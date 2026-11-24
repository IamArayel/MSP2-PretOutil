<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Categorie;

/**
 * Filtres du catalogue (US3), tous facultatifs et combinables.
 *
 * La cohérence des dates (fin après début, pas dans le passé) est validée par le
 * formulaire de l'écran E3 ; la période ne s'applique que si les deux dates sont fournies.
 */
final class CriteresCatalogue
{
    public function __construct(
        public ?Categorie $categorie = null,
        public ?\DateTimeImmutable $debut = null,
        public ?\DateTimeImmutable $fin = null,
    ) {
    }

    public function aUnePeriode(): bool
    {
        return null !== $this->debut && null !== $this->fin;
    }
}
