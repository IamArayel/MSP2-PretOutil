<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Critères d'accessibilité vérifiables automatiquement (grille : docs/tests/accessibilite.md),
 * sur chaque écran et dans ses états d'erreur : langue, titre de page, titre h1 unique,
 * niveaux de titres sans saut, étiquette de chaque champ, aide et erreurs reliées au champ,
 * en-têtes de tableau, lien d'évitement.
 *
 * Ne remplace pas la vérification manuelle (clavier, lecteur d'écran, zoom, contraste).
 * Chaque test s'exécute dans une transaction annulée à la fin.
 */
final class AccessibiliteTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private Connection $connexion;
    private Utilisateur $adherent;
    private Objet $objet;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        self::getContainer()->get('cache.limiteur_api')->clear();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $this->entityManager->getConnection();
        $this->connexion->beginTransaction();

        $this->adherent = (new Utilisateur())->setEmail('adherent.a11y@pretoutil.test')->setNom('Martin')->setPrenom('Camille')->setMotDePasse('empreinte-non-utilisee');
        $categorie = (new Categorie())->setLibelle('Outillage (test accessibilité)');
        $this->objet = (new Objet())->setCategorie($categorie)->setNom('Perceuse')->setDescription('Objet de test');
        $reservation = new Reservation($this->adherent, $this->objet, new \DateTimeImmutable('today +7 days'), new \DateTimeImmutable('today +9 days'));
        foreach ([$this->adherent, $categorie, $this->objet, $reservation] as $entite) {
            $this->entityManager->persist($entite);
        }
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }

        parent::tearDown();
    }

    private const array INSCRIPTION_INVALIDE = ['inscription' => ['email' => 'pas-un-email', 'motDePasse' => 'court']];
    private const array RESERVATION_VIDE = ['reservation' => ['debut' => '', 'fin' => '']];

    /** @return iterable<string, array{string, string, bool, array<string, mixed>}> écran => [méthode, chemin, connecté, données envoyées] */
    public static function ecrans(): iterable
    {
        yield 'Accueil' => ['GET', '/', false, []];
        yield 'E1 Connexion' => ['GET', '/connexion', false, []];
        yield 'E2 Inscription' => ['GET', '/inscription', false, []];
        yield 'E2 Inscription, erreurs' => ['POST', '/inscription', false, self::INSCRIPTION_INVALIDE];
        yield 'E3 Catalogue' => ['GET', '/catalogue', false, []];
        yield 'E3 Catalogue, filtres invalides' => ['GET', '/catalogue?categorie=999999&debut=x', false, []];
        yield 'E4 Fiche, visiteur' => ['GET', '/catalogue/{objet}', false, []];
        yield 'E4 Fiche, adhérent' => ['GET', '/catalogue/{objet}', true, []];
        yield 'E4 Réservation, erreurs' => ['POST', '/catalogue/{objet}/reserver', true, self::RESERVATION_VIDE];
        yield 'E5 Mes réservations' => ['GET', '/mes-reservations', true, []];
    }

    #[DataProvider('ecrans')]
    /** @param array<string, mixed> $donnees */
    public function testCriteresDeStructure(string $methode, string $chemin, bool $connecte, array $donnees): void
    {
        if ($connecte) {
            $this->client->loginUser($this->adherent);
        }
        $page = $this->client->request($methode, str_replace('{objet}', (string) $this->objet->getId(), $chemin), $donnees);
        self::assertLessThan(500, $this->client->getResponse()->getStatusCode());

        self::assertSame('fr', $page->filter('html')->attr('lang'), 'Langue de la page (RGAA 8.3)');
        self::assertMatchesRegularExpression('/^\S.* · Prêt\'Outil$/u', $page->filter('title')->text(), 'Titre de page pertinent (RGAA 8.6)');
        self::assertCount(1, $page->filter('h1'), 'Un seul titre h1 (RGAA 9.1)');
        $this->assertTitresSansSaut($page);
        self::assertSame('#contenu', $page->filter('body > a')->first()->attr('href'), 'Lien d\'évitement en premier (RGAA 12.7)');
        self::assertCount(1, $page->filter('main#contenu'));

        $page->filter('input:not([type="hidden"]):not([type="submit"]), select, textarea')->each(function (Crawler $champ) use ($page): void {
            $id = (string) $champ->attr('id');
            self::assertNotSame('', $id, 'Champ avec un id');
            self::assertCount(1, $page->filter(\sprintf('label[for="%s"]', $id)), \sprintf('Étiquette du champ %s (RGAA 11.1)', $id));

            foreach (array_filter(explode(' ', (string) $champ->attr('aria-describedby'))) as $cible) {
                self::assertCount(1, $page->filter('#'.$cible), \sprintf('aria-describedby de %s vers un élément existant', $id));
            }
            if ($page->filter('#'.$id.'_help')->count() > 0) {
                self::assertStringContainsString($id.'_help', (string) $champ->attr('aria-describedby'), \sprintf('Aide de %s reliée au champ (RGAA 11.10)', $id));
            }
            if ($page->filter('#'.$id.'_erreurs')->count() > 0) {
                self::assertStringContainsString($id.'_erreurs', (string) $champ->attr('aria-describedby'), \sprintf('Erreur de %s reliée au champ (RGAA 11.10)', $id));
                self::assertSame('true', $champ->attr('aria-invalid'));
            }
        });

        $page->filter('table')->each(static function (Crawler $tableau): void {
            self::assertCount(1, $tableau->filter('caption'), 'Tableau avec un titre (RGAA 5.4)');
            self::assertSame(0, $tableau->filter('th:not([scope])')->count(), 'En-têtes avec scope (RGAA 5.7)');
        });
    }

    /** Les états d'erreur testés ci-dessus affichent bien des erreurs : sinon le test ne prouverait rien. */
    public function testLesEtatsDErreurAffichentDesErreursReliees(): void
    {
        $page = $this->client->request('POST', '/inscription', self::INSCRIPTION_INVALIDE);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('inscription_motDePasse_help inscription_motDePasse_erreurs', $page->filter('#inscription_motDePasse')->attr('aria-describedby'));

        $this->client->loginUser($this->adherent);
        $page = $this->client->request('POST', \sprintf('/catalogue/%d/reserver', $this->objet->getId()), self::RESERVATION_VIDE);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('true', $page->filter('#reservation_debut')->attr('aria-invalid'));

        $page = $this->client->request('GET', '/catalogue?categorie=999999');
        self::assertSame('categorie_erreurs', $page->filter('#categorie')->attr('aria-describedby'));
    }

    /** E5 : l'objet est l'en-tête de chaque ligne, annoncé avec chaque cellule. */
    public function testObjetEnTeteDeLigneSurMesReservations(): void
    {
        $this->client->loginUser($this->adherent);
        $page = $this->client->request('GET', '/mes-reservations');

        self::assertSelectorTextContains('tbody th[scope="row"]', 'Perceuse');
    }

    /** La page en cours est signalée dans la navigation principale. */
    public function testPageEnCoursSignaleeDansLaNavigation(): void
    {
        $this->client->loginUser($this->adherent);
        $this->client->request('GET', '/mes-reservations');

        self::assertSelectorTextSame('nav[aria-label="Navigation principale"] [aria-current="page"]', 'Mes réservations');
    }

    private function assertTitresSansSaut(Crawler $page): void
    {
        $precedent = 0;
        $page->filter('h1, h2, h3, h4, h5, h6')->each(function (Crawler $titre) use (&$precedent): void {
            $niveau = (int) substr($titre->nodeName(), 1);
            self::assertLessThanOrEqual($precedent + 1, $niveau, \sprintf('Pas de saut de niveau avant « %s » (RGAA 9.1)', $titre->text()));
            $precedent = $niveau;
        });
    }
}
