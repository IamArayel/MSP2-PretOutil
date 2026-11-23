<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\InscriptionDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de l'écran E2. La protection CSRF est activée par défaut
 * sur les formulaires Symfony (champ caché _token).
 */
final class InscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['autocomplete' => 'given-name', 'maxlength' => 100],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['autocomplete' => 'family-name', 'maxlength' => 100],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['autocomplete' => 'email', 'maxlength' => 180, 'placeholder' => 'camille.martin@exemple.fr'],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone (facultatif)',
                'required' => false,
                'attr' => ['autocomplete' => 'tel', 'maxlength' => 20, 'placeholder' => '06 12 34 56 78'],
            ])
            ->add('motDePasse', PasswordType::class, [
                'label' => 'Mot de passe',
                'help' => '12 caractères minimum.',
                'attr' => ['autocomplete' => 'new-password', 'minlength' => 12],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InscriptionDto::class,
            'csrf_token_id' => 'inscription',
            'csrf_message' => 'La page a expiré. Merci de renvoyer le formulaire.',
        ]);
    }
}
