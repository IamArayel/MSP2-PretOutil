<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Période saisie dans le panneau « Réserver cet objet » de l'écran E4 (US4).
 *
 * Le formulaire contrôle la saisie (dates présentes, RG1) pour répondre tout de suite ;
 * ReservationService reste la référence et revérifie toutes les règles (RG1 à RG5).
 */
final class ReservationDto
{
    #[Assert\NotNull(message: 'Indiquez la date de début.')]
    #[Assert\GreaterThanOrEqual('today', message: 'La réservation ne peut pas commencer dans le passé.')]
    public ?\DateTimeImmutable $debut = null;

    #[Assert\NotNull(message: 'Indiquez la date de fin.')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'debut', message: 'La date de fin doit être après la date de début.')]
    public ?\DateTimeImmutable $fin = null;
}
