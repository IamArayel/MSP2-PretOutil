<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Écran E1 (US2). La vérification des identifiants est faite par le pare-feu
 * (form_login) : ce contrôleur affiche seulement le formulaire et l'erreur.
 */
final class ConnexionController extends AbstractController
{
    public const MESSAGE_ECHEC = 'Email ou mot de passe incorrect.';

    #[Route('/connexion', name: 'app_connexion', methods: ['GET', 'POST'])]
    public function connexion(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

        return $this->render('connexion/index.html.twig', [
            'dernier_email' => $authenticationUtils->getLastUsername(),
            'erreur' => $this->messageErreur($authenticationUtils->getLastAuthenticationError()),
        ]);
    }

    /** Intercepté par le pare-feu (logout) : ce code n'est jamais exécuté. */
    #[Route('/deconnexion', name: 'app_deconnexion', methods: ['GET'])]
    public function deconnexion(): never
    {
        throw new \LogicException('La déconnexion est gérée par le pare-feu.');
    }

    /**
     * Message affiché à l'utilisateur. Hors blocage et compte désactivé, il reste
     * générique : il ne dit pas si l'email existe ni lequel des deux champs est faux.
     */
    private function messageErreur(?AuthenticationException $erreur): ?string
    {
        return match (true) {
            null === $erreur => null,
            $erreur instanceof TooManyLoginAttemptsAuthenticationException => sprintf(
                'Trop de tentatives de connexion. Réessayez dans %d minutes.',
                $erreur->getMessageData()['%minutes%'] ?? 15,
            ),
            $erreur instanceof CustomUserMessageAccountStatusException => $erreur->getMessageKey(),
            $erreur instanceof InvalidCsrfTokenException => 'La page a expiré. Merci de réessayer.',
            default => self::MESSAGE_ECHEC,
        };
    }
}
