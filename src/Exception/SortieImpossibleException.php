<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * US7 : la sortie ne peut pas être validée (réservation plus confirmée, période pas
 * encore commencée, objet hors service).
 */
final class SortieImpossibleException extends ReservationRefuseeException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
