<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Entity\Objet;
use App\Enum\StatutDuJour;

/**
 * Données de l'écran E4 : l'objet, son statut du jour et ses périodes déjà réservées.
 */
final readonly class FicheObjet
{
    /**
     * @param list<array{dateDebut: \DateTimeImmutable, dateFin: \DateTimeImmutable}> $periodesReservees
     */
    public function __construct(
        public Objet $objet,
        public StatutDuJour $statut,
        public array $periodesReservees,
    ) {
    }

    /** Un objet hors service ne peut pas être réservé (RG5). */
    public function estReservable(): bool
    {
        return StatutDuJour::HorsService !== $this->statut;
    }
}
