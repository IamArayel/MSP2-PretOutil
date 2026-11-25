<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\CriteresCatalogue;
use App\Entity\Categorie;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Filtres de l'écran E3, envoyés en GET : l'URL d'une recherche peut être partagée
 * ou mise en favori, et les filtres se retrouvent dans les liens de pagination.
 * Pas de jeton CSRF : une recherche ne modifie aucune donnée.
 */
final class FiltreCatalogueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Paramètre court dans l'URL (?q=perceuse), rangé dans CriteresCatalogue::$recherche (#109).
            ->add('q', ChampRechercheType::class, ['property_path' => 'recherche'])
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => 'Toutes les catégories',
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('c')->orderBy('c.libelle', 'ASC'),
                'invalid_message' => 'Cette catégorie n\'existe pas.',
            ])
            ->add('debut', DateType::class, [
                'label' => 'Disponible du',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'invalid_message' => 'Cette date n\'est pas valide.',
            ])
            ->add('fin', DateType::class, [
                'label' => 'jusqu\'au',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'invalid_message' => 'Cette date n\'est pas valide.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CriteresCatalogue::class,
            'method' => 'GET',
            'csrf_protection' => false,
            // Le numéro de page voyage dans la même URL que les filtres.
            'allow_extra_fields' => true,
        ]);
    }

    /** Pas de préfixe : les paramètres de l'URL restent lisibles (?categorie=1&debut=...). */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
