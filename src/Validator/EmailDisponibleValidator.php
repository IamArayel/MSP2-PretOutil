<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\UtilisateurRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class EmailDisponibleValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EmailDisponible) {
            throw new UnexpectedTypeException($constraint, EmailDisponible::class);
        }

        // Champ vide ou mal formé : déjà signalé par NotBlank et Email
        if (null === $value || '' === $value) {
            return;
        }

        if ($this->utilisateurRepository->emailExiste($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
