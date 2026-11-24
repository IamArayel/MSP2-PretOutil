<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Situation d'un objet aujourd'hui, affichée en badge sur les écrans E3 et E4.
 * Elle se déduit de l'état de l'objet et de ses réservations : elle n'est jamais stockée.
 */
enum StatutDuJour: string
{
    case Disponible = 'disponible';
    case Reserve = 'reserve';
    case Sorti = 'sorti';
    case HorsService = 'hors_service';

    public function libelle(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Reserve => 'Réservé',
            self::Sorti => 'Sorti',
            self::HorsService => 'Hors service',
        };
    }
}
