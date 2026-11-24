<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Finder\Finder;

/**
 * En-têtes de sécurité HTTP (EntetesSecuriteSubscriber), constat de la veille OWASP
 * (docs/securite/veille-owasp.md, A02) : chaque test échoue si l'en-tête est retiré.
 *
 * Pages publiques uniquement : aucune donnée n'est nécessaire en base.
 */
final class EntetesSecuriteTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Limite de débit de l'API remise à zéro (compteurs conservés sur disque en test).
        self::getContainer()->get('cache.limiteur_api')->clear();
    }

    /** @return iterable<string, array{string}> */
    public static function pages(): iterable
    {
        yield 'accueil' => ['/'];
        yield 'catalogue (E3)' => ['/catalogue'];
        yield 'connexion (E1)' => ['/connexion'];
        yield 'inscription (E2)' => ['/inscription'];
    }

    #[DataProvider('pages')]
    public function testEntetesPresentsSurChaquePage(string $page): void
    {
        $this->client->request('GET', $page);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
        self::assertResponseHasHeader('Permissions-Policy');

        $csp = (string) $this->client->getResponse()->headers->get('Content-Security-Policy');
        foreach (["default-src 'self'", "object-src 'none'", "frame-ancestors 'none'", "form-action 'self'", "base-uri 'self'"] as $directive) {
            self::assertStringContainsString($directive, $csp);
        }
        self::assertStringNotContainsString('unsafe-inline', $csp, 'Aucun script ni style en ligne autorisé sans nonce');
        self::assertStringNotContainsString('unsafe-eval', $csp);
    }

    /** Chaque <script> de la page porte le nonce annoncé dans l'en-tête : sinon il serait bloqué. */
    #[DataProvider('pages')]
    public function testChaqueScriptPorteLeNonceDeLaRequete(string $page): void
    {
        $crawler = $this->client->request('GET', $page);

        $nonce = $this->nonce();
        $scripts = $crawler->filter('script');
        self::assertGreaterThan(0, $scripts->count(), 'Scripts de l\'importmap présents');
        foreach ($scripts as $script) {
            self::assertSame($nonce, $script->getAttribute('nonce'), 'Script sans le nonce de la requête');
        }
    }

    /** Un nonce réutilisé pourrait être recopié par un attaquant dans un script injecté. */
    public function testNonceDifferentAChaqueRequete(): void
    {
        $this->client->request('GET', '/catalogue');
        $premier = $this->nonce();
        $this->client->request('GET', '/catalogue');

        self::assertGreaterThanOrEqual(16, \strlen(base64_decode($premier, true) ?: ''), '16 octets aléatoires au moins');
        self::assertNotSame($premier, $this->nonce());
    }

    /** HSTS : envoyé en HTTPS seulement (en HTTP, le navigateur l'ignorerait). */
    public function testHstsEnHttpsSeulement(): void
    {
        $this->client->request('GET', '/catalogue', server: ['HTTPS' => 'on']);
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Adresse complète : une adresse relative reprendrait le https de la requête précédente.
        $this->client->request('GET', 'http://localhost/catalogue');
        self::assertResponseNotHasHeader('Strict-Transport-Security');
    }

    /** Les réponses JSON de l'API portent aussi les en-têtes. */
    public function testEntetesSurLApi(): void
    {
        $this->client->request('GET', '/api/objets/999999/disponibilites');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHasHeader('Content-Security-Policy');
    }

    /**
     * Aucun gestionnaire d'évènement écrit dans le HTML (onsubmit, onclick...) : la CSP
     * le bloquerait, et la confirmation des actions irréversibles ne s'afficherait plus.
     * Les formulaires utilisent data-confirmation (assets/confirmation.js).
     */
    public function testAucunJavaScriptEnLigneDansLesGabarits(): void
    {
        $gabarits = (new Finder())->files()->in(\dirname(__DIR__, 2).'/templates')->name('*.twig');
        self::assertTrue($gabarits->hasResults());

        foreach ($gabarits as $gabarit) {
            self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $gabarit->getContents(), $gabarit->getRelativePathname());
            self::assertStringNotContainsString('javascript:', $gabarit->getContents(), $gabarit->getRelativePathname());
        }
    }

    private function nonce(): string
    {
        $csp = (string) $this->client->getResponse()->headers->get('Content-Security-Policy');
        self::assertMatchesRegularExpression("/script-src 'nonce-([A-Za-z0-9+\\/=]+)' 'strict-dynamic'/", $csp);
        preg_match("/'nonce-([A-Za-z0-9+\\/=]+)'/", $csp, $correspondance);

        return $correspondance[1];
    }
}
