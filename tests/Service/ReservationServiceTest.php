<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Enum\EtatObjet;
use App\Enum\StatutReservation;
use App\Exception\AnnulationImpossibleException;
use App\Exception\DureeDepasseeException;
use App\Exception\ObjetNonReservableException;
use App\Exception\PeriodeIndisponibleException;
use App\Exception\PeriodeInvalideException;
use App\Exception\QuotaAtteintException;
use App\Repository\ReservationRepository;
use App\Service\AuditLogger;
use App\Service\ReservationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Tests unitaires de ReservationService (US4, US5) : une méthode par branche du
 * diagramme de séquence docs/diagrammes/sequence-reserver.md, plus les cas limites.
 *
 * Base de données simulée : le repository, l'EntityManager et la connexion sont des
 * doubles ; l'horloge est fixée. RG6 (annulation jusqu'à la veille) est testée dans
 * ReservationVoterTest, car le droit d'annuler est vérifié par le Voter.
 *
 * Les doubles sont créés une fois dans setUp() ; chaque test ne pose des attentes
 * (expects) que sur les appels qu'il vérifie, d'où l'attribut ci-dessous.
 */
#[AllowMockObjectsWithoutExpectations]
final class ReservationServiceTest extends TestCase
{
    /** « Aujourd'hui » des tests : jeudi 10 décembre 2026, 15 h à La Réunion. */
    private const string MAINTENANT = '2026-12-10 15:00:00';
    private const string FUSEAU = 'Indian/Reunion';

