<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Refus d'une action de l'écran E8 (US9) : un administrateur ne peut ni se désactiver
 * ni retirer son propre rôle, pour que l'association garde toujours un administrateur.
 * Le message est affiché tel quel.
 */
final class ModificationCompteImpossibleException extends \DomainException
{
}
