<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Refus d'une réservation ou d'une annulation par une règle de gestion.
 *
 * Le message est destiné à l'adhérent : le contrôleur l'affiche tel quel sur le
 * formulaire, sans connaître la règle en cause. Une sous-classe par règle permet
 * aux tests et au dossier de les distinguer.
 */
abstract class ReservationRefuseeException extends \DomainException
{
}
