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

    /**
     * Prénom et nom de plusieurs utilisateurs en une seule requête (écran E9 : les acteurs
     * du journal MongoDB sont retrouvés dans PostgreSQL par leur identifiant).
     *
     * @param list<int> $ids
     *
     * @return array<int, string> « Prénom Nom » indexé par identifiant
     */
    public function nomsParIdentifiant(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $lignes = $this->createQueryBuilder('u')
            ->select('u.id', 'u.prenom', 'u.nom')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', array_values(array_unique($ids)))
            ->getQuery()
            ->getArrayResult();

        $noms = [];
        foreach ($lignes as $ligne) {
            $noms[(int) $ligne['id']] = $ligne['prenom'].' '.$ligne['nom'];
        }

        return $noms;
    }
}
