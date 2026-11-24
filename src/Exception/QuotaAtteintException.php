<?php

declare(strict_types=1);

namespace App\Exception;

/** RG4 : l'adhérent a déjà le nombre maximal de réservations actives. */
final class QuotaAtteintException extends ReservationRefuseeException
{
    public function __construct(int $quota, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Vous avez déjà %d réservations en cours ou à venir : c\'est le maximum.', $quota), 0, $previous);
    }
}
