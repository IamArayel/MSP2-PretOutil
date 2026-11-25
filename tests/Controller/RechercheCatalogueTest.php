<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Barre de recherche des écrans E3 et E7 (#109) : recherche sur le nom, sans tenir compte de la
 * casse ni des accents, combinée aux filtres, conservée dans la pagination. Le bouton Rechercher
 * fonctionne sans JavaScript. Base dans une transaction annulée à la fin.
 */
final class RechercheCatalogueTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connexion;
    private int $outillage;
    private int $jardin;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $entityManager->getConnection();
        $this->connexion->beginTransaction();

        $gestionnaire = (new Utilisateur())->setEmail('gestionnaire.recherche@pretoutil.test')->setNom('Bernard')->setPrenom('Léa')
            ->setMotDePasse('empreinte-non-utilisee')->setRoles(['ROLE_MANAGER']);
        $entityManager->persist($gestionnaire);
        $entityManager->flush();
        $this->client->loginUser($gestionnaire);

        $this->outillage = $this->categorie('Outillage (test recherche)');
        $this->jardin = $this->categorie('Jardin (test recherche)');
        $this->objet($this->outillage, 'Échelle télescopique');
        $this->objet($this->outillage, 'Échafaudage roulant');
        $this->objet($this->jardin, 'Échelle de jardin');
        $this->objet($this->outillage, 'Lot 100% vis');
        $this->objet($this->outillage, 'Marteau retiré', actif: false);
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }

        parent::tearDown();
    }

    public function testRechercheSansTenirCompteDeLaCasseNiDesAccents(): void
    {
        $page = $this->client->request('GET', '/catalogue?q=ECHELLE');

        self::assertResponseIsSuccessful();
        self::assertSame(['Échelle de jardin', 'Échelle télescopique'], $this->nomsDesCartes($page));
        self::assertSelectorTextContains('#total-catalogue', '2 objets à emprunter pour « ECHELLE »');
    }

    public function testRechercheCombineeAvecLaCategorie(): void
    {
        $page = $this->client->request('GET', \sprintf('/catalogue?q=echelle&categorie=%d', $this->jardin));

        self::assertSame(['Échelle de jardin'], $this->nomsDesCartes($page));
    }

    /** % et _ ne servent pas de jokers : « % » ne trouve que les noms qui contiennent ce caractère. */
    public function testCaracteresSpeciauxCherchesTelsQuels(): void
    {
        self::assertSame(['Lot 100% vis'], $this->nomsDesCartes($this->client->request('GET', '/catalogue?q=%25')));
        self::assertSame([], $this->nomsDesCartes($this->client->request('GET', '/catalogue?q=_')));
    }

    /** Texte cherché passé en paramètre lié (SQL) et échappé à l'affichage (XSS). */
    public function testSaisieMalveillanteSansEffet(): void
    {
        self::assertSame([], $this->nomsDesCartes($this->client->request('GET', '/catalogue?'.http_build_query(['q' => "' OR 1=1 --"]))));

        $this->client->request('GET', '/gestion/catalogue?'.http_build_query(['q' => '<script>alert(1)</script>']));
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertStringContainsString('« &lt;script&gt;alert(1)&lt;/script&gt; »', $html);
    }

    public function testObjetRetireAbsentDuCatalogue(): void
    {
        $this->client->request('GET', '/catalogue?q=marteau');

        self::assertSelectorTextContains('[role="status"]', 'Essayez un autre nom');
    }

    public function testRechercheTropLongueSignalee(): void
    {
        $this->client->request('GET', '/catalogue?q='.str_repeat('a', 101));

        // Formulaire invalide : 422, comme pour des dates invalides, et tout le catalogue est proposé.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#q_erreurs', 'La recherche ne peut pas dépasser 100 caractères.');
    }

    /** Sans JavaScript : champ de recherche étiqueté et bouton Rechercher dans le formulaire de recherche. */
    public function testBoutonRechercherPresentSurE3EtE7(): void
    {
        foreach (['/catalogue', '/gestion/catalogue'] as $url) {
            $page = $this->client->request('GET', $url);

            self::assertSame('Rechercher un objet', trim($page->filter('label[for="q"]')->text()), $url);
            self::assertCount(1, $page->filter('form[role="search"] input[type="search"][name="q"]'), $url);
            self::assertCount(1, $page->filter('form[role="search"] button[type="submit"]:contains("Rechercher")'), $url);
        }
    }

    public function testGestionTrouveAussiLesObjetsRetires(): void
    {
        $page = $this->client->request('GET', '/gestion/catalogue?q=MARTEAU');

        self::assertResponseIsSuccessful();
        self::assertSame(['Marteau retiré'], $page->filter('tbody th[scope="row"]')->each(static fn (Crawler $ligne): string => $ligne->text()));
        self::assertSelectorTextContains('#total-gestion-catalogue', '1 objet pour « MARTEAU »');
    }

    public function testGestionSansResultat(): void
    {
        $this->client->request('GET', '/gestion/catalogue?q=introuvable');

        self::assertSelectorTextContains('tbody', 'Aucun objet ne correspond à « introuvable ».');
    }

    public function testGestionPaginationConserveLaRecherche(): void
    {
        for ($i = 1; $i <= 21; ++$i) {
            $this->objet($this->jardin, \sprintf('Râteau %02d', $i));
        }

        $page = $this->client->request('GET', '/gestion/catalogue?q=rateau');

        self::assertSame('/gestion/catalogue?q=rateau&page=2', $page->filter('a[rel="next"]')->attr('href'));
        $page = $this->client->click($page->filter('a[rel="next"]')->link());
        self::assertSame(['Râteau 21'], $page->filter('tbody th[scope="row"]')->each(static fn (Crawler $ligne): string => $ligne->text()));
    }

    private function categorie(string $libelle): int
    {
        return (int) $this->connexion->fetchOne('INSERT INTO categorie (libelle) VALUES (?) RETURNING id', [$libelle]);
    }

    private function objet(int $categorie, string $nom, bool $actif = true): void
    {
        $this->connexion->executeStatement(
            'INSERT INTO objet (categorie_id, nom, description, actif) VALUES (?, ?, ?, ?)',
            [$categorie, $nom, 'Objet de test', $actif],
            [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::BOOLEAN],
        );
    }

    /** @return list<string> */
    private function nomsDesCartes(Crawler $page): array
    {
        return $page->filter('.grille-cartes h2')->each(static fn (Crawler $nom): string => $nom->text());
    }
}
