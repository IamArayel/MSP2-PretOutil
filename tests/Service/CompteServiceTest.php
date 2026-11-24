<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Enum\RoleCompte;
use App\Exception\ModificationCompteImpossibleException;
use App\Service\AuditLogger;
use App\Service\CompteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CompteService (US9, écran E8) sans base de données : règles et journalisation.
 */
final class CompteServiceTest extends TestCase
{
    public function testChangerRoleEnregistreEtJournaliseAncienEtNouveauRole(): void
    {
        $administrateur = $this->compte(1, 'admin@pretoutil.test', ['ROLE_ADMIN']);
        $compte = $this->compte(7, 'camille@pretoutil.test', []);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $journal = $this->createMock(AuditLogger::class);
        $journal->expects(self::once())->method('journaliser')->with(
            ActionAudit::RoleModifie,
            $administrateur,
            'utilisateur',
            7,
            ['email' => 'camille@pretoutil.test', 'ancien_role' => 'adherent', 'nouveau_role' => 'gestionnaire'],
        );

        (new CompteService($entityManager, $journal))->changerRole($compte, RoleCompte::Gestionnaire, $administrateur);

        self::assertSame(RoleCompte::Gestionnaire, RoleCompte::de($compte));
        self::assertContains('ROLE_MANAGER', $compte->getRoles());
    }

    public function testMemeRoleSansEffet(): void
    {
        $compte = $this->compte(7, 'camille@pretoutil.test', ['ROLE_MANAGER']);

        $this->serviceSansEcriture()->changerRole($compte, RoleCompte::Gestionnaire, $this->compte(1, 'admin@pretoutil.test', ['ROLE_ADMIN']));

        self::assertSame(RoleCompte::Gestionnaire, RoleCompte::de($compte));
    }

    public function testDesactiverPuisReactiverJournalises(): void
    {
        $administrateur = $this->compte(1, 'admin@pretoutil.test', ['ROLE_ADMIN']);
        $compte = $this->compte(7, 'camille@pretoutil.test', []);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');
        $actions = [];
        $journal = $this->createMock(AuditLogger::class);
        $journal->expects(self::exactly(2))->method('journaliser')
            ->willReturnCallback(static function (ActionAudit $action, $acteur, string $type, ?int $id, array $details) use (&$actions): void {
                $actions[] = [$action, $id, $details];
            });
        $service = new CompteService($entityManager, $journal);

        $service->desactiver($compte, $administrateur);
        self::assertFalse($compte->isActif());

        $service->reactiver($compte, $administrateur);
        self::assertTrue($compte->isActif());

        self::assertSame([
            [ActionAudit::CompteDesactive, 7, ['email' => 'camille@pretoutil.test']],
            [ActionAudit::CompteReactive, 7, ['email' => 'camille@pretoutil.test']],
        ], $actions);
    }

    public function testCompteDejaDansLEtatDemandeSansEffet(): void
    {
        $administrateur = $this->compte(1, 'admin@pretoutil.test', ['ROLE_ADMIN']);
        $desactive = $this->compte(7, 'camille@pretoutil.test', [])->setActif(false);
        $actif = $this->compte(8, 'noah@pretoutil.test', []);

        $service = $this->serviceSansEcriture();
        $service->desactiver($desactive, $administrateur);
        $service->reactiver($actif, $administrateur);

        self::assertFalse($desactive->isActif());
        self::assertTrue($actif->isActif());
    }

    /** @return iterable<string, array{callable(CompteService, Utilisateur): void, string}> */
    public static function actionsSurSoiMeme(): iterable
    {
        yield 'se désactiver' => [
            static fn (CompteService $service, Utilisateur $admin) => $service->desactiver($admin, $admin),
            'Vous ne pouvez pas désactiver votre propre compte.',
        ];
        yield 'se rétrograder adhérent' => [
            static fn (CompteService $service, Utilisateur $admin) => $service->changerRole($admin, RoleCompte::Adherent, $admin),
            'Vous ne pouvez pas changer votre propre rôle.',
        ];
        yield 'se rétrograder gestionnaire' => [
            static fn (CompteService $service, Utilisateur $admin) => $service->changerRole($admin, RoleCompte::Gestionnaire, $admin),
            'Vous ne pouvez pas changer votre propre rôle.',
        ];
    }

    /** L'association garde toujours un administrateur : il ne peut pas agir sur son propre compte. */
    #[DataProvider('actionsSurSoiMeme')]
    public function testAdministrateurNAgitPasSurSonPropreCompte(callable $action, string $message): void
    {
        $administrateur = $this->compte(1, 'admin@pretoutil.test', ['ROLE_ADMIN']);

        try {
            $action($this->serviceSansEcriture(), $administrateur);
            self::fail('ModificationCompteImpossibleException attendue');
        } catch (ModificationCompteImpossibleException $e) {
            self::assertSame($message, $e->getMessage());
        }

        self::assertTrue($administrateur->isActif());
        self::assertSame(RoleCompte::Administrateur, RoleCompte::de($administrateur));
    }

    /** Un administrateur peut agir sur un autre administrateur : il en reste toujours un, lui. */
    public function testAdministrateurPeutDesactiverUnAutreAdministrateur(): void
    {
        $autre = $this->compte(2, 'admin2@pretoutil.test', ['ROLE_ADMIN']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        (new CompteService($entityManager, $this->createStub(AuditLogger::class)))
            ->desactiver($autre, $this->compte(1, 'admin@pretoutil.test', ['ROLE_ADMIN']));

        self::assertFalse($autre->isActif());
    }

    /** Refus ou action sans effet : ni écriture en base ni entrée de journal. */
    private function serviceSansEcriture(): CompteService
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $journal = $this->createMock(AuditLogger::class);
        $journal->expects(self::never())->method('journaliser');

        return new CompteService($entityManager, $journal);
    }

    /** @param list<string> $roles */
    private function compte(int $id, string $email, array $roles): Utilisateur
    {
        $compte = (new Utilisateur())->setEmail($email)->setNom('Testeur')->setPrenom('Compte')->setRoles($roles);
        (new \ReflectionProperty(Utilisateur::class, 'id'))->setValue($compte, $id);

        return $compte;
    }
}
