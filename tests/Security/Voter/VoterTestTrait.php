<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * Outils communs aux tests des Voters : utilisateurs de chaque rôle, jetons
 * et gestionnaire de décision avec la même hiérarchie que config/packages/security.yaml.
 */
trait VoterTestTrait
{
    private function decisionManager(): AccessDecisionManagerInterface
    {
        $hierarchie = new RoleHierarchy([
            'ROLE_MANAGER' => ['ROLE_USER'],
            'ROLE_ADMIN' => ['ROLE_MANAGER'],
        ]);

        return new AccessDecisionManager([new RoleHierarchyVoter($hierarchie)]);
    }

    /** @param list<string> $roles */
    private function utilisateur(int $id, array $roles = []): Utilisateur
    {
        $utilisateur = (new Utilisateur())
            ->setEmail(\sprintf('utilisateur%d@pretoutil.test', $id))
            ->setNom('Test')
            ->setPrenom('Utilisateur')
            ->setRoles($roles);

        // L'identifiant est attribué par la base : on le fixe pour simuler une entité chargée.
        (new \ReflectionProperty(Utilisateur::class, 'id'))->setValue($utilisateur, $id);

        return $utilisateur;
    }

    private function jeton(?Utilisateur $utilisateur): TokenInterface
    {
        if (null === $utilisateur) {
            return new NullToken();
        }

        return new UsernamePasswordToken($utilisateur, 'main', $utilisateur->getRoles());
    }
}
