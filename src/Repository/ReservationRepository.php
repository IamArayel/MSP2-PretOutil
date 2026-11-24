<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Objet;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données de la table reservation. Les requêtes métier sont ajoutées
 * avec les fonctionnalités qui les utilisent.
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
