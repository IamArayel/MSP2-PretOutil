<?php

declare(strict_types=1);

namespace App\Exception;

/** RG1 : la date de fin précède la date de début, ou la période commence dans le passé. */
final class PeriodeInvalideException extends ReservationRefuseeException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
