<?php

declare(strict_types=1);

namespace App\Exception;

/** RG5 : objet désactivé, hors service, ou pas encore rendu par l'emprunteur précédent. */
final class ObjetNonReservableException extends ReservationRefuseeException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
