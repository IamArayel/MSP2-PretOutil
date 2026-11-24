<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\StatutReservation;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une réservation.
 *
 * - VOIR : l'adhérent qui a réservé, ou un gestionnaire (écrans E5 et E6).
 * - ANNULER : l'adhérent qui a réservé, si la réservation est confirmée et au plus
 *   tard la veille du premier jour (RG6). Utilisé à J6.
 *
 * Données personnelles (O4) : un adhérent ne voit jamais la réservation d'un autre.
 *
 * @extends Voter<self::VOIR|self::ANNULER, Reservation>
 */
final class ReservationVoter extends Voter
{
    public const string VOIR = 'RESERVATION_VOIR';
    public const string ANNULER = 'RESERVATION_ANNULER';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        // Horloge injectée : « aujourd'hui » est fixé dans les tests (fuseau Indian/Reunion en dev).
        private readonly ClockInterface $clock,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VOIR, self::ANNULER], true)
            && $subject instanceof Reservation;
    }

    /**
     * @param Reservation $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $utilisateur = $token->getUser();

        if (!$utilisateur instanceof Utilisateur) {
            return false;
        }

        // Comparaison par identifiant : l'utilisateur de la session et celui chargé
        // avec la réservation peuvent être deux instances PHP différentes.
        $estProprietaire = $subject->getUtilisateur()->getId() === $utilisateur->getId();

        return match ($attribute) {
            self::VOIR => $estProprietaire || $this->accessDecisionManager->decide($token, ['ROLE_MANAGER']),
            self::ANNULER => $estProprietaire && $this->estAnnulable($subject),
            default => false,
        };
    }

    /** RG6 : confirmée, et le premier jour est strictement après aujourd'hui. */
    private function estAnnulable(Reservation $reservation): bool
    {
        if (StatutReservation::Confirmee !== $reservation->getStatut()) {
            return false;
        }

        // Comparaison de jours calendaires (AAAA-MM-JJ) : date_debut est une DATE sans heure,
        // la convertir de fuseau pourrait la décaler d'un jour.
        $aujourdhui = $this->clock->now()->format('Y-m-d');
        $premierJour = $reservation->getDateDebut()->format('Y-m-d');

        return $aujourdhui < $premierJour;
    }
}
