<?php

declare(strict_types=1);

namespace App\Exception;

/** US8 : le retour ne peut être enregistré que pour une réservation en cours (objet sorti). */
final class RetourImpossibleException extends ReservationRefuseeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Ce retour ne peut pas être enregistré : l\'objet n\'est pas sorti, ou son retour est déjà enregistré.', 0, $previous);
    }
}
