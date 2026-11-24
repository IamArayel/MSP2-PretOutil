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
use App\Repository\ReservationRepository;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Règles de réservation (US4, RG1 à RG5) et d'annulation (US5), selon le diagramme
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
