<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Objet;
use App\Repository\ReservationRepository;
use App\Security\Voter\ObjetVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * US6 : seul un gestionnaire (ou un administrateur) gère le catalogue ;
 * un objet déjà réservé n'est jamais supprimé physiquement.
 */
final class ObjetVoterTest extends TestCase
{
    use VoterTestTrait;

    private const int ACCORDE = VoterInterface::ACCESS_GRANTED;
    private const int REFUSE = VoterInterface::ACCESS_DENIED;

    /** @return iterable<string, array{list<string>|null, int}> */
    public static function rolesPourGerer(): iterable
    {
        yield 'visiteur' => [null, self::REFUSE];
        yield 'adhérent' => [[], self::REFUSE];
        yield 'gestionnaire' => [['ROLE_MANAGER'], self::ACCORDE];
        yield 'administrateur (hiérarchie)' => [['ROLE_ADMIN'], self::ACCORDE];
    }

    /** @param list<string>|null $roles null pour un visiteur non connecté */
    #[DataProvider('rolesPourGerer')]
    public function testGererUnObjet(?array $roles, int $attendu): void
    {
        $voter = $this->voter(objetDejaReserve: false);

        self::assertSame($attendu, $voter->vote($this->jetonPour($roles), new Objet(), [ObjetVoter::GERER]));
    }

    /** @param list<string>|null $roles */
    #[DataProvider('rolesPourGerer')]
    public function testGererLeCatalogueSansObjet(?array $roles, int $attendu): void
    {
        $voter = $this->voter(objetDejaReserve: false);

        self::assertSame($attendu, $voter->vote($this->jetonPour($roles), null, [ObjetVoter::GERER]));
    }

    /** @return iterable<string, array{list<string>|null, bool, int}> */
    public static function casDeSuppression(): iterable
    {
        yield 'gestionnaire, objet jamais réservé' => [['ROLE_MANAGER'], false, self::ACCORDE];
        yield 'administrateur, objet jamais réservé' => [['ROLE_ADMIN'], false, self::ACCORDE];
        yield 'gestionnaire, objet déjà réservé' => [['ROLE_MANAGER'], true, self::REFUSE];
        yield 'adhérent, objet jamais réservé' => [[], false, self::REFUSE];
        yield 'visiteur, objet jamais réservé' => [null, false, self::REFUSE];
    }

    /** @param list<string>|null $roles */
    #[DataProvider('casDeSuppression')]
    public function testSupprimer(?array $roles, bool $objetDejaReserve, int $attendu): void
    {
        $voter = $this->voter($objetDejaReserve);

        self::assertSame($attendu, $voter->vote($this->jetonPour($roles), new Objet(), [ObjetVoter::SUPPRIMER]));
    }

    public function testSupprimerExigeUnObjet(): void
    {
        $voter = $this->voter(objetDejaReserve: false);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->jetonPour(['ROLE_MANAGER']), null, [ObjetVoter::SUPPRIMER]));
    }

    public function testAttributInconnuIgnore(): void
    {
        $voter = $this->voter(objetDejaReserve: false);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->jetonPour(['ROLE_ADMIN']), new Objet(), ['OBJET_INCONNU']));
    }

    private function voter(bool $objetDejaReserve): ObjetVoter
    {
        $reservationRepository = $this->createStub(ReservationRepository::class);
        $reservationRepository->method('existePourObjet')->willReturn($objetDejaReserve);

        return new ObjetVoter($this->decisionManager(), $reservationRepository);
    }

    /** @param list<string>|null $roles */
    private function jetonPour(?array $roles): \Symfony\Component\Security\Core\Authentication\Token\TokenInterface
    {
        return $this->jeton(null === $roles ? null : $this->utilisateur(1, $roles));
    }
}
