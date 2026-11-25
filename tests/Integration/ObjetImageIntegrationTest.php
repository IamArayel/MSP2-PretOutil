<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Contrainte ck_objet_image sur la vraie base de test : seule une photo de assets/images/objets/
 * peut être enregistrée, jamais un chemin (le nom est concaténé au chemin du dossier par asset()).
 * Chaque test s'exécute dans une transaction annulée à la fin.
 */
final class ObjetImageIntegrationTest extends KernelTestCase
{
    private const string VIOLATION_CHECK = '23514';

    private Connection $connexion;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connexion = self::getContainer()->get(Connection::class);
        $this->connexion->beginTransaction();
        $this->connexion->executeStatement("INSERT INTO categorie (libelle) VALUES ('Photos (test)')");
        $this->connexion->executeStatement("INSERT INTO objet (categorie_id, nom, description) SELECT id, 'Objet photo', 'Test' FROM categorie WHERE libelle = 'Photos (test)'");
    }

    protected function tearDown(): void
    {
        $this->connexion->rollBack();
        parent::tearDown();
    }

    public function testNomDePhotoAccepte(): void
    {
        self::assertSame(1, $this->enregistrer('perceuse-visseuse.webp'));
    }

    /** @return iterable<string, array{string}> */
    public static function nomsRefuses(): iterable
    {
        yield 'remontée de dossier' => ['../../.env'];
        yield 'chemin absolu' => ['/etc/passwd.webp'];
        yield 'autre format' => ['perceuse.php'];
        yield 'majuscules et espaces' => ['Ma Perceuse.webp'];
    }

    #[DataProvider('nomsRefuses')]
    public function testNomDePhotoRefuse(string $nom): void
    {
        try {
            $this->enregistrer($nom);
            self::fail(\sprintf('La contrainte ck_objet_image aurait dû refuser « %s »', $nom));
        } catch (DriverException $e) {
            self::assertSame(self::VIOLATION_CHECK, $e->getSQLState());
        }
    }

    private function enregistrer(string $nom): int
    {
        return (int) $this->connexion->executeStatement("UPDATE objet SET image = ? WHERE nom = 'Objet photo'", [$nom]);
    }
}
