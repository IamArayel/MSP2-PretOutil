<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\ObjetDto;
use App\Entity\Categorie;
use App\Enum\EtatObjet;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'ajout et de modification d'un objet (écran E7). Jeton CSRF par défaut.
 */
final class ObjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('categorie', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Categorie::class,
                'choice_label' => 'libelle',
                'placeholder' => 'Choisir une catégorie',
                'query_builder' => static fn (EntityRepository $er) => $er->createQueryBuilder('c')->orderBy('c.libelle', 'ASC'),
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['maxlength' => 120],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'help' => 'Texte court affiché sur la carte du catalogue.',
                'attr' => ['rows' => 3, 'maxlength' => 2000],
            ])
            ->add('caracteristiques', TextareaType::class, [
                'label' => 'Caractéristiques (facultatif)',
                'help' => 'Détails affichés sur la fiche de l\'objet.',
                'required' => false,
                'attr' => ['rows' => 5, 'maxlength' => 5000],
            ])
            ->add('etat', EnumType::class, [
                'label' => 'État',
                'class' => EtatObjet::class,
                'choice_label' => static fn (EtatObjet $etat): string => $etat->libelle(),
                'help' => 'Un objet hors service n\'est plus réservable (RG5).',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ObjetDto::class,
            'csrf_token_id' => 'objet',
            'csrf_message' => 'La page a expiré. Merci de renvoyer le formulaire.',
        ]);
    }
}
