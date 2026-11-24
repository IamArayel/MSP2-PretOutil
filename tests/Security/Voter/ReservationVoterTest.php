<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\StatutReservation;
use App\Security\Voter\ReservationVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * VOIR : l'adhérent propriétaire ou un gestionnaire (O4 : pas de données d'un autre adhérent).
 * ANNULER : le propriétaire, réservation confirmée, au plus tard la veille du début (RG6).
 */
final class ReservationVoterTest extends TestCase
{
    use VoterTestTrait;

    private const int ACCORDE = VoterInterface::ACCESS_GRANTED;
    private const int REFUSE = VoterInterface::ACCESS_DENIED;

    /** « Aujourd'hui » des tests, en fin de journée pour vérifier que seule la date compte. */
    private const string MAINTENANT = '2026-12-10 23:30:00';
    private const string FUSEAU = 'Indian/Reunion';

    private const int PROPRIETAIRE = 1;
    private const int AUTRE_ADHERENT = 2;
    private const int GESTIONNAIRE = 3;
    private const int ADMINISTRATEUR = 4;

    /** @return iterable<string, array{int|null, int}> */
    public static function casDeConsultation(): iterable
    {
        yield 'adhérent propriétaire' => [self::PROPRIETAIRE, self::ACCORDE];
        yield 'autre adhérent' => [self::AUTRE_ADHERENT, self::REFUSE];
        yield 'gestionnaire' => [self::GESTIONNAIRE, self::ACCORDE];
        yield 'administrateur (hiérarchie)' => [self::ADMINISTRATEUR, self::ACCORDE];
        yield 'visiteur' => [null, self::REFUSE];
    }

    #[DataProvider('casDeConsultation')]
    public function testVoir(?int $qui, int $attendu): void
    {
        $reservation = $this->reservation('2026-12-20', StatutReservation::Confirmee);

        self::assertSame($attendu, $this->voter()->vote($this->jeton($this->personne($qui)), $reservation, [ReservationVoter::VOIR]));
    }

    /** @return iterable<string, array{int|null, string, StatutReservation, int}> */
    public static function casDAnnulation(): iterable
    {
        // Qui annule
        yield 'propriétaire, début dans 10 jours' => [self::PROPRIETAIRE, '2026-12-20', StatutReservation::Confirmee, self::ACCORDE];
        yield 'autre adhérent' => [self::AUTRE_ADHERENT, '2026-12-20', StatutReservation::Confirmee, self::REFUSE];
        yield 'gestionnaire, non propriétaire' => [self::GESTIONNAIRE, '2026-12-20', StatutReservation::Confirmee, self::REFUSE];
        yield 'visiteur' => [null, '2026-12-20', StatutReservation::Confirmee, self::REFUSE];

        // RG6 : jusqu'à la veille du début
        yield 'propriétaire, la veille du début' => [self::PROPRIETAIRE, '2026-12-11', StatutReservation::Confirmee, self::ACCORDE];
        yield 'propriétaire, le jour du début' => [self::PROPRIETAIRE, '2026-12-10', StatutReservation::Confirmee, self::REFUSE];
        yield 'propriétaire, début passé' => [self::PROPRIETAIRE, '2026-12-01', StatutReservation::Confirmee, self::REFUSE];

        // Cycle de vie : seule une réservation confirmée s'annule
        yield 'propriétaire, réservation en cours' => [self::PROPRIETAIRE, '2026-12-20', StatutReservation::EnCours, self::REFUSE];
        yield 'propriétaire, réservation terminée' => [self::PROPRIETAIRE, '2026-12-20', StatutReservation::Terminee, self::REFUSE];
        yield 'propriétaire, déjà annulée' => [self::PROPRIETAIRE, '2026-12-20', StatutReservation::Annulee, self::REFUSE];
    }

    #[DataProvider('casDAnnulation')]
    public function testAnnuler(?int $qui, string $debut, StatutReservation $statut, int $attendu): void
    {
        $reservation = $this->reservation($debut, $statut);

        self::assertSame($attendu, $this->voter()->vote($this->jeton($this->personne($qui)), $reservation, [ReservationVoter::ANNULER]));
    }

    public function testSujetAutreQuUneReservationIgnore(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter()->vote($this->jeton($this->personne(self::ADMINISTRATEUR)), new Objet(), [ReservationVoter::VOIR]),
        );
    }

    private function voter(): ReservationVoter
    {
        $horloge = new MockClock(self::MAINTENANT, new \DateTimeZone(self::FUSEAU));

        return new ReservationVoter($this->decisionManager(), $horloge);
    }

    private function reservation(string $debut, StatutReservation $statut): Reservation
    {
        $dateDebut = new \DateTimeImmutable($debut, new \DateTimeZone(self::FUSEAU));
        $reservation = new Reservation($this->personne(self::PROPRIETAIRE), new Objet(), $dateDebut, $dateDebut->modify('+2 days'));

        return $reservation->setStatut($statut);
    }

    /**
     * Une nouvelle instance à chaque appel : le Voter doit comparer les identifiants,
     * pas les objets PHP.
     */
    private function personne(?int $qui): ?Utilisateur
    {
        return match ($qui) {
            null => null,
            self::GESTIONNAIRE => $this->utilisateur($qui, ['ROLE_MANAGER']),
            self::ADMINISTRATEUR => $this->utilisateur($qui, ['ROLE_ADMIN']),
            default => $this->utilisateur($qui),
        };
    }
}
