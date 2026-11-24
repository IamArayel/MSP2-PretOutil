<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\EtatObjet;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * API REST des disponibilités (US11, docs/conception/api.md), par de vraies requêtes HTTP
 * sur la base de test : codes de retour, format JSON, absence de donnée personnelle,
 * limitation de débit.
 *
 * Chaque test s'exécute dans une transaction annulée à la fin : la base reste vide.
 * Prérequis : base créée et migrée (README, section Tests).
 */
final class DisponibiliteControllerTest extends WebTestCase
{
    private const string EMAIL_ADHERENT = 'adherent.api@pretoutil.test';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private Connection $connexion;
    private Objet $tente;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Même noyau pour toutes les requêtes du test : même connexion, donc même transaction.
        $this->client->disableReboot();
        // Compteurs de la limitation de débit remis à zéro : chaque test part de 0 appel.
        self::getContainer()->get('cache.limiteur_api')->clear();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $this->entityManager->getConnection();
        $this->connexion->beginTransaction();

        $categorie = (new Categorie())->setLibelle('Camping (test API)');
        $this->tente = $this->objet($categorie, 'Tente de test');
        $adherent = (new Utilisateur())
            ->setEmail(self::EMAIL_ADHERENT)
            ->setNom('Testeur')
            ->setPrenom('Api')
            ->setMotDePasse('empreinte-de-test');
        $reservation = new Reservation($adherent, $this->tente, $this->jour(7), $this->jour(9));

        $this->entityManager->persist($categorie);
        $this->entityManager->persist($adherent);
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }

        parent::tearDown();
    }

    public function testSansPeriodeRenvoieStatutEtPeriodesReservees(): void
    {
        $donnees = $this->appeler($this->tente);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(['id' => $this->tente->getId(), 'nom' => 'Tente de test'], $donnees['objet']);
        self::assertSame('disponible', $donnees['statut_du_jour']['code']);
        self::assertSame([['debut' => $this->jour(7)->format('Y-m-d'), 'fin' => $this->jour(9)->format('Y-m-d')]], $donnees['periodes_reservees']);
        self::assertArrayNotHasKey('periode_demandee', $donnees);
    }

    public function testAucuneDonneePersonnelle(): void
    {
        $this->appeler($this->tente);
        $corps = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString(self::EMAIL_ADHERENT, $corps);
        self::assertStringNotContainsString('Testeur', $corps);
    }

    public function testPeriodeQuiChevaucheIndisponibleAvecMotif(): void
    {
        $periode = $this->appeler($this->tente, 8, 10)['periode_demandee'];

        self::assertResponseIsSuccessful();
        self::assertFalse($periode['disponible']);
        self::assertStringContainsString('déjà réservé', $periode['motif']);
    }

    public function testPeriodeLibreDisponible(): void
    {
        $periode = $this->appeler($this->tente, 10, 11)['periode_demandee'];

        self::assertTrue($periode['disponible']);
        self::assertNull($periode['motif']);
    }

    public function testObjetHorsServiceIndisponible(): void
    {
        $meuleuse = $this->objet($this->tente->getCategorie(), 'Meuleuse de test', EtatObjet::HorsService);
        $this->entityManager->flush();

        $donnees = $this->appeler($meuleuse, 10, 11);

        self::assertSame('hors_service', $donnees['statut_du_jour']['code']);
        self::assertFalse($donnees['periode_demandee']['disponible']);
    }

    public function testMemesReglesQueLaReservation(): void
    {
        // RG1 et RG3 : mêmes refus que ReservationService, avec leur message.
        self::assertStringContainsString('passé', $this->appeler($this->tente, -1, 2)['periode_demandee']['motif']);
        self::assertStringContainsString('14 jours', $this->appeler($this->tente, 20, 34)['periode_demandee']['motif']);
    }

    /** @return iterable<string, array{0: string}> */
    public static function parametresInvalides(): iterable
    {
        yield 'date inexistante' => ['?debut=2026-02-30&fin=2026-03-02'];
        yield 'format incomplet' => ['?debut=2026-12-1&fin=2026-12-02'];
        yield 'texte' => ['?debut=demain&fin=2026-12-02'];
        yield 'une seule date' => ['?debut=2026-12-01'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('parametresInvalides')]
    public function testParametresInvalides400(string $parametres): void
    {
        $this->client->request('GET', $this->url($this->tente).$parametres);

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('erreur', $this->json());
    }

    public function testObjetDesactive404(): void
    {
        $this->tente->setActif(false);
        $this->entityManager->flush();

        $this->client->request('GET', $this->url($this->tente));

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['erreur' => 'Objet introuvable.'], $this->json());
    }

    public function testObjetInconnu404(): void
    {
        $this->client->request('GET', '/api/objets/999999999/disponibilites');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testMethodeAutreQueGet405(): void
    {
        $this->client->request('POST', $this->url($this->tente));

        self::assertResponseStatusCodeSame(405);
        self::assertArrayHasKey('erreur', $this->json());
    }

    public function testLimiteDeDebit429(): void
    {
        for ($appel = 1; $appel <= 30; ++$appel) {
            $this->client->request('GET', $this->url($this->tente));
            self::assertResponseIsSuccessful();
        }

        $this->client->request('GET', $this->url($this->tente));

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertArrayHasKey('erreur', $this->json());
    }

    private function objet(Categorie $categorie, string $nom, EtatObjet $etat = EtatObjet::Bon): Objet
    {
        $objet = (new Objet())->setCategorie($categorie)->setNom($nom)->setDescription('Objet de test')->setEtat($etat);
        $this->entityManager->persist($objet);

        return $objet;
    }

    /** @return array<string, mixed> */
    private function appeler(Objet $objet, ?int $debut = null, ?int $fin = null): array
    {
        $parametres = null === $debut ? '' : \sprintf('?debut=%s&fin=%s', $this->jour($debut)->format('Y-m-d'), $this->jour($fin)->format('Y-m-d'));
        $this->client->request('GET', $this->url($objet).$parametres);

        return $this->json();
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function url(Objet $objet): string
    {
        return \sprintf('/api/objets/%d/disponibilites', $objet->getId());
    }

    private function jour(int $decalage): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('today %+d days', $decalage));
    }
}
