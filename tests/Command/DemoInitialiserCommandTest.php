<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Demo\JeuDEssai;
use App\Entity\Utilisateur;
use App\Service\StockagePhotoObjet;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Commande app:demo:initialiser (ticket #115) : données de démonstration en production, avec
 * un mot de passe secret, jamais par-dessus des données existantes.
 *
 * Chaque test s'exécute dans une transaction annulée à la fin : la base de test reste vide.
 */
final class DemoInitialiserCommandTest extends KernelTestCase
{
    private const string MOT_DE_PASSE_SECRET = 'Demo-Secret-Essai-2026';

    private ?Connection $connexion = null;

    protected function tearDown(): void
    {
        while ($this->connexion?->isTransactionActive()) {
            $this->connexion->rollBack();
        }
        // Photos écrites par la commande dans le dossier de test (var/test/uploads/objets)
        if (null !== $this->connexion) {
            self::getContainer()->get(StockagePhotoObjet::class)->vider();
        }
        unset($_ENV['DEMO_MOT_DE_PASSE'], $_SERVER['DEMO_MOT_DE_PASSE']);

        parent::tearDown();
    }

    public function testChargeLaDemonstrationAvecLeMotDePasseSecret(): void
    {
        $commande = $this->commande(self::MOT_DE_PASSE_SECRET);

        self::assertSame(Command::SUCCESS, $commande->execute([]));
        self::assertStringContainsString('6 catégories, 5 comptes, 24 objets (14 photos), 11 réservations', preg_replace('/\s+/', ' ', $commande->getDisplay()));

        $compter = fn (string $table, string $colonne = '*'): int => (int) $this->connexion->fetchOne(\sprintf('SELECT count(%s) FROM %s', $colonne, $table));
        self::assertSame(6, $compter('categorie'));
        self::assertSame(5, $compter('utilisateur'));
        self::assertSame(24, $compter('objet'));
        self::assertSame(14, $compter('objet', 'image'), 'Photos enregistrées');
        self::assertSame(11, $compter('reservation'));

        $admin = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Utilisateur::class)->findOneBy(['email' => 'admin@pretoutil.test']);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($admin, self::MOT_DE_PASSE_SECRET), 'Mot de passe secret accepté');
        self::assertFalse($hasher->isPasswordValid($admin, JeuDEssai::MOT_DE_PASSE_TEST), 'Mot de passe public du dépôt refusé');
    }

    /** Une base qui contient déjà un compte n'est jamais complétée : pas de mélange avec de vraies données. */
    public function testRefuseUneBaseNonVide(): void
    {
        $commande = $this->commande(self::MOT_DE_PASSE_SECRET);
        $this->connexion->insert('utilisateur', [
            'email' => 'vraie.personne@exemple.fr', 'nom' => 'Personne', 'prenom' => 'Vraie',
            'mot_de_passe' => 'empreinte', 'roles' => '[]', 'actif' => 'true', 'cree_le' => '2026-11-26 10:00:00',
        ]);

        self::assertSame(Command::FAILURE, $commande->execute([]));
        self::assertStringContainsString('contient déjà 1 compte(s)', $commande->getDisplay());
        self::assertSame(0, (int) $this->connexion->fetchOne('SELECT count(*) FROM objet'), 'Rien n\'est écrit');
    }

    /** @return iterable<string, array{?string, string}> */
    public static function motsDePasseRefuses(): iterable
    {
        yield 'absent' => [null, 'doit être défini'];
        yield 'trop court' => ['court', 'au moins 12 caractères'];
        yield 'mot de passe public du dépôt' => [JeuDEssai::MOT_DE_PASSE_TEST, 'public dans le dépôt'];
    }

    #[DataProvider('motsDePasseRefuses')]
    public function testRefuseUnMotDePasseAbsentFaibleOuPublic(?string $motDePasse, string $message): void
    {
        $commande = $this->commande($motDePasse);

        self::assertSame(Command::FAILURE, $commande->execute([]));
        self::assertStringContainsString($message, preg_replace('/\s+/', ' ', $commande->getDisplay()));
        self::assertSame(0, (int) $this->connexion->fetchOne('SELECT count(*) FROM utilisateur'), 'Rien n\'est écrit');
    }

    /** Démarre le noyau avec DEMO_MOT_DE_PASSE (ou sans), ouvre une transaction et renvoie la commande. */
    private function commande(?string $motDePasse): CommandTester
    {
        if (null !== $motDePasse) {
            $_ENV['DEMO_MOT_DE_PASSE'] = $_SERVER['DEMO_MOT_DE_PASSE'] = $motDePasse;
        }
        $noyau = self::bootKernel();
        $this->connexion = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $this->connexion->beginTransaction();

        return new CommandTester((new Application($noyau))->find('app:demo:initialiser'));
    }
}
