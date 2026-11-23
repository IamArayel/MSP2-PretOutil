<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Objet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données de la table objet. Les requêtes métier sont ajoutées
 * avec les fonctionnalités qui les utilisent.
 *
 * @extends ServiceEntityRepository<Objet>
 */
class ObjetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Objet::class);
    }
}
