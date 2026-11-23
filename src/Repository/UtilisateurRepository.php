<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données de la table utilisateur. Les requêtes métier sont ajoutées
 * avec les fonctionnalités qui les utilisent.
 *
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /** Recherche insensible à la casse : les emails sont stockés en minuscules. */
    public function emailExiste(string $email): bool
    {
        return null !== $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }
}
