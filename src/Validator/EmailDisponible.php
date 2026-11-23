<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * L'email n'est pas déjà utilisé par un compte (US1).
 * UniqueEntity ne s'applique qu'aux entités : cette contrainte fait la même
 * vérification sur un DTO.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class EmailDisponible extends Constraint
{
    public string $message = 'Un compte existe déjà avec cet email.';
}
