<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Utilisateur;
use App\Pagination\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données de la table utilisateur. Les requêtes métier sont ajoutées
 * avec les fonctionnalités qui les utilisent.
 *
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public const int COMPTES_PAR_PAGE = 20;

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

    /**
     * Comptes de l'écran E8 (US9), comptes actifs d'abord, puis par nom.
     *
     * @return Page<Utilisateur>
     */
    public function listerPourGestion(int $numeroPage = 1): Page
    {
        $numeroPage = max(1, $numeroPage);

        $requete = $this->createQueryBuilder('u')
            ->orderBy('u.actif', 'DESC')
            ->addOrderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->addOrderBy('u.id', 'ASC')
            ->setFirstResult(($numeroPage - 1) * self::COMPTES_PAR_PAGE)
            ->setMaxResults(self::COMPTES_PAR_PAGE)
            ->getQuery();

        $paginator = new Paginator($requete, fetchJoinCollection: false);

        return new Page(
            iterator_to_array($paginator, false),
            \count($paginator),
            $numeroPage,
            self::COMPTES_PAR_PAGE,
        );
    }
}
