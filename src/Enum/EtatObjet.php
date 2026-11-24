<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * État physique d'un objet (colonnes objet.etat et reservation.etat_retour).
 * Les valeurs sont celles stockées en base (contraintes ck_objet_etat et ck_reservation_etat_retour).
 */
enum EtatObjet: string
{
    case Bon = 'bon';
    case Abime = 'abime';
    case HorsService = 'hors_service';

    /** Libellé affiché (formulaire et liste de l'écran E7, écran E6). */
    public function libelle(): string
    {
        return match ($this) {
            self::Bon => 'Bon',
            self::Abime => 'Abîmé',
            self::HorsService => 'Hors service',
        };
    }
}
