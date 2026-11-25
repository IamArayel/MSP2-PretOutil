<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\CriteresCatalogue;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champ « Rechercher un objet » des écrans E3 et E7 (#109) : recherche sur le nom,
 * lancée à la volée dès 3 lettres par assets/recherche.js, ou avec le bouton Rechercher.
 */
final class ChampRechercheType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'Rechercher un objet',
            'required' => false,
            'constraints' => [new Assert\Length(
                max: CriteresCatalogue::LONGUEUR_MAX_RECHERCHE,
                maxMessage: 'La recherche ne peut pas dépasser {{ limit }} caractères.',
            )],
            'attr' => [
                'maxlength' => CriteresCatalogue::LONGUEUR_MAX_RECHERCHE,
                'autocomplete' => 'off',
                'data-recherche-champ' => '',
            ],
        ]);
    }

    public function getParent(): string
    {
        return SearchType::class;
    }
}
