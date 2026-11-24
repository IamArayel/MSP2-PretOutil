<?php

declare(strict_types=1);

namespace App\Exception;

/** Annulation refusée : seule une réservation confirmée, pas encore commencée, peut être annulée (RG6). */
final class AnnulationImpossibleException extends ReservationRefuseeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Cette réservation ne peut plus être annulée.', 0, $previous);
    }
}
