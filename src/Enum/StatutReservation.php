<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Étape du cycle de vie d'une réservation (colonne reservation.statut).
 * Les valeurs sont celles stockées en base (contrainte ck_reservation_statut).
 */
enum StatutReservation: string
{
    case Confirmee = 'confirmee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Annulee = 'annulee';

    /**
     * Une réservation active bloque l'objet (RG2) et compte dans le quota (RG4).
     */
    public function estActive(): bool
    {
        return self::Confirmee === $this || self::EnCours === $this;
    }
}
