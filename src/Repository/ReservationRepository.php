<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\StatutReservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données de la table reservation.
 *
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Périodes pendant lesquelles un objet est déjà réservé (écran E4), de la plus proche
     * à la plus lointaine. Seules les réservations actives qui ne sont pas terminées avant
     * $aPartirDu sont renvoyées ; aucune donnée sur l'adhérent n'est exposée.
     *
     * @return list<array{dateDebut: \DateTimeImmutable, dateFin: \DateTimeImmutable}>
     */
    public function periodesReservees(Objet $objet, \DateTimeImmutable $aPartirDu): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.dateDebut', 'r.dateFin')
            ->where('r.objet = :objet')
            ->andWhere('r.statut IN (:statutsActifs)')
            ->andWhere('r.dateFin >= :aPartirDu')
            ->orderBy('r.dateDebut', 'ASC')
            ->setParameter('objet', $objet)
            ->setParameter(
                'statutsActifs',
                array_map(static fn (StatutReservation $statut): string => $statut->value, StatutReservation::actifs()),
                ArrayParameterType::STRING,
            )
            ->setParameter('aPartirDu', $aPartirDu, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Statut des réservations actives qui couvrent une date, pour une liste d'objets,
     * en une seule requête (évite une requête par objet affiché).
     *
     * @param list<Objet> $objets
     *
     * @return array<int, list<StatutReservation>> statuts indexés par identifiant d'objet
     */
    public function statutsActifsAuJour(array $objets, \DateTimeImmutable $jour): array
    {
        if ([] === $objets) {
            return [];
        }

        $lignes = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.objet) AS objetId', 'r.statut')
            ->where('r.objet IN (:objets)')
            ->andWhere('r.statut IN (:statutsActifs)')
            ->andWhere('r.dateDebut <= :jour')
            ->andWhere('r.dateFin >= :jour OR r.statut = :enCours')
            ->setParameter('objets', $objets)
            ->setParameter(
                'statutsActifs',
                array_map(static fn (StatutReservation $statut): string => $statut->value, StatutReservation::actifs()),
                ArrayParameterType::STRING,
            )
            ->setParameter('jour', $jour, Types::DATE_IMMUTABLE)
            ->setParameter('enCours', StatutReservation::EnCours->value)
            ->getQuery()
            ->getArrayResult();

        $statuts = [];
        foreach ($lignes as $ligne) {
            $statuts[(int) $ligne['objetId']][] = $ligne['statut'];
        }

        return $statuts;
    }

    /**
     * Parmi une liste d'objets, ceux qui ont au moins une réservation (tous statuts),
     * en une seule requête : l'écran E7 n'affiche « Supprimer » que pour les autres.
     * La suppression elle-même reste autorisée par ObjetVoter::SUPPRIMER.
     *
     * @param list<Objet> $objets
     *
     * @return array<int, true> identifiants des objets déjà réservés
     */
    public function objetsDejaReserves(array $objets): array
    {
        if ([] === $objets) {
            return [];
        }

        $lignes = $this->createQueryBuilder('r')
            ->select('DISTINCT IDENTITY(r.objet) AS objetId')
            ->where('r.objet IN (:objets)')
            ->setParameter('objets', $objets)
            ->getQuery()
            ->getArrayResult();

        return array_fill_keys(array_map(static fn (array $ligne): int => (int) $ligne['objetId'], $lignes), true);
    }

    /**
     * Vrai si l'objet a au moins une réservation, quel que soit son statut (annulée comprise).
     * Un tel objet ne peut pas être supprimé physiquement (US6) : la clé étrangère
     * fk_reservation_objet (ON DELETE RESTRICT) le refuserait.
     */
    public function existePourObjet(Objet $objet): bool
    {
        $resultat = $this->createQueryBuilder('r')
            ->select('1')
            ->where('r.objet = :objet')
            ->setParameter('objet', $objet)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $resultat;
    }

    /**
     * Réservations d'un adhérent pour l'écran E5, avec leur objet (une seule requête) :
     * à venir, puis en cours, puis terminées et annulées ; dans chaque groupe, par date de début décroissante.
     *
     * @return list<Reservation>
     */
    public function listerPourAdherent(Utilisateur $adherent): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('o')
            ->innerJoin('r.objet', 'o')
            ->addSelect("CASE WHEN r.statut = 'confirmee' THEN 1 WHEN r.statut = 'en_cours' THEN 2 ELSE 3 END AS HIDDEN rang")
            ->where('r.utilisateur = :adherent')
            ->setParameter('adherent', $adherent)
            ->orderBy('rang', 'ASC')
            ->addOrderBy('r.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Verrouille la ligne de l'adhérent jusqu'à la fin de la transaction en cours
     * (SELECT ... FOR UPDATE) : deux demandes simultanées du même adhérent passent
     * l'une après l'autre et ne peuvent pas dépasser le quota (RG4).
     */
    public function verrouillerAdherent(Utilisateur $adherent): void
    {
        $this->getEntityManager()->lock($adherent, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Recharge la réservation en verrouillant sa ligne (SELECT ... FOR UPDATE) jusqu'à la fin
     * de la transaction : deux gestionnaires qui valident la même sortie ou le même retour
     * passent l'un après l'autre, et le second voit le statut déjà modifié (US7, US8).
     */
    public function verrouiller(Reservation $reservation): void
    {
        $this->getEntityManager()->refresh($reservation, LockMode::PESSIMISTIC_WRITE);
    }

    /** Vrai si l'objet est actuellement sorti (une réservation en cours), donc pas dans les locaux. */
    public function objetEstSorti(Objet $objet): bool
    {
        return null !== $this->createQueryBuilder('r')
            ->select('1')
            ->where('r.objet = :objet')
            ->andWhere('r.statut = :enCours')
            ->setParameter('objet', $objet)
            ->setParameter('enCours', StatutReservation::EnCours->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Écran E6 : réservations confirmées dont la période a commencé (sortie à valider),
     * de la plus ancienne à la plus récente. Objet et adhérent chargés dans la même requête.
     *
     * @return list<Reservation>
     */
    public function sortiesAValider(\DateTimeImmutable $aujourdhui): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('o', 'u')
            ->innerJoin('r.objet', 'o')
            ->innerJoin('r.utilisateur', 'u')
            ->where('r.statut = :confirmee')
            ->andWhere('r.dateDebut <= :aujourdhui')
            ->setParameter('confirmee', StatutReservation::Confirmee->value)
            ->setParameter('aujourdhui', $aujourdhui, Types::DATE_IMMUTABLE)
            ->orderBy('r.dateDebut', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Écran E6 : objets sortis (réservations en cours), retours en retard en premier
     * grâce au tri par date de fin (index partiel idx_reservation_en_cours).
     *
     * @return list<Reservation>
     */
    public function retoursAttendus(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('o', 'u')
            ->innerJoin('r.objet', 'o')
            ->innerJoin('r.utilisateur', 'u')
            ->where('r.statut = :enCours')
            ->setParameter('enCours', StatutReservation::EnCours->value)
            // Tri sur date_fin seule : l'index partiel fournit déjà cet ordre (pas de tri en mémoire).
            ->orderBy('r.dateFin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réservations confirmées d'un objet, pas encore sorties (RG7) : à signaler au
     * gestionnaire quand l'objet revient hors service. Adhérent chargé dans la même requête.
     *
     * @return list<Reservation>
     */
    public function confirmeesPourObjet(Objet $objet): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('u')
            ->innerJoin('r.utilisateur', 'u')
            ->where('r.objet = :objet')
            ->andWhere('r.statut = :confirmee')
            ->setParameter('objet', $objet)
            ->setParameter('confirmee', StatutReservation::Confirmee->value)
            ->orderBy('r.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Réservations actives (confirmées ou en cours) d'un adhérent (RG4). */
    public function compterActives(Utilisateur $adherent): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.utilisateur = :adherent')
            ->andWhere('r.statut IN (:statutsActifs)')
            ->setParameter('adherent', $adherent)
            ->setParameter('statutsActifs', $this->valeursActives(), ArrayParameterType::STRING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vrai si une réservation active de l'objet chevauche la période (RG2).
     * Bornes incluses, comme la contrainte ex_reservation_chevauchement.
     */
    public function existeChevauchement(Objet $objet, \DateTimeImmutable $debut, \DateTimeImmutable $fin): bool
    {
        return null !== $this->createQueryBuilder('r')
            ->select('1')
            ->where('r.objet = :objet')
            ->andWhere('r.statut IN (:statutsActifs)')
            ->andWhere('r.dateDebut <= :fin')
            ->andWhere('r.dateFin >= :debut')
            ->setParameter('objet', $objet)
            ->setParameter('statutsActifs', $this->valeursActives(), ArrayParameterType::STRING)
            ->setParameter('debut', $debut, Types::DATE_IMMUTABLE)
            ->setParameter('fin', $fin, Types::DATE_IMMUTABLE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vrai si l'objet est sorti et que sa date de retour est dépassée : il n'est pas
     * réservable tant que le retour n'est pas enregistré (décision du 2026-11-24).
     */
    public function existeRetourEnRetard(Objet $objet, \DateTimeImmutable $aujourdhui): bool
    {
        return null !== $this->createQueryBuilder('r')
            ->select('1')
            ->where('r.objet = :objet')
            ->andWhere('r.statut = :enCours')
            ->andWhere('r.dateFin < :aujourdhui')
            ->setParameter('objet', $objet)
            ->setParameter('enCours', StatutReservation::EnCours->value)
            ->setParameter('aujourdhui', $aujourdhui, Types::DATE_IMMUTABLE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<string> */
    private function valeursActives(): array
    {
        return array_map(static fn (StatutReservation $statut): string => $statut->value, StatutReservation::actifs());
    }
}
