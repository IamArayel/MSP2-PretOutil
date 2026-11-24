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
