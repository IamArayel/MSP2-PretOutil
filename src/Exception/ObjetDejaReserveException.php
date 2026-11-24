<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée quand la base refuse de supprimer un objet qui a des réservations
 * (fk_reservation_objet, ON DELETE RESTRICT), par exemple si une réservation
 * est créée entre l'affichage de l'écran E7 et la demande de suppression.
 */
final class ObjetDejaReserveException extends \DomainException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Cet objet a déjà été réservé : il ne peut pas être supprimé, seulement désactivé.', 0, $previous);
    }
}
