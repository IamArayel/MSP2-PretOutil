<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Enum\EtatObjet;
use App\Enum\StatutReservation;
use App\Exception\AnnulationImpossibleException;
use App\Exception\DureeDepasseeException;
use App\Exception\ObjetNonReservableException;
use App\Exception\PeriodeIndisponibleException;
use App\Exception\PeriodeInvalideException;
use App\Exception\QuotaAtteintException;
use App\Exception\RetourImpossibleException;
use App\Exception\SortieImpossibleException;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Règles de réservation (US4, RG1 à RG5), d'annulation (US5), de sortie (US7) et de
 * retour (US8, RG7), selon le diagramme
 * de séquence docs/diagrammes/sequence-reserver.md.
 *
 * Chaque refus lève une exception métier (App\Exception\ReservationRefuseeException)
 * dont le message est affiché à l'adhérent. Les droits (adhérent connecté, propriétaire,
 * RG6 pour l'annulation) sont vérifiés avant l'appel, par le contrôleur et les Voters.
 */
class ReservationService
{
    public const int DUREE_MAX_JOURS = 14;
    public const int QUOTA_RESERVATIONS_ACTIVES = 3;

    /** Code SQLSTATE d'une violation de contrainte d'exclusion (ex_reservation_chevauchement). */
    private const string VIOLATION_EXCLUSION = '23P01';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservationRepository,
        private readonly AuditLogger $auditLogger,
        private readonly ClockInterface $horloge,
    ) {
    }

    /**
     * @throws PeriodeInvalideException     RG1
     * @throws DureeDepasseeException       RG3
     * @throws ObjetNonReservableException  RG5, ou retour en retard
     * @throws QuotaAtteintException        RG4
     * @throws PeriodeIndisponibleException RG2, y compris en cas d'accès simultanés
     */
    public function reserver(Utilisateur $adherent, Objet $objet, \DateTimeImmutable $debut, \DateTimeImmutable $fin): Reservation
    {
        $aujourdhui = $this->aujourdhui();
        $debut = $debut->setTime(0, 0);
        $fin = $fin->setTime(0, 0);

        // Règles qui ne dépendent que de la demande : vérifiées avant d'ouvrir la transaction.
        $this->verifierPeriode($debut, $fin, $aujourdhui);
        $this->verifierDuree($debut, $fin);

        $connexion = $this->entityManager->getConnection();
        $connexion->beginTransaction();

        try {
            $this->verifierObjetReservable($objet, $aujourdhui);

            // RG4 sous verrou : deux demandes simultanées du même adhérent passent l'une après l'autre.
            $this->reservationRepository->verrouillerAdherent($adherent);
            if ($this->reservationRepository->compterActives($adherent) >= self::QUOTA_RESERVATIONS_ACTIVES) {
                throw new QuotaAtteintException(self::QUOTA_RESERVATIONS_ACTIVES);
            }

            if ($this->reservationRepository->existeChevauchement($objet, $debut, $fin)) {
                throw new PeriodeIndisponibleException();
            }

            $reservation = new Reservation($adherent, $objet, $debut, $fin);
            $adherent->setDerniereActiviteLe($this->horloge->now());
            $this->entityManager->persist($reservation);
            $this->entityManager->flush();

            $connexion->commit();
        } catch (\Throwable $erreur) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            throw $this->traduireConflit($erreur);
        }

        // Après le COMMIT : on ne journalise que ce qui a réellement été enregistré (RG8).
        $this->auditLogger->journaliser(ActionAudit::ReservationCreee, $adherent, 'reservation', $reservation->getId(), [
            'objet' => $objet->getNom(),
            'date_debut' => $debut->format('Y-m-d'),
            'date_fin' => $fin->format('Y-m-d'),
        ]);

        return $reservation;
    }

    /**
     * Annule une réservation confirmée. Le droit d'annuler (propriétaire, jusqu'à la
     * veille du début, RG6) est vérifié avant l'appel par ReservationVoter::ANNULER.
     *
     * @throws AnnulationImpossibleException si la réservation n'est plus confirmée
     */
    public function annuler(Reservation $reservation, Utilisateur $adherent): void
    {
        if (StatutReservation::Confirmee !== $reservation->getStatut()) {
            throw new AnnulationImpossibleException();
        }

        $reservation->setStatut(StatutReservation::Annulee);
        $adherent->setDerniereActiviteLe($this->horloge->now());
        $this->entityManager->flush();

        $this->auditLogger->journaliser(ActionAudit::ReservationAnnulee, $adherent, 'reservation', $reservation->getId(), [
            'objet' => $reservation->getObjet()->getNom(),
            'date_debut' => $reservation->getDateDebut()->format('Y-m-d'),
            'date_fin' => $reservation->getDateFin()->format('Y-m-d'),
        ]);
    }

    /**
     * US7 : le gestionnaire remet l'objet à l'adhérent. Confirmée → en cours.
     *
     * @throws SortieImpossibleException si la réservation n'est plus confirmée, si sa période
     *                                   n'a pas commencé, si l'objet est hors service ou
     *                                   pas encore rendu par l'emprunteur précédent
     */
    public function validerSortie(Reservation $reservation, Utilisateur $gestionnaire): void
    {
        $this->dansUneTransaction(function () use ($reservation, $gestionnaire): void {
            // Verrou : si un autre gestionnaire vient de valider cette sortie, on voit son statut.
            $this->reservationRepository->verrouiller($reservation);

            if (StatutReservation::Confirmee !== $reservation->getStatut()) {
                throw new SortieImpossibleException('Cette sortie ne peut pas être validée : la réservation n\'est plus confirmée.');
            }
            if ($reservation->getDateDebut()->format('Y-m-d') > $this->aujourdhui()->format('Y-m-d')) {
                throw new SortieImpossibleException(\sprintf(
                    'La réservation commence le %s : la sortie ne peut pas encore être validée.',
                    $reservation->getDateDebut()->format('d/m/Y'),
                ));
            }
            if (EtatObjet::HorsService === $reservation->getObjet()->getEtat()) {
                throw new SortieImpossibleException('Cet objet est hors service : il ne peut pas être remis à l\'adhérent.');
            }
            if ($this->reservationRepository->objetEstSorti($reservation->getObjet())) {
                throw new SortieImpossibleException('Cet objet n\'a pas encore été rendu par l\'emprunteur précédent : enregistrez d\'abord son retour.');
            }

            $reservation
                ->enregistrerSortie($gestionnaire, $this->horloge->now())
                ->setStatut(StatutReservation::EnCours);
        });

        // Après le COMMIT (RG8)
        $this->auditLogger->journaliser(ActionAudit::SortieValidee, $gestionnaire, 'reservation', $reservation->getId(), [
            'objet' => $reservation->getObjet()->getNom(),
            'adherent' => $reservation->getUtilisateur()->getEmail(),
        ]);
    }

    /**
     * US8 : l'objet est rendu. En cours → terminée, avec l'état constaté, que l'objet prend
     * aussi (RG7 : « hors service » le rend non réservable).
     *
     * Les réservations confirmées de l'objet ne sont pas annulées automatiquement : si l'objet
     * revient hors service, elles sont renvoyées pour que le gestionnaire prévienne les adhérents.
     *
     * @return list<Reservation> réservations à signaler (vide sauf retour hors service)
     *
     * @throws RetourImpossibleException si la réservation n'est pas en cours
     */
    public function enregistrerRetour(Reservation $reservation, Utilisateur $gestionnaire, EtatObjet $etat): array
    {
        $objet = $reservation->getObjet();
        $etatAvant = $objet->getEtat();

        $this->dansUneTransaction(function () use ($reservation, $gestionnaire, $etat, $objet): void {
            $this->reservationRepository->verrouiller($reservation);

            if (StatutReservation::EnCours !== $reservation->getStatut()) {
                throw new RetourImpossibleException();
            }

            $reservation
                ->enregistrerRetour($gestionnaire, $this->horloge->now(), $etat)
                ->setStatut(StatutReservation::Terminee);
            $objet->setEtat($etat);
        });

        $this->auditLogger->journaliser(ActionAudit::RetourEnregistre, $gestionnaire, 'reservation', $reservation->getId(), [
            'objet' => $objet->getNom(),
            'etat_retour' => $etat->value,
            'etat_objet_avant' => $etatAvant->value,
        ]);

        if (EtatObjet::HorsService !== $etat) {
            return [];
        }

        return $this->reservationRepository->confirmeesPourObjet($objet);
    }

    /**
     * Exécute une transition dans une transaction : flush et COMMIT si tout passe,
     * ROLLBACK et exception relancée sinon.
     *
     * @param \Closure(): void $transition
     */
    private function dansUneTransaction(\Closure $transition): void
    {
        $connexion = $this->entityManager->getConnection();
        $connexion->beginTransaction();

        try {
            $transition();
            $this->entityManager->flush();
            $connexion->commit();
        } catch (\Throwable $erreur) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            throw $erreur;
        }
    }

    /** RG1 : fin après début, début aujourd'hui au plus tôt. */
    private function verifierPeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin, \DateTimeImmutable $aujourdhui): void
    {
        if ($fin < $debut) {
            throw new PeriodeInvalideException('La date de fin doit être après la date de début.');
        }
        if ($debut < $aujourdhui) {
            throw new PeriodeInvalideException('La réservation ne peut pas commencer dans le passé.');
        }
    }

    /** RG3 : 14 jours au plus, premier et dernier jour compris. */
    private function verifierDuree(\DateTimeImmutable $debut, \DateTimeImmutable $fin): void
    {
        if ($debut->diff($fin)->days + 1 > self::DUREE_MAX_JOURS) {
            throw new DureeDepasseeException(self::DUREE_MAX_JOURS);
        }
    }

    /** RG5 : objet actif, pas hors service, et rendu par l'emprunteur précédent. */
    private function verifierObjetReservable(Objet $objet, \DateTimeImmutable $aujourdhui): void
    {
        if (!$objet->isActif()) {
            throw new ObjetNonReservableException('Cet objet ne fait plus partie du catalogue.');
        }
        if (EtatObjet::HorsService === $objet->getEtat()) {
            throw new ObjetNonReservableException('Cet objet est hors service : il ne peut pas être réservé.');
        }
        if ($this->reservationRepository->existeRetourEnRetard($objet, $aujourdhui)) {
            throw new ObjetNonReservableException('Cet objet n\'a pas encore été rendu par l\'emprunteur précédent : il ne peut pas être réservé pour le moment.');
        }
    }

    /**
     * Une réservation concurrente a pu être enregistrée entre la vérification de RG2 et
     * l'INSERT : la contrainte EXCLUDE la refuse (SQLSTATE 23P01), traduite en refus métier.
     */
    private function traduireConflit(\Throwable $erreur): \Throwable
    {
        if ($erreur instanceof DriverException && self::VIOLATION_EXCLUSION === $erreur->getSQLState()) {
            return new PeriodeIndisponibleException($erreur);
        }

        return $erreur;
    }

    private function aujourdhui(): \DateTimeImmutable
    {
        return $this->horloge->now()->setTime(0, 0);
    }
}
