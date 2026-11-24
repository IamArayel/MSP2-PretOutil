<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Document\AuditLog;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Repository\AuditLogRepository;
use App\Service\AuditLogger;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\ServerException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Journal d'audit sur la vraie base MongoDB de test (pretoutil_test), avec le compte
 * applicatif pretoutil_app (rôle auditAppendOnly : find et insert seulement).
 *
 * Le compte ne peut rien supprimer : les documents de test restent dans pretoutil_test
 * (l'index TTL les efface au bout d'un an). Chaque test les retrouve par un marqueur unique.
 * Prérequis : docker/mongodb/initdb/03-base-de-test.js (README, section Tests).
 */
final class AuditLogIntegrationTest extends WebTestCase
{
    private const int VALIDATION_REFUSEE = 121; // DocumentValidationFailure
    private const int NON_AUTORISE = 13; // Unauthorized

    private DocumentManager $documentManager;
    private string $marqueur;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->documentManager = self::getContainer()->get(DocumentManager::class);
        $this->marqueur = bin2hex(random_bytes(6));
    }

    public function testBaseDeTestEtCompteApplicatif(): void
    {
        self::assertSame('pretoutil_test', $this->documentManager->getConfiguration()->getDefaultDB());
        self::assertStringContainsString('pretoutil_app', (string) $_SERVER['MONGODB_URI']);
    }

    public function testDocumentEcritPuisRelu(): void
    {
        $acteur = $this->acteur(5, 'gestionnaire@pretoutil.test');
        self::getContainer()->get(AuditLogger::class)
            ->journaliser(ActionAudit::RetourEnregistre, $acteur, 'reservation', 42, ['objet' => 'Tente 4 places', 'marqueur' => $this->marqueur]);

        $brut = $this->collection()->findOne(['details.marqueur' => $this->marqueur]);
        self::assertNotNull($brut, 'Document écrit dans pretoutil_test.audit_log');
        self::assertSame('RETOUR_ENREGISTRE', $brut['action']);
        self::assertInstanceOf(UTCDateTime::class, $brut['horodatage'], 'Type BSON date');
        self::assertSame(['id' => 5, 'email' => 'gestionnaire@pretoutil.test'], $brut['acteur']);
        self::assertSame(['type' => 'reservation', 'id' => 42], $brut['cible']);

        $relu = $this->documentManager->getRepository(AuditLog::class)->find((string) $brut['_id']);
        self::assertInstanceOf(AuditLog::class, $relu);
        self::assertSame(ActionAudit::RetourEnregistre, $relu->getAction());
        self::assertSame('Tente 4 places', $relu->getDetails()['objet']);
    }

    /** Piège PHP : un tableau vide s'écrirait [] ; le validateur exige un objet {}. */
    public function testDetailsVidesEcritsCommeUnObjet(): void
    {
        $acteur = $this->acteur(9, \sprintf('vide-%s@pretoutil.test', $this->marqueur));
        self::getContainer()->get(AuditLogger::class)->journaliser(ActionAudit::CompteCree, $acteur, 'utilisateur', 9);

        self::assertNotNull($this->collection()->findOne(['acteur.email' => $acteur->getEmail()]), 'Accepté par le validateur');
        // Vérifié par MongoDB lui-même : details est de type BSON « object », pas « array ».
        self::assertNotNull($this->collection()->findOne(['acteur.email' => $acteur->getEmail(), 'details' => ['$type' => 'object', '$eq' => new \stdClass()]]));
        self::assertNull($this->collection()->findOne(['acteur.email' => $acteur->getEmail(), 'details' => ['$type' => 'array']]));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function documentsInvalides(): iterable
    {
        $valide = ['acteur' => ['id' => 1, 'email' => 'a@pretoutil.test'], 'cible' => ['type' => 'objet', 'id' => 1], 'details' => []];

        yield 'action inconnue' => [['action' => 'ACTION_INCONNUE'] + $valide];
        yield 'champ non prévu' => [['action' => 'OBJET_CREE', 'adresse_ip' => '192.0.2.1'] + $valide];
        yield 'acteur sans email' => [['action' => 'OBJET_CREE', 'acteur' => ['id' => 1]] + $valide];
    }

    /** @param array<string, mixed> $document */
    #[\PHPUnit\Framework\Attributes\DataProvider('documentsInvalides')]
    public function testDocumentInvalideRefuseParLeValidateur(array $document): void
    {
        $document['horodatage'] = new UTCDateTime();
        $document['details'] = (object) ['marqueur' => $this->marqueur];

        try {
            $this->collection()->insertOne($document);
            self::fail('Le validateur $jsonSchema aurait dû refuser ce document');
        } catch (ServerException $e) {
            self::assertSame(self::VALIDATION_REFUSEE, $e->getCode());
        }
    }

    /** Journal en ajout seul : le compte applicatif ne peut ni modifier ni supprimer. */
    public function testModificationEtSuppressionInterdites(): void
    {
        foreach ([
            'suppression' => fn () => $this->collection()->deleteMany([]),
            'modification' => fn () => $this->collection()->updateMany([], ['$set' => ['action' => 'OBJET_CREE']]),
        ] as $operation => $tentative) {
            try {
                $tentative();
                self::fail(\sprintf('La %s aurait dû être refusée', $operation));
            } catch (ServerException $e) {
                self::assertSame(self::NON_AUTORISE, $e->getCode(), $operation);
            }
        }
    }

    public function testListeFiltreeParActionDuPlusRecentAuPlusAncien(): void
    {
        $logger = self::getContainer()->get(AuditLogger::class);
        $acteur = $this->acteur(1, 'adherent@pretoutil.test');
        $logger->journaliser(ActionAudit::ReservationCreee, $acteur, 'reservation', 1, ['marqueur' => $this->marqueur, 'rang' => 1]);
        $logger->journaliser(ActionAudit::ReservationAnnulee, $acteur, 'reservation', 1, ['marqueur' => $this->marqueur]);
        usleep(2000);
        $logger->journaliser(ActionAudit::ReservationCreee, $acteur, 'reservation', 2, ['marqueur' => $this->marqueur, 'rang' => 2]);

        $page = self::getContainer()->get(AuditLogRepository::class)->lister(ActionAudit::ReservationCreee);

        // Une seule assertion : la page grossit d'un lancement à l'autre (base en ajout seul),
        // une assertion par entrée rendrait le nombre d'assertions instable.
        $autresActions = array_filter($page->elements, static fn (AuditLog $e): bool => ActionAudit::ReservationCreee !== $e->getAction());
        self::assertSame([], $autresActions, 'Filtre par action');
        $miens = array_values(array_filter($page->elements, fn (AuditLog $e): bool => $this->marqueur === ($e->getDetails()['marqueur'] ?? null)));
        self::assertSame([2, 1], array_map(static fn (AuditLog $e): int => $e->getDetails()['rang'], $miens), 'Plus récent en premier');
    }

    public function testConnexionEchoueeJournalisee(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $email = \sprintf('inconnu-%s@pretoutil.test', $this->marqueur);

        $crawler = $client->request('GET', '/connexion');
        $client->submit($crawler->selectButton('Se connecter')->form(['email' => $email, 'mot_de_passe' => 'mauvais']));

        $brut = $this->collection()->findOne(['acteur.email' => $email]);
        self::assertNotNull($brut, 'Échec de connexion journalisé');
        self::assertSame('CONNEXION_ECHOUEE', $brut['action']);
        self::assertNull($brut['acteur']['id']);
        self::assertSame(['motif' => 'echec'], $brut['details']);
    }

    private function collection(): Collection
    {
        $base = self::getContainer()->get(DocumentManager::class)->getConfiguration()->getDefaultDB();

        return self::getContainer()->get(DocumentManager::class)->getClient()->getCollection($base, 'audit_log');
    }

    private function acteur(int $id, string $email): Utilisateur
    {
        $acteur = (new Utilisateur())->setEmail($email)->setNom('Test')->setPrenom('Journal');
        (new \ReflectionProperty(Utilisateur::class, 'id'))->setValue($acteur, $id);

        return $acteur;
    }
}
