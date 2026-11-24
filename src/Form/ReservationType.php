<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\ReservationDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de réservation de l'écran E4 (US4). Jeton CSRF par défaut.
 */
final class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $aujourdhui = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $builder
            ->add('debut', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                // Aide à la saisie seulement : la validation se fait côté serveur.
                'attr' => ['min' => $aujourdhui],
            ])
            ->add('fin', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['min' => $aujourdhui],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationDto::class,
            'csrf_token_id' => 'reservation',
            'csrf_message' => 'La page a expiré. Merci de renvoyer le formulaire.',
        ]);
    }
}
