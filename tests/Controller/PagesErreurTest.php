<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Security\ConnexionRequiseEntryPoint;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

/**
 * Pages d'erreur personnalisées et redirection vers la connexion (Style-C2, #101).
 *
 * Les pages d'erreur ne s'affichent que sans le mode débogage, comme en production : le client
 * est créé avec debug à false. Chaque test s'exécute dans une transaction annulée à la fin.
 */
final class PagesErreurTest extends WebTestCase
{
    private const string MOT_DE_PASSE = 'BonMotDePasse!2026';

    private KernelBrowser $client;
    private Connection $connexion;
    private Utilisateur $adherent;

    protected function setUp(): void
    {
        $this->client = self::createClient(['debug' => false]);
        $this->client->disableReboot();
        self::getContainer()->get('cache.rate_limiter')->clear();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $entityManager->getConnection();
        $this->connexion->beginTransaction();

        $this->adherent = (new Utilisateur())->setEmail('adherent.erreurs@pretoutil.test')->setNom('Testeur')->setPrenom('Erreurs');
        $this->adherent->setMotDePasse(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($this->adherent, self::MOT_DE_PASSE));
        $entityManager->persist($this->adherent);
        $entityManager->flush();
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }

        parent::tearDown();
    }

    /** @return iterable<string, array{string, string, bool, int, string, string}> */
    public static function erreurs(): iterable
    {
        // [méthode, adresse, connecté, code, titre de la page, titre h1]
        yield '404 adresse inconnue' => ['GET', '/page-inexistante', false, 404, 'Page introuvable', 'Page introuvable'];
        yield '404 objet inexistant' => ['GET', '/catalogue/999999', false, 404, 'Page introuvable', 'Page introuvable'];
        yield '403 adhérent sur la gestion' => ['GET', '/gestion/catalogue', true, 403, 'Accès refusé', 'Accès refusé'];
        yield '405 action appelée par un lien' => ['GET', '/catalogue/1/reserver', true, 405, 'Action impossible', 'Action impossible depuis un lien'];
    }

    /** Page de la charte : code HTTP conservé, titres pertinents, aucun détail technique. */
    #[DataProvider('erreurs')]
    public function testPageDErreurPersonnalisee(string $methode, string $adresse, bool $connecte, int $code, string $titrePage, string $titre): void
    {
        if ($connecte) {
            $this->client->loginUser($this->adherent);
        }

        $page = $this->client->request($methode, $adresse);

        self::assertResponseStatusCodeSame($code);
        self::assertSame($titrePage.' · Prêt\'Outil', $page->filter('title')->text());
        self::assertCount(1, $page->filter('h1'));
        self::assertSelectorTextSame('h1', $titre);
        self::assertSelectorTextContains('main', 'Erreur '.$code);
        $this->assertStructureAccessible($page);
        $this->assertAucunDetailTechnique();
    }

    /** 403 d'une personne connectée : lien vers ses réservations plutôt que vers la connexion. */
    public function testAccesRefuseProposeMesReservations(): void
    {
        $this->client->loginUser($this->adherent);
        $page = $this->client->request('GET', '/admin/journal');

        self::assertResponseStatusCodeSame(403);
        self::assertCount(1, $page->filter('main a[href="/mes-reservations"]'));
        self::assertCount(0, $page->filter('main a[href="/connexion"]'));
    }

    /** L'API garde ses erreurs en JSON (ApiExceptionSubscriber), sans page HTML. */
    public function testErreurDeLApiToujoursEnJson(): void
    {
        $this->client->request('GET', '/api/objets/999999/disponibilites');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['erreur' => 'Objet introuvable.'], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function gabarits(): iterable
    {
        yield '429' => ['error429', 429, 'Un peu de patience'];
        yield 'générique (410)' => ['error', 410, 'Cette demande n\'a pas pu aboutir'];
    }

    /** Pages difficiles à provoquer par une requête : gabarits rendus directement. */
    #[DataProvider('gabarits')]
    public function testGabaritsDesAutresCodes(string $gabarit, int $code, string $titre): void
    {
        // Requête en cours, comme lors d'une vraie erreur : le gabarit commun lit app.request.
        self::getContainer()->get(RequestStack::class)->push(Request::create('/'));
        $html = self::getContainer()->get(Environment::class)->render('bundles/TwigBundle/Exception/'.$gabarit.'.html.twig', [
            'status_code' => $code,
            'status_text' => 'Gone',
        ]);
        $page = new Crawler($html);

        self::assertSame($titre, $page->filter('h1')->text());
        self::assertStringContainsString('Erreur '.$code, $page->filter('main')->text());
        $this->assertStructureAccessible($page);
    }

    /**
     * 500 : page autonome, sans la barre de navigation qui lit le compte connecté en base ;
     * elle s'affiche même si la base de données ne répond pas.
     */
    public function testPage500AutonomeEtSansDetail(): void
    {
        $html = self::getContainer()->get(Environment::class)->render('bundles/TwigBundle/Exception/error500.html.twig', [
            'status_code' => 500,
            'status_text' => 'Internal Server Error',
        ]);
        $page = new Crawler($html);

        self::assertSame('Un problème est survenu de notre côté', $page->filter('h1')->text());
        self::assertCount(0, $page->filter('nav'), 'Pas de navigation : aucun accès au compte connecté');
        self::assertStringNotContainsString('Internal Server Error', $page->filter('main')->text());
        $this->assertStructureAccessible($page);
    }

    /**
     * Visiteur sur une page réservée : connexion avec un message, puis retour à la page demandée
     * une fois connecté (adresse mémorisée par le pare-feu).
     */
    public function testRedirectionVersLaConnexionPuisRetourALaPage(): void
    {
        $this->client->request('GET', '/mes-reservations');
        self::assertResponseRedirects('/connexion');

        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', ConnexionRequiseEntryPoint::MESSAGE);

        $this->client->submitForm('Se connecter', ['email' => 'adherent.erreurs@pretoutil.test', 'mot_de_passe' => self::MOT_DE_PASSE]);
        self::assertResponseRedirects('http://localhost/mes-reservations');
    }

    /** Formulaire envoyé par un visiteur (POST) : redirection sans message, rien n'est rejoué. */
    public function testFormulaireDUnVisiteurRedirigeSansMessage(): void
    {
        $this->client->request('POST', '/mes-reservations/1/annuler');
        self::assertResponseRedirects('/connexion');

        $this->client->followRedirect();
        self::assertSelectorNotExists('[role="status"]');
    }

    private function assertStructureAccessible(Crawler $page): void
    {
        self::assertSame('fr', $page->filter('html')->attr('lang'), 'Langue de la page (RGAA 8.3)');
        self::assertSame('#contenu', $page->filter('body > a')->first()->attr('href'), 'Lien d\'évitement (RGAA 12.7)');
        self::assertCount(1, $page->filter('main#contenu'));
        self::assertCount(1, $page->filter('h1'), 'Un seul titre h1 (RGAA 9.1)');
        self::assertSame('true', $page->filter('svg')->ancestors()->filter('[aria-hidden="true"]')->first()->attr('aria-hidden'), 'Illustration décorative');
    }

    private function assertAucunDetailTechnique(): void
    {
        $html = (string) $this->client->getResponse()->getContent();
        foreach (['Exception', 'Stack', 'vendor/', 'src/'] as $detail) {
            self::assertStringNotContainsString($detail, $html, 'Aucun détail technique affiché');
        }
    }
}
