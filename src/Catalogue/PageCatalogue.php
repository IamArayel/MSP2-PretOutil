<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Entity\Objet;
use App\Enum\StatutDuJour;
use App\Pagination\Page;

/**
 * Données de l'écran E3 : une page d'objets et le statut du jour de chacun.
 */
final readonly class PageCatalogue
{
    /**
     * @param Page<Objet>               $page
     * @param array<int, StatutDuJour> $statuts indexés par identifiant d'objet
     */
    public function __construct(
        public Page $page,
        private array $statuts,
    ) {
    }

    public function statut(Objet $objet): StatutDuJour
    {
        return $this->statuts[$objet->getId()] ?? StatutDuJour::Disponible;
    }
}
