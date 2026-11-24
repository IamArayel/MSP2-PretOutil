<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\StatutReservation;
use App\Exception\PeriodeIndisponibleException;
use App\Exception\QuotaAtteintException;
use App\Repository\ReservationRepository;
use App\Service\AuditLogger;
use App\Service\ReservationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * ReservationService sur la vraie base de test (pretoutil_test), avec le compte
 * applicatif pretoutil_app : contrainte EXCLUDE, comptage des réservations actives.
 *
 * Chaque test s'exécute dans une transaction annulée à la fin : la base reste vide.
 * Prérequis : base créée et migrée (README, section Tests).
 */
final class ReservationServiceIntegrationTest extends KernelTestCase
{
    private const string MAINTENANT = '2026-12-10 10:00:00';

    private EntityManagerInterface $entityManager;
    private Connection $connexion;
    private Categorie $categorie;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $this->entityManager->getConnection();

        // Le service ouvre sa propre transaction : elle devient un point de sauvegarde (SAVEPOINT)
        // de celle-ci, annulée dans tearDown().
        $this->connexion->beginTransaction();

        $this->categorie = (new Categorie())->setLibelle('Catégorie de test');
        $this->entityManager->persist($this->categorie);
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }

        parent::tearDown();
    }

    public function testCompteApplicatif(): void
    {
        self::assertSame('pretoutil_app', $this->connexion->fetchOne('SELECT current_user'));
        self::assertSame('pretoutil_test', $this->connexion->fetchOne('SELECT current_database()'));
    }

    /** RG2 : cas courant, le chevauchement est détecté par la requête du service. */
    public function testChevauchementDetecteAvantEnregistrement(): void
    {
        $objet = $this->objet();
        $this->service()->reserver($this->adherent('a'), $objet, $this->jour('2026-12-12'), $this->jour('2026-12-14'));

        try {
            $this->service()->reserver($this->adherent('b'), $objet, $this->jour('2026-12-14'), $this->jour('2026-12-16'));
            self::fail('PeriodeIndisponibleException attendue');
        } catch (PeriodeIndisponibleException $e) {
            self::assertNull($e->getPrevious(), 'Refus par le service, sans erreur SQL');
        }
    }

    /**
     * RG2 en cas d'accès simultanés : la vérification du service n'a rien vu (simulé par un
     * repository qui répond « pas de chevauchement »), c'est la contrainte EXCLUDE de
     * PostgreSQL qui refuse l'INSERT. L'erreur 23P01 est traduite en refus métier.
     */
    public function testContrainteExcludeTraduiteEnPeriodeIndisponible(): void
    {
        $objet = $this->objet();
        $this->service()->reserver($this->adherent('a'), $objet, $this->jour('2026-12-12'), $this->jour('2026-12-14'));

        $registry = self::getContainer()->get(ManagerRegistry::class);
        $repositoryAveugle = new class($registry) extends ReservationRepository {
            public function existeChevauchement(Objet $objet, \DateTimeImmutable $debut, \DateTimeImmutable $fin): bool
            {
                return false;
            }
        };

        try {
            $this->service($repositoryAveugle)->reserver($this->adherent('b'), $objet, $this->jour('2026-12-13'), $this->jour('2026-12-15'));
            self::fail('PeriodeIndisponibleException attendue');
        } catch (PeriodeIndisponibleException $e) {
            $erreurSql = $e->getPrevious();
            self::assertInstanceOf(DriverException::class, $erreurSql);
            self::assertSame('23P01', $erreurSql->getSQLState());
            self::assertStringContainsString('ex_reservation_chevauchement', $erreurSql->getMessage());
        }
    }

    /** RG2 : une réservation annulée ne bloque plus la période. */
    public function testPeriodeDUneReservationAnnuleeReservable(): void
    {
        $objet = $this->objet();
        $premiere = $this->service()->reserver($this->adherent('a'), $objet, $this->jour('2026-12-12'), $this->jour('2026-12-14'));
        $premiere->setStatut(StatutReservation::Annulee);
        $this->entityManager->flush();

        $seconde = $this->service()->reserver($this->adherent('b'), $objet, $this->jour('2026-12-13'), $this->jour('2026-12-15'));

        self::assertNotNull($seconde->getId());
    }

    /** RG4 sur des données réelles : une réservation annulée ne compte pas dans le quota. */
    public function testReservationAnnuleeNeComptePasDansLeQuota(): void
    {
        $adherent = $this->adherent('quota');
        $this->reservationExistante($adherent, StatutReservation::Confirmee);
        $this->reservationExistante($adherent, StatutReservation::Confirmee);
        $this->reservationExistante($adherent, StatutReservation::Annulee);

        $reservation = $this->service()->reserver($adherent, $this->objet(), $this->jour('2026-12-20'), $this->jour('2026-12-21'));

        self::assertNotNull($reservation->getId(), '2 actives + 1 annulée : la 3e active est acceptée');
    }

    public function testQuatriemeReservationActiveRefusee(): void
    {
        $adherent = $this->adherent('quota');
        $this->reservationExistante($adherent, StatutReservation::Confirmee);
        $this->reservationExistante($adherent, StatutReservation::Confirmee);
        $this->reservationExistante($adherent, StatutReservation::Confirmee);

        $this->expectException(QuotaAtteintException::class);

        $this->service()->reserver($adherent, $this->objet(), $this->jour('2026-12-20'), $this->jour('2026-12-21'));
    }

    private function service(?ReservationRepository $repository = null): ReservationService
    {
        return new ReservationService(
            $this->entityManager,
            $repository ?? self::getContainer()->get(ReservationRepository::class),
            new AuditLogger(),
            new MockClock(self::MAINTENANT),
        );
    }

    private function adherent(string $nom): Utilisateur
    {
        $adherent = (new Utilisateur())
            ->setEmail($nom.'@integration.test')
            ->setNom($nom)
            ->setPrenom('Test')
            ->setMotDePasse('empreinte-non-utilisee');
        $this->entityManager->persist($adherent);
        $this->entityManager->flush();

        return $adherent;
    }

    private function objet(): Objet
    {
        $objet = (new Objet())
            ->setCategorie($this->categorie)
            ->setNom('Objet de test')
            ->setDescription('Créé par le test d\'intégration.');
        $this->entityManager->persist($objet);
        $this->entityManager->flush();

        return $objet;
    }

    /** Réservation déjà en base, sur un objet à part, pour remplir le quota d'un adhérent. */
    private function reservationExistante(Utilisateur $adherent, StatutReservation $statut): void
    {
        $reservation = (new Reservation($adherent, $this->objet(), $this->jour('2026-12-15'), $this->jour('2026-12-16')))
            ->setStatut($statut);
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();
    }

    private function jour(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }
}
