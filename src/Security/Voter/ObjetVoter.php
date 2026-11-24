<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Objet;
use App\Repository\ReservationRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur le catalogue (US6, écran E7).
 *
 * - GERER : ajouter, modifier ou désactiver un objet. Sujet facultatif : sans objet,
 *   il protège la liste et la création.
 * - SUPPRIMER : suppression physique, seulement pour un objet jamais réservé ;
 *   sinon l'objet est désactivé (suppression logique).
 *
 * @extends Voter<self::GERER|self::SUPPRIMER, Objet|null>
 */
final class ObjetVoter extends Voter
{
    public const string GERER = 'OBJET_GERER';
    public const string SUPPRIMER = 'OBJET_SUPPRIMER';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::GERER => null === $subject || $subject instanceof Objet,
            self::SUPPRIMER => $subject instanceof Objet,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // Passe par role_hierarchy : un administrateur est aussi gestionnaire.
        if (!$this->accessDecisionManager->decide($token, ['ROLE_MANAGER'])) {
            return false;
        }

        return match ($attribute) {
            self::GERER => true,
            // La base refuserait de toute façon (ON DELETE RESTRICT) : le Voter évite
            // de proposer une action vouée à l'échec.
            self::SUPPRIMER => !$this->reservationRepository->existePourObjet($subject),
            default => false,
        };
    }
}
