<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\EmailDisponible;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données saisies sur l'écran E2 Inscription (US1).
 * Le formulaire remplit ce DTO, validé avant toute création de compte :
 * l'entité Utilisateur n'est jamais dans un état invalide.
 */
final class InscriptionDto
{
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le prénom ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $prenom = null;

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $nom = null;

    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Cet email n\'est pas valide.', mode: Assert\Email::VALIDATION_MODE_HTML5)]
    #[Assert\Length(max: 180, maxMessage: 'L\'email ne doit pas dépasser {{ limit }} caractères.')]
    #[EmailDisponible]
    public ?string $email = null;

    #[Assert\Length(max: 20, maxMessage: 'Le téléphone ne doit pas dépasser {{ limit }} caractères.')]
    #[Assert\Regex(
        pattern: '/^\+?[0-9][0-9 .-]{8,18}[0-9]$/',
        message: 'Le téléphone ne doit contenir que des chiffres, espaces, points ou tirets (ex. 06 12 34 56 78).',
    )]
    public ?string $telephone = null;

    /** Au moins 12 caractères (US1) ; 4096 au plus, limite du hasher Symfony. */
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Assert\Length(
        min: 12,
        max: 4096,
        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
    )]
    public ?string $motDePasse = null;

    /** Acceptation des conditions générales d'utilisation (#106) : obligatoire, non enregistrée. */
    #[Assert\IsTrue(message: 'Vous devez accepter les conditions générales d\'utilisation pour créer un compte.')]
    public bool $accepteCgu = false;
}
