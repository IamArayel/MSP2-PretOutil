<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * RG9 : un compte désactivé ne peut plus se connecter.
 *
 * Le contrôle est fait après la vérification du mot de passe (checkPostAuth) :
 * sans le bon mot de passe, on ne révèle pas qu'un compte désactivé existe
 * pour cet email, le message reste le message générique d'échec.
 */
final class UtilisateurChecker implements UserCheckerInterface
{
    public const MESSAGE_COMPTE_DESACTIVE = 'Ce compte est désactivé. Contactez un administrateur de l\'association.';

    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        if (!$user->isActif()) {
            throw new CustomUserMessageAccountStatusException(self::MESSAGE_COMPTE_DESACTIVE);
        }
    }
}
