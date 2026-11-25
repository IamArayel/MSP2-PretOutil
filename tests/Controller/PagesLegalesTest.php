<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pages légales, acceptation des CGU à l'inscription et bandeau d'information sur les cookies (#106).
 * Chaque test s'exécute dans une transaction annulée à la fin.
 */
final class PagesLegalesTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connexion;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->connexion = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $this->connexion->beginTransaction();
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }

        parent::tearDown();
    }

    public function testPiedDePageMeneAuxPagesLegales(): void
    {
        $pied = $this->client->request('GET', '/')->filter('footer');

        self::assertCount(1, $pied->filter('a[href="/cgu"]'), 'Lien vers les CGU');
        self::assertCount(1, $pied->filter('a[href="/confidentialite"]'), 'Lien vers la politique de confidentialité');

        $this->client->click($pied->selectLink('Confidentialité et cookies')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Politique de confidentialité');
        self::assertSelectorExists('h2#cookies', 'Section cookies, cible du lien du bandeau');
    }

    public function testInscriptionRefuseeSansAcceptationDesCgu(): void
    {
        $this->client->request('GET', '/inscription');
        $page = $this->client->submitForm('Créer mon compte', $this->inscription('sans.cgu@pretoutil.test'));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#inscription_accepteCgu_erreurs', 'Vous devez accepter les conditions générales d\'utilisation');
        $case = $page->filter('#inscription_accepteCgu');
        self::assertSame('true', $case->attr('aria-invalid'), 'Case signalée en erreur');
        self::assertSame('inscription_accepteCgu_erreurs', $case->attr('aria-describedby'), 'Erreur reliée à la case (RGAA 11.10)');
        self::assertFalse($this->compteExiste('sans.cgu@pretoutil.test'), 'Aucun compte créé');
    }

    public function testInscriptionAccepteeAvecLesCgu(): void
    {
        $page = $this->client->request('GET', '/inscription');
        self::assertCount(1, $page->filter('label[for="inscription_accepteCgu"] a[href="/cgu"]'), 'Le libellé de la case mène aux CGU');

        $this->client->submitForm('Créer mon compte', $this->inscription('avec.cgu@pretoutil.test') + ['inscription[accepteCgu]' => true]);

        self::assertResponseRedirects('/connexion');
        self::assertTrue($this->compteExiste('avec.cgu@pretoutil.test'));
    }

    public function testBandeauCookiesMasqueSansJavaScript(): void
    {
        $bandeau = $this->client->request('GET', '/')->filter('#bandeau-cookies');

        self::assertCount(1, $bandeau);
        self::assertNotNull($bandeau->attr('hidden'), 'Affiché par le script seulement : sans JavaScript, il ne pourrait pas être fermé');
        self::assertCount(1, $bandeau->filter('a[href="/confidentialite#cookies"]'));
        self::assertCount(1, $bandeau->filter('button[data-fermer-bandeau]'));
    }

    /** Ce que dit la politique : pas de cookie pour consulter, le cookie de session vient avec un formulaire. */
    public function testCookieDeSessionDeposeSeulementQuandIlEstNecessaire(): void
    {
        foreach (['/', '/catalogue', '/cgu', '/confidentialite'] as $chemin) {
            $this->client->request('GET', $chemin);
            self::assertSame([], $this->client->getResponse()->headers->getCookies(), \sprintf('Aucun cookie sur %s', $chemin));
        }

        $this->client->request('GET', '/connexion');
        $noms = array_map(static fn ($cookie): string => $cookie->getName(), $this->client->getResponse()->headers->getCookies());
        self::assertCount(1, $noms, 'Un seul cookie : la session, qui porte le jeton CSRF du formulaire');
    }

    /** @return array<string, string> */
    private function inscription(string $email): array
    {
        return [
            'inscription[prenom]' => 'Camille',
            'inscription[nom]' => 'Légale',
            'inscription[email]' => $email,
            'inscription[motDePasse]' => 'MotDePasse-Cgu-2026',
        ];
    }

    private function compteExiste(string $email): bool
    {
        return false !== $this->connexion->fetchOne('SELECT 1 FROM utilisateur WHERE email = ?', [$email]);
    }
}
