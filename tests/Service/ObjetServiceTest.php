<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Objet;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Service\AuditLogger;
use App\Service\ObjetService;
use App\Service\StockagePhotoObjet;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Remise au catalogue d'un objet désactivé (#111), sans base de données : écriture et journalisation.
 */
final class ObjetServiceTest extends TestCase
{
    public function testReactiverRemetAuCatalogueEtJournalise(): void
    {
        $gestionnaire = new Utilisateur();
        $objet = $this->objet(12, 'Tronçonneuse')->setActif(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $journal = $this->createMock(AuditLogger::class);
        $journal->expects(self::once())->method('journaliser')
            ->with(ActionAudit::ObjetReactive, $gestionnaire, 'objet', 12, ['nom' => 'Tronçonneuse']);

        $this->service($entityManager, $journal)->reactiver($objet, $gestionnaire);

        self::assertTrue($objet->isActif());
    }

    public function testObjetDejaActifSansEcritureNiJournal(): void
    {
        $objet = $this->objet(12, 'Tronçonneuse');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $journal = $this->createMock(AuditLogger::class);
        $journal->expects(self::never())->method('journaliser');

        $this->service($entityManager, $journal)->reactiver($objet, new Utilisateur());

        self::assertTrue($objet->isActif());
    }

    private function service(EntityManagerInterface $entityManager, AuditLogger $journal): ObjetService
    {
        return new ObjetService($entityManager, $journal, $this->createStub(StockagePhotoObjet::class));
    }

    private function objet(int $id, string $nom): Objet
    {
        $objet = (new Objet())->setNom($nom)->setDescription('Objet de test');
        (new \ReflectionProperty(Objet::class, 'id'))->setValue($objet, $id);

        return $objet;
    }
}
