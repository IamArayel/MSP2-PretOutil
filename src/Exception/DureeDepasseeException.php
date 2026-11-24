<?php

declare(strict_types=1);

namespace App\Exception;

/** RG3 : la période dépasse la durée maximale d'une réservation. */
final class DureeDepasseeException extends ReservationRefuseeException
{
    public function __construct(int $joursMax, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Une réservation dure %d jours au maximum.', $joursMax), 0, $previous);
    }
}
