<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée quand la base refuse un email déjà utilisé (contrainte d'unicité),
 * par exemple si deux inscriptions identiques arrivent en même temps
 * et passent toutes les deux la validation.
 */
final class EmailDejaUtiliseException extends \DomainException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Un compte existe déjà avec cet email.', 0, $previous);
    }
}
