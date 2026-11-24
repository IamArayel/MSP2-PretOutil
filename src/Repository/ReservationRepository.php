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
}
