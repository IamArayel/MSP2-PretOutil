<?php

declare(strict_types=1);

namespace App\Exception;

/** RG2 : une réservation active de l'objet chevauche la période demandée. */
final class PeriodeIndisponibleException extends ReservationRefuseeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Cet objet est déjà réservé sur une partie de cette période. Choisissez d\'autres dates.', 0, $previous);
    }
}