    private ReservationRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $entityManager;
    private Connection&MockObject $connexion;
    private AuditLogger&MockObject $auditLogger;
    private ReservationService $service;
    private Utilisateur $adherent;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReservationRepository::class);
        $this->connexion = $this->createMock(Connection::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connexion);
        $this->auditLogger = $this->createMock(AuditLogger::class);

        $this->service = new ReservationService(
            $this->entityManager,
            $this->repository,
            $this->auditLogger,
            new MockClock(self::MAINTENANT, new \DateTimeZone(self::FUSEAU)),
        );

        $this->adherent = (new Utilisateur())->setEmail('adherent@pretoutil.test')->setNom('Martin')->setPrenom('Camille');
    }

    // ------------------------------------------------------------------
    // Cas nominal
    // ------------------------------------------------------------------

    public function testReservationValideEnregistreeEtJournalisee(): void
    {
        $this->repository->method('compterActives')->willReturn(0);
        $this->repository->method('existeChevauchement')->willReturn(false);

        $this->connexion->expects(self::once())->method('beginTransaction');
        $this->connexion->expects(self::once())->method('commit');
        $this->connexion->expects(self::never())->method('rollBack');
        $this->entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Reservation::class));
        $this->entityManager->expects(self::once())->method('flush');
        $this->auditLogger->expects(self::once())->method('journaliser')
            ->with(ActionAudit::ReservationCreee, $this->adherent, 'reservation', self::anything(), self::callback(
                static fn (array $details): bool => '2026-12-12' === $details['date_debut'] && '2026-12-14' === $details['date_fin'],
            ));

        $reservation = $this->service->reserver($this->adherent, $this->objet(), $this->jour('2026-12-12 18:45'), $this->jour('2026-12-14'));

        self::assertSame(StatutReservation::Confirmee, $reservation->getStatut());
        self::assertSame($this->adherent, $reservation->getUtilisateur());
        // Les heures saisies sont ignorées : une réservation porte sur des jours entiers.
        self::assertSame('2026-12-12 00:00', $reservation->getDateDebut()->format('Y-m-d H:i'));
        self::assertNotNull($this->adherent->getDerniereActiviteLe(), 'Dernière activité mise à jour (durée de conservation RGPD)');
    }

    // ------------------------------------------------------------------
    // RG1 : période cohérente, pas dans le passé
    // ------------------------------------------------------------------

    /** @return iterable<string, array{string, string, string|null}> */
    public static function casRG1(): iterable
    {
        yield 'début aujourd\'hui : accepté' => ['2026-12-10', '2026-12-11', null];
        yield 'début et fin le même jour : accepté' => ['2026-12-11', '2026-12-11', null];
        yield 'début hier : refusé' => ['2026-12-09', '2026-12-11', 'La réservation ne peut pas commencer dans le passé.'];
        yield 'fin avant le début : refusé' => ['2026-12-15', '2026-12-14', 'La date de fin doit être après la date de début.'];
    }

    #[DataProvider('casRG1')]
    public function testRG1Periode(string $debut, string $fin, ?string $messageAttendu): void
    {
        $this->accepterLesAutresRegles();

        if (null !== $messageAttendu) {
            $this->refusAvantTransaction();
            $this->expectException(PeriodeInvalideException::class);
            $this->expectExceptionMessage($messageAttendu);
        }

        $reservation = $this->service->reserver($this->adherent, $this->objet(), $this->jour($debut), $this->jour($fin));

        self::assertSame($debut, $reservation->getDateDebut()->format('Y-m-d'));
    }

    // ------------------------------------------------------------------
    // RG3 : 14 jours au maximum, premier et dernier jour compris
    // ------------------------------------------------------------------

    /** @return iterable<string, array{string, bool}> */
    public static function casRG3(): iterable
    {
        yield '14 jours exactement : accepté' => ['2026-12-23', true];
        yield '15 jours : refusé' => ['2026-12-24', false];
    }

    #[DataProvider('casRG3')]
    public function testRG3Duree(string $fin, bool $accepte): void
    {
        $this->accepterLesAutresRegles();

        if (!$accepte) {
            $this->refusAvantTransaction();
            $this->expectException(DureeDepasseeException::class);
            $this->expectExceptionMessage('Une réservation dure 14 jours au maximum.');
        }

        $reservation = $this->service->reserver($this->adherent, $this->objet(), $this->jour('2026-12-10'), $this->jour($fin));

        self::assertSame($fin, $reservation->getDateFin()->format('Y-m-d'));
    }

    // ------------------------------------------------------------------
    // RG5 : objet réservable
    // ------------------------------------------------------------------

    public function testRG5ObjetAbimeResteReservable(): void
    {
        $this->accepterLesAutresRegles();

        $reservation = $this->service->reserver($this->adherent, $this->objet(EtatObjet::Abime), $this->jour('2026-12-12'), $this->jour('2026-12-13'));

        self::assertSame(EtatObjet::Abime, $reservation->getObjet()->getEtat());
    }

    /** @return iterable<string, array{EtatObjet, bool, bool, string}> */
    public static function casRG5Refus(): iterable
    {
        yield 'objet hors service' => [EtatObjet::HorsService, true, false, 'hors service'];
        yield 'objet retiré du catalogue' => [EtatObjet::Bon, false, false, 'ne fait plus partie du catalogue'];
        yield 'retour en retard de l\'emprunteur précédent' => [EtatObjet::Bon, true, true, 'pas encore été rendu'];
    }

    #[DataProvider('casRG5Refus')]
    public function testRG5ObjetNonReservable(EtatObjet $etat, bool $actif, bool $retourEnRetard, string $extraitMessage): void
    {
        $this->repository->method('existeRetourEnRetard')->willReturn($retourEnRetard);
        $this->refusDansTransaction();

        $this->expectException(ObjetNonReservableException::class);
        $this->expectExceptionMessage($extraitMessage);

        $this->service->reserver($this->adherent, $this->objet($etat, $actif), $this->jour('2026-12-12'), $this->jour('2026-12-13'));
    }

    // ------------------------------------------------------------------
    // RG4 : 3 réservations actives au maximum, vérifié sous verrou
    // ------------------------------------------------------------------

    public function testRG4TroisiemeReservationAcceptee(): void
    {
        $this->repository->method('compterActives')->willReturn(2);
        $this->repository->method('existeChevauchement')->willReturn(false);

        $reservation = $this->service->reserver($this->adherent, $this->objet(), $this->jour('2026-12-12'), $this->jour('2026-12-13'));

        self::assertSame(StatutReservation::Confirmee, $reservation->getStatut());
    }

    public function testRG4QuatriemeReservationRefusee(): void
    {
        $this->repository->method('compterActives')->willReturn(3);
        $this->repository->expects(self::never())->method('existeChevauchement');
        $this->refusDansTransaction();

        $this->expectException(QuotaAtteintException::class);
        $this->expectExceptionMessage('Vous avez déjà 3 réservations en cours ou à venir');

        $this->service->reserver($this->adherent, $this->objet(), $this->jour('2026-12-12'), $this->jour('2026-12-13'));
    }

    public function testRG4QuotaCompteSousVerrouDeLAdherent(): void
    {
        $ordre = [];
        $this->repository->method('verrouillerAdherent')->willReturnCallback(static function () use (&$ordre): void {
            $ordre[] = 'verrou';
        });
        $this->repository->method('compterActives')->willReturnCallback(static function () use (&$ordre): int {
            $ordre[] = 'comptage';

            return 0;
        });
        $this->repository->method('existeChevauchement')->willReturn(false);
        $this->connexion->method('beginTransaction')->willReturnCallback(static function () use (&$ordre): void {
            $ordre[] = 'transaction';
        });

        $this->service->reserver($this->adherent, $this->objet(), $this->jour('2026-12-12'), $this->jour('2026-12-13'));

        // Deux demandes simultanées du même adhérent ne peuvent pas dépasser le quota.
        self::assertSame(['transaction', 'verrou', 'comptage'], $ordre);
    }

    // ------------------------------------------------------------------
    // RG2 : pas de chevauchement avec une réservation active
    // ------------------------------------------------------------------

    public function testRG2PeriodeDejaReservee(): void
    {
        $this->repository->method('compterActives')->willReturn(0);
        $this->repository->method('existeChevauchement')->willReturn(true);
        $this->refusDansTransaction();

        $this->expectException(PeriodeIndisponibleException::class);

        $this->service->reserver($this->adherent, $this->objet(), $this->jour('2026-12-12'), $this->jour('2026-12-13'));
    }

    public function testRG2ConflitConcurrentTraduitEnRefusMetier(): void
    {
        // Une autre transaction a réservé entre la vérification et l'INSERT :
        // PostgreSQL refuse l'INSERT avec la contrainte EXCLUDE (SQLSTATE 23P01).
        $this->accepterLesAutresRegles();
        $this->entityManager->method('flush')->willThrowException($this->erreurSql('23P01'));
        $this->refusDansTransaction();

        try {
            $this->service->reserver($this->adherent, $this->objet(), $this->jour('2026-12-12'), $this->jour('2026-12-13'));
            self::fail('PeriodeIndisponibleException attendue');
        } catch (PeriodeIndisponibleException $e) {
            self::assertInstanceOf(DriverException::class, $e->getPrevious(), 'Erreur SQL d\'origine conservée');
        }
    }

    public function testAutreErreurSqlNonTraduite(): void
    {
        $this->accepterLesAutresRegles();
        $this->entityManager->method('flush')->willThrowException($this->erreurSql('23505'));
        $this->refusDansTransaction();

        $this->expectException(DriverException::class);

        $this->service->reserver($this->adherent, $this->objet(), $this->jour('2026-12-12'), $this->jour('2026-12-13'));
    }

    // ------------------------------------------------------------------
    // Annulation (US5) ; le délai RG6 est contrôlé par ReservationVoter
    // ------------------------------------------------------------------

    public function testAnnulerUneReservationConfirmee(): void
    {
        $reservation = $this->reservation(StatutReservation::Confirmee);
        $this->entityManager->expects(self::once())->method('flush');
        $this->auditLogger->expects(self::once())->method('journaliser')
            ->with(ActionAudit::ReservationAnnulee, $this->adherent, 'reservation', self::anything(), self::anything());

        $this->service->annuler($reservation, $this->adherent);

        self::assertSame(StatutReservation::Annulee, $reservation->getStatut());
        self::assertNotNull($this->adherent->getDerniereActiviteLe());
    }

    /** @return iterable<string, array{StatutReservation}> */
    public static function statutsNonAnnulables(): iterable
    {
        yield 'en cours' => [StatutReservation::EnCours];
        yield 'terminée' => [StatutReservation::Terminee];
        yield 'déjà annulée' => [StatutReservation::Annulee];
    }

    #[DataProvider('statutsNonAnnulables')]
    public function testAnnulationImpossible(StatutReservation $statut): void
    {
        $reservation = $this->reservation($statut);
        $this->entityManager->expects(self::never())->method('flush');
        $this->auditLogger->expects(self::never())->method('journaliser');

        $this->expectException(AnnulationImpossibleException::class);

        $this->service->annuler($reservation, $this->adherent);
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    /** Toutes les vérifications passent, sauf celle que le test fait échouer. */
    private function accepterLesAutresRegles(): void
    {
        $this->repository->method('existeRetourEnRetard')->willReturn(false);
        $this->repository->method('compterActives')->willReturn(0);
        $this->repository->method('existeChevauchement')->willReturn(false);
    }

    /** RG1 et RG3 sont vérifiées avant d'ouvrir la transaction : rien n'est écrit ni journalisé. */
    private function refusAvantTransaction(): void
    {
        $this->connexion->expects(self::never())->method('beginTransaction');
        $this->entityManager->expects(self::never())->method('persist');
        $this->auditLogger->expects(self::never())->method('journaliser');
    }

    /** Refus après l'ouverture de la transaction : annulation (ROLLBACK), pas de journal. */
    private function refusDansTransaction(): void
    {
        $this->connexion->method('isTransactionActive')->willReturn(true);
        $this->connexion->expects(self::once())->method('rollBack');
        $this->connexion->expects(self::never())->method('commit');
        $this->auditLogger->expects(self::never())->method('journaliser');
    }

    private function objet(EtatObjet $etat = EtatObjet::Bon, bool $actif = true): Objet
    {
        return (new Objet())
            ->setCategorie((new Categorie())->setLibelle('Outillage'))
            ->setNom('Perceuse-visseuse 18 V')
            ->setDescription('Livrée avec deux batteries.')
            ->setEtat($etat)
            ->setActif($actif);
    }

    private function reservation(StatutReservation $statut): Reservation
    {
        return (new Reservation($this->adherent, $this->objet(), $this->jour('2026-12-20'), $this->jour('2026-12-22')))
            ->setStatut($statut);
    }

    private function jour(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date, new \DateTimeZone(self::FUSEAU));
    }

    private function erreurSql(string $sqlState): DriverException
    {
        $erreurPilote = new class('Erreur SQL simulée', $sqlState) extends AbstractException {
        };

        return new DriverException($erreurPilote, null);
    }
}
