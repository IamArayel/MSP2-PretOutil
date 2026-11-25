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
     * $aujourdhui sert à repérer les retours en retard (filtre de disponibilité).
     *
     * @return Page<Objet>
     */
    public function rechercherCatalogue(CriteresCatalogue $criteres, \DateTimeImmutable $aujourdhui, int $numeroPage = 1): Page
    {
        $numeroPage = max(1, $numeroPage);

        $requete = $this->catalogue($criteres, $aujourdhui)
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
     * triés par nom, avec leur catégorie, paginés. $recherche filtre sur le nom (#109).
     *
     * @return Page<Objet>
     */
    public function listerPourGestion(int $numeroPage = 1, ?string $recherche = null): Page
    {
        $numeroPage = max(1, $numeroPage);

        $qb = $this->createQueryBuilder('o')
            ->addSelect('c')
            ->innerJoin('o.categorie', 'c')
            ->orderBy('o.actif', 'DESC')
            ->addOrderBy('o.nom', 'ASC')
            ->addOrderBy('o.id', 'ASC');
        $this->filtrerParNom($qb, $recherche);

        $requete = $qb
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

    private function catalogue(CriteresCatalogue $criteres, \DateTimeImmutable $aujourdhui): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->addSelect('c')
            ->innerJoin('o.categorie', 'c')
            ->andWhere('o.actif = true')
            ->orderBy('o.nom', 'ASC')
            ->addOrderBy('o.id', 'ASC');

        $this->filtrerParNom($qb, $criteres->recherche);

        if (null !== $criteres->categorie) {
            $qb->andWhere('o.categorie = :categorie')
                ->setParameter('categorie', $criteres->categorie);
        }

        if ($criteres->aUnePeriode()) {
            $this->filtrerDisponibles($qb, $criteres->debut, $criteres->fin, $aujourdhui);
        }

        return $qb;
    }

    /**
     * Objets dont le nom contient le texte cherché, sans tenir compte de la casse ni des accents
     * (« echelle » trouve « Échelle »). Les caractères % et _ saisis sont cherchés tels quels.
     */
    private function filtrerParNom(QueryBuilder $qb, ?string $recherche): void
    {
        $recherche = mb_substr(trim($recherche ?? ''), 0, CriteresCatalogue::LONGUEUR_MAX_RECHERCHE);
        if ('' === $recherche) {
            return;
        }

        $qb->andWhere('LOWER(UNACCENT(o.nom)) LIKE LOWER(UNACCENT(:recherche))')
            ->setParameter('recherche', '%'.addcslashes($recherche, '%_\\').'%');
    }

    /**
     * Objets réservables sur toute la période, avec les mêmes règles que ReservationService :
     * pas hors service (RG5), aucune réservation active qui la chevauche (RG2, bornes incluses
     * comme la contrainte ex_reservation_chevauchement), pas de retour en retard.
     */
    private function filtrerDisponibles(QueryBuilder $qb, \DateTimeImmutable $debut, \DateTimeImmutable $fin, \DateTimeImmutable $aujourdhui): void
    {
        $chevauchement = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Reservation::class, 'r')
            ->where('r.objet = o')
            ->andWhere('r.statut IN (:statutsActifs)')
            ->andWhere('r.dateDebut <= :fin')
            ->andWhere('r.dateFin >= :debut');

        // Retours en retard : très peu de lignes, sous-requête calculée une seule fois
        // (index idx_reservation_en_cours ; voir docs/conception/requetes-catalogue.md).
        $retards = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(r2.objet)')
            ->from(Reservation::class, 'r2')
            ->where('r2.statut = :enCours')
            ->andWhere('r2.dateFin < :aujourdhui');

        $qb->andWhere('o.etat <> :horsService')
            ->andWhere($qb->expr()->not($qb->expr()->exists($chevauchement->getDQL())))
            ->andWhere($qb->expr()->notIn('o.id', $retards->getDQL()))
            ->setParameter('horsService', EtatObjet::HorsService->value)
            ->setParameter(
                'statutsActifs',
                array_map(static fn (StatutReservation $statut): string => $statut->value, StatutReservation::actifs()),
                ArrayParameterType::STRING,
            )
            ->setParameter('debut', $debut, Types::DATE_IMMUTABLE)
            ->setParameter('fin', $fin, Types::DATE_IMMUTABLE)
            ->setParameter('enCours', StatutReservation::EnCours->value)
            ->setParameter('aujourdhui', $aujourdhui, Types::DATE_IMMUTABLE);
    }
}
