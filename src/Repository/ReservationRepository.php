<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Objet;
use App\Entity\Reservation;
use App\Enum\StatutReservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
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
}
