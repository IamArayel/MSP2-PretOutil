<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\CriteresCatalogue;
use App\Entity\Objet;
use App\Entity\Reservation;
use App\Enum\EtatObjet;
use App\Enum\StatutReservation;
use App\Pagination\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données de la table objet.
 *
 * @extends ServiceEntityRepository<Objet>
 */
class ObjetRepository extends ServiceEntityRepository
{
    public const int OBJETS_PAR_PAGE = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Objet::class);
    }

    /**
     * Catalogue (US3) : objets actifs triés par nom, filtrés selon les critères, paginés.
     *
     * @return Page<Objet>
     */
    public function rechercherCatalogue(CriteresCatalogue $criteres, int $numeroPage = 1): Page
    {
        $numeroPage = max(1, $numeroPage);

        $requete = $this->catalogue($criteres)
            ->setFirstResult(($numeroPage - 1) * self::OBJETS_PAR_PAGE)
            ->setMaxResults(self::OBJETS_PAR_PAGE)
            ->getQuery();

        // Jointure vers une seule catégorie par objet : pas de collection à dédoublonner.
        $paginator = new Paginator($requete, fetchJoinCollection: false);

        return new Page(
            iterator_to_array($paginator, false),
            \count($paginator),
            $numeroPage,
            self::OBJETS_PAR_PAGE,
        );
    }

    /**
     * Écran E7 Gestion du catalogue (US6) : tous les objets, actifs puis désactivés,
     * triés par nom, avec leur catégorie, paginés.
     *
     * @return Page<Objet>
     */
    public function listerPourGestion(int $numeroPage = 1): Page
    {
        $numeroPage = max(1, $numeroPage);

        $requete = $this->createQueryBuilder('o')
            ->addSelect('c')
            ->innerJoin('o.categorie', 'c')
            ->orderBy('o.actif', 'DESC')
            ->addOrderBy('o.nom', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->setFirstResult(($numeroPage - 1) * self::OBJETS_PAR_PAGE)
            ->setMaxResults(self::OBJETS_PAR_PAGE)
            ->getQuery();

        $paginator = new Paginator($requete, fetchJoinCollection: false);

        return new Page(
            iterator_to_array($paginator, false),
            \count($paginator),
            $numeroPage,
            self::OBJETS_PAR_PAGE,
        );
    }

    private function catalogue(CriteresCatalogue $criteres): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->addSelect('c')
            ->innerJoin('o.categorie', 'c')
            ->andWhere('o.actif = true')
            ->orderBy('o.nom', 'ASC')
            ->addOrderBy('o.id', 'ASC');

        if (null !== $criteres->categorie) {
            $qb->andWhere('o.categorie = :categorie')
                ->setParameter('categorie', $criteres->categorie);
        }

        if ($criteres->aUnePeriode()) {
            $this->filtrerDisponibles($qb, $criteres->debut, $criteres->fin);
        }

        return $qb;
    }

    /**
     * Objets réservables sur toute la période : pas hors service (RG5) et aucune réservation
     * active qui la chevauche (RG2). Bornes incluses, comme la contrainte ex_reservation_chevauchement.
     */
    private function filtrerDisponibles(QueryBuilder $qb, \DateTimeImmutable $debut, \DateTimeImmutable $fin): void
    {
        $chevauchement = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Reservation::class, 'r')
            ->where('r.objet = o')
            ->andWhere('r.statut IN (:statutsActifs)')
            ->andWhere('r.dateDebut <= :fin')
            ->andWhere('r.dateFin >= :debut');

        $qb->andWhere('o.etat <> :horsService')
            ->andWhere($qb->expr()->not($qb->expr()->exists($chevauchement->getDQL())))
            ->setParameter('horsService', EtatObjet::HorsService->value)
            ->setParameter(
                'statutsActifs',
                array_map(static fn (StatutReservation $statut): string => $statut->value, StatutReservation::actifs()),
                ArrayParameterType::STRING,
            )
            ->setParameter('debut', $debut, Types::DATE_IMMUTABLE)
            ->setParameter('fin', $fin, Types::DATE_IMMUTABLE);
    }
}
