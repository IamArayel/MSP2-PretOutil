<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Document\AuditLog;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Service\AuditLogger;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

/**
 * AuditLogger sans MongoDB : construction du document, et résistance à une panne
 * (RG8 : l'action métier, déjà enregistrée dans PostgreSQL, n'est pas annulée).
 */
final class AuditLoggerTest extends TestCase
{
    public function testDocumentConstruitDepuisLAction(): void
    {
        $ecrit = null;
        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())->method('persist')
            ->willReturnCallback(static function (object $document) use (&$ecrit): void {
                $ecrit = $document;
            });
        $documentManager->expects(self::once())->method('flush');
        $documentManager->expects(self::once())->method('detach');

        $acteur = (new Utilisateur())->setEmail('gestionnaire@pretoutil.test')->setNom('Bernard')->setPrenom('Léa');
        (new \ReflectionProperty(Utilisateur::class, 'id'))->setValue($acteur, 3);

        $logger = new AuditLogger($documentManager, new MockClock('2026-12-10 15:00:00'), $this->createStub(LoggerInterface::class));
        $logger->journaliser(ActionAudit::RetourEnregistre, $acteur, 'reservation', 42, ['objet' => 'Tente 4 places']);

        self::assertInstanceOf(AuditLog::class, $ecrit);
        self::assertSame(ActionAudit::RetourEnregistre, $ecrit->getAction());
        self::assertSame(3, $ecrit->getActeur()->getId());
        self::assertSame('gestionnaire@pretoutil.test', $ecrit->getActeur()->getEmail());
        self::assertSame('reservation', $ecrit->getCible()->getType());
        self::assertSame(42, $ecrit->getCible()->getId());
        self::assertSame(['objet' => 'Tente 4 places'], $ecrit->getDetails());
        self::assertSame('2026-12-10 15:00:00', $ecrit->getHorodatage()->format('Y-m-d H:i:s'));
    }

    public function testMongoDbIndisponibleNeBloquePasLActionMetier(): void
    {
        $documentManager = $this->createStub(DocumentManager::class);
        $documentManager->method('flush')->willThrowException(new ConnectionTimeoutException('MongoDB ne répond pas'));

        $logs = $this->createMock(LoggerInterface::class);
        $logs->expects(self::once())->method('error')
            ->with(self::stringContains('Journal d\'audit'), self::callback(
                static fn (array $contexte): bool => 'OBJET_CREE' === $contexte['action'] && $contexte['exception'] instanceof ConnectionTimeoutException,
            ));

        $logger = new AuditLogger($documentManager, new MockClock(), $logs);

        // Aucune exception ne remonte au service métier.
        $logger->journaliser(ActionAudit::ObjetCree, null, 'objet', 7);
        $this->addToAssertionCount(1);
    }

    public function testConnexionEchoueeSansIdentifiant(): void
    {
        $ecrit = null;
        $documentManager = $this->createStub(DocumentManager::class);
        $documentManager->method('persist')->willReturnCallback(static function (object $document) use (&$ecrit): void {
            $ecrit = $document;
        });

        $logger = new AuditLogger($documentManager, new MockClock(), $this->createStub(LoggerInterface::class));
        $logger->journaliserAnonyme(ActionAudit::ConnexionEchouee, 'adherent@pretoutil.test', ['motif' => 'echec']);

        self::assertInstanceOf(AuditLog::class, $ecrit);
        self::assertNull($ecrit->getActeur()->getId(), 'Jamais d\'identifiant : le journal ne dit pas si le compte existe');
        self::assertSame('adherent@pretoutil.test', $ecrit->getActeur()->getEmail());
        self::assertSame('utilisateur', $ecrit->getCible()->getType());
        self::assertNull($ecrit->getCible()->getId());
    }
}
