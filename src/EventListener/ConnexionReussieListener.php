<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Dernière activité d'un compte : connexion réussie (ici) ou réservation (ReservationService).
 *
 * Elle est affichée sur l'écran E8 et fait partir le délai de conservation des données
 * personnelles (RGPD, dictionnaire de données) : sans elle, un adhérent qui se connecte
 * sans réserver serait considéré comme inactif.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
final class ConnexionReussieListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $horloge,
    ) {
    }

    public function __invoke(LoginSuccessEvent $evenement): void
    {
        $utilisateur = $evenement->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return;
        }

        $utilisateur->setDerniereActiviteLe($this->horloge->now());
        $this->entityManager->flush();
    }
}
