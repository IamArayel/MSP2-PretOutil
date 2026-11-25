<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Enum\EtatObjet;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    /**
     * Nouvelle photo (#106), facultative. Type vérifié sur le contenu du fichier, pas sur son extension ;
     * nombre de pixels plafonné pour qu'une image piégée ne sature pas la mémoire au décodage.
     */
    #[Assert\Image(
        maxSize: '8M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        minWidth: 640,
        minHeight: 480,
        maxPixels: 40_000_000,
        maxSizeMessage: 'La photo ne doit pas dépasser {{ limit }} {{ suffix }}.',
        mimeTypesMessage: 'La photo doit être au format JPEG, PNG ou WebP.',
        minWidthMessage: 'La photo doit mesurer au moins {{ min_width }} pixels de large.',
        minHeightMessage: 'La photo doit mesurer au moins {{ min_height }} pixels de haut.',
        maxPixelsMessage: 'La photo est trop grande (40 millions de pixels au plus).',
        uploadIniSizeErrorMessage: 'La photo ne doit pas dépasser {{ limit }} {{ suffix }}.',
        uploadErrorMessage: 'La photo n\'a pas pu être envoyée. Merci de réessayer.',
    )]
    public ?UploadedFile $photo = null;

    /** Retirer la photo actuelle (modification seulement) ; ignoré si une nouvelle photo est envoyée. */
    public bool $supprimerPhoto = false;

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
