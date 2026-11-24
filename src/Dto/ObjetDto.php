<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Enum\EtatObjet;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données saisies sur l'écran E7 pour ajouter ou modifier un objet (US6).
 * Contraintes reprises du dictionnaire de données (table objet).
 */
final class ObjetDto
{
    #[Assert\NotNull(message: 'Choisissez une catégorie.')]
    public ?Categorie $categorie = null;

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 120, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $nom = null;

    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(max: 2000, maxMessage: 'La description ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $description = null;

    #[Assert\Length(max: 5000, maxMessage: 'Les caractéristiques ne doivent pas dépasser {{ limit }} caractères.')]
    public ?string $caracteristiques = null;

    #[Assert\NotNull(message: 'Choisissez un état.')]
    public ?EtatObjet $etat = EtatObjet::Bon;

    /** Pré-remplit le formulaire de modification. */
    public static function depuisObjet(Objet $objet): self
    {
        $dto = new self();
        $dto->categorie = $objet->getCategorie();
        $dto->nom = $objet->getNom();
        $dto->description = $objet->getDescription();
        $dto->caracteristiques = $objet->getCaracteristiques();
        $dto->etat = $objet->getEtat();

        return $dto;
    }
}
