<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\StatutReservation;
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

    /** @var array<string, Utilisateur> profil => utilisateur connecté */
    private array $profils = [];

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
        $gestionnaire = (new Utilisateur())->setEmail('gestionnaire.a11y@pretoutil.test')->setNom('Bernard')->setPrenom('Léa')->setMotDePasse('empreinte-non-utilisee')->setRoles(['ROLE_MANAGER']);
        $administrateur = (new Utilisateur())->setEmail('admin.a11y@pretoutil.test')->setNom('Petit')->setPrenom('Sam')->setMotDePasse('empreinte-non-utilisee')->setRoles(['ROLE_ADMIN']);
        // Écrans de gestion avec des lignes : une sortie à valider (E6) et un retour attendu (E6), d'un autre adhérent.
        $emprunteur = (new Utilisateur())->setEmail('emprunteur.a11y@pretoutil.test')->setNom('Durand')->setPrenom('Noah')->setMotDePasse('empreinte-non-utilisee');
        $aSortir = new Reservation($emprunteur, (new Objet())->setCategorie($categorie)->setNom('Ponceuse')->setDescription('Objet de test'), new \DateTimeImmutable('today'), new \DateTimeImmutable('today +2 days'));
        $aRendre = (new Reservation($emprunteur, (new Objet())->setCategorie($categorie)->setNom('Escabeau')->setDescription('Objet de test'), new \DateTimeImmutable('today -2 days'), new \DateTimeImmutable('today +1 day')))
            ->enregistrerSortie($gestionnaire, new \DateTimeImmutable('-2 days'))
            ->setStatut(StatutReservation::EnCours);
        foreach ([$this->adherent, $gestionnaire, $administrateur, $emprunteur, $categorie, $this->objet, $reservation, $aSortir->getObjet(), $aSortir, $aRendre->getObjet(), $aRendre] as $entite) {
            $this->entityManager->persist($entite);
        }
        $this->entityManager->flush();
        $this->profils = ['adherent' => $this->adherent, 'gestionnaire' => $gestionnaire, 'administrateur' => $administrateur];
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
    private const array OBJET_VIDE = ['objet' => ['nom' => '', 'description' => '']];

    /** @return iterable<string, array{string, string, ?string, array<string, mixed>}> écran => [méthode, chemin, profil connecté, données envoyées] */
    public static function ecrans(): iterable
    {
        yield 'Accueil' => ['GET', '/', null, []];
        yield 'E1 Connexion' => ['GET', '/connexion', null, []];
        yield 'E2 Inscription' => ['GET', '/inscription', null, []];
        yield 'E2 Inscription, erreurs' => ['POST', '/inscription', null, self::INSCRIPTION_INVALIDE];
        yield 'E3 Catalogue' => ['GET', '/catalogue', null, []];
        yield 'E3 Catalogue, filtres invalides' => ['GET', '/catalogue?categorie=999999&debut=x', null, []];
        yield 'E4 Fiche, visiteur' => ['GET', '/catalogue/{objet}', null, []];
        yield 'E4 Fiche, adhérent' => ['GET', '/catalogue/{objet}', 'adherent', []];
        yield 'E4 Réservation, erreurs' => ['POST', '/catalogue/{objet}/reserver', 'adherent', self::RESERVATION_VIDE];
        yield 'E5 Mes réservations' => ['GET', '/mes-reservations', 'adherent', []];
        yield from self::ecransDeGestion();
    }

    /** @return iterable<string, array{string, string, ?string, array<string, mixed>}> */
    public static function ecransDeGestion(): iterable
    {
        yield 'E6 Sorties et retours' => ['GET', '/gestion/sorties-retours', 'gestionnaire', []];
        yield 'E7 Gestion du catalogue' => ['GET', '/gestion/catalogue', 'gestionnaire', []];
        yield 'E7 Ajouter un objet' => ['GET', '/gestion/catalogue/nouveau', 'gestionnaire', []];
        yield 'E7 Ajouter un objet, erreurs' => ['POST', '/gestion/catalogue/nouveau', 'gestionnaire', self::OBJET_VIDE];
        yield 'E7 Modifier un objet' => ['GET', '/gestion/catalogue/{objet}/modifier', 'gestionnaire', []];
        yield 'E8 Gestion des comptes' => ['GET', '/admin/comptes', 'administrateur', []];
        yield 'E9 Journal d\'audit' => ['GET', '/admin/journal', 'administrateur', []];
        yield 'E9 Journal, filtre inconnu' => ['GET', '/admin/journal?action=INCONNUE', 'administrateur', []];
    }

    #[DataProvider('ecrans')]
    /** @param array<string, mixed> $donnees */
    public function testCriteresDeStructure(string $methode, string $chemin, ?string $profil, array $donnees): void
    {
        $page = $this->ouvrir($methode, $chemin, $profil, $donnees);

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

        $page->filter('img')->each(static function (Crawler $image): void {
            self::assertNotNull($image->attr('alt'), \sprintf('Alternative textuelle de %s, vide si décorative (RGAA 1.1, 1.2)', $image->attr('src')));
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

    /**
     * Nom accessible d'un lien ou d'un bouton : il contient le texte visible, pour qu'un
     * utilisateur de commande vocale puisse dire ce qu'il voit (RGAA 6.1, WCAG 2.5.3).
     */
    #[DataProvider('ecrans')]
    /** @param array<string, mixed> $donnees */
    public function testNomAccessibleContientLeTexteVisible(string $methode, string $chemin, ?string $profil, array $donnees): void
    {
        $page = $this->ouvrir($methode, $chemin, $profil, $donnees);

        $page->filter('a[aria-label], button[aria-label]')->each(function (Crawler $element): void {
            $visible = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $element->text())));
            $nom = mb_strtolower((string) $element->attr('aria-label'));
            self::assertStringContainsString($visible, $nom, \sprintf('« %s » doit contenir le texte visible « %s »', $nom, $visible));
        });
        $this->addToAssertionCount(1);
    }

    /**
     * Écrans de gestion : un tableau large défile dans son cadre, pas la page (affichage
     * mobile), et ce cadre s'atteint au clavier pour le faire défiler (RGAA 7.3, 10.11).
     */
    #[DataProvider('ecransDeGestion')]
    /** @param array<string, mixed> $donnees */
    public function testTableauxDefilentDansLeurCadre(string $methode, string $chemin, ?string $profil, array $donnees): void
    {
        $page = $this->ouvrir($methode, $chemin, $profil, $donnees);

        $page->filter('table')->each(static function (Crawler $tableau): void {
            $cadre = $tableau->ancestors()->first();
            self::assertStringContainsString('cadre-tableau', (string) $cadre->attr('class'), 'Tableau dans un cadre défilant');
            self::assertStringContainsString('tableau', (string) $tableau->attr('class'), 'Largeur minimale du tableau');
            self::assertSame('0', $cadre->attr('tabindex'), 'Cadre atteignable au clavier');
            self::assertSame('region', $cadre->attr('role'));
            self::assertTrue(null !== $cadre->attr('aria-label') || null !== $cadre->attr('aria-labelledby'), 'Cadre nommé');
        });
        $this->addToAssertionCount(1);
    }

    /**
     * Contour de chaque liste de choix contrasté à 3:1 au moins (RGAA 3.3) : composants champ
     * ou liste-choix (couleur champ, 4,77:1), champ-erreur (rouille, 5,01:1) en erreur.
     */
    #[DataProvider('ecrans')]
    /** @param array<string, mixed> $donnees */
    public function testListesDeChoixContrastees(string $methode, string $chemin, ?string $profil, array $donnees): void
    {
        $page = $this->ouvrir($methode, $chemin, $profil, $donnees);

        $page->filter('select')->each(static function (Crawler $liste): void {
            self::assertMatchesRegularExpression('/(^|\s)(liste-choix|champ)(\s|$)/', (string) $liste->attr('class'), \sprintf('Contour de la liste %s', $liste->attr('id')));
        });
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function pagesDeGestion(): iterable
    {
        yield 'E6' => ['/gestion/sorties-retours', 'gestionnaire', 'Sorties et retours'];
        yield 'E7' => ['/gestion/catalogue', 'gestionnaire', 'Gestion du catalogue'];
        yield 'E8' => ['/admin/comptes', 'administrateur', 'Gestion des comptes'];
        yield 'E9' => ['/admin/journal', 'administrateur', 'Journal d\'audit'];
    }

    #[DataProvider('pagesDeGestion')]
    public function testPageDeGestionSignaleeDansLaNavigation(string $chemin, string $profil, string $lien): void
    {
        $this->ouvrir('GET', $chemin, $profil, []);

        self::assertSelectorTextSame('nav[aria-label="Navigation principale"] [aria-current="page"]', $lien);
    }

    /**
     * Logo : l'icône de l'en-tête est décorative (le nom est écrit à côté, il n'est pas lu deux
     * fois) ; le logo de l'accueil contient du texte, repris dans son alternative (RGAA 1.3).
     */
    public function testLogoEtFavicon(): void
    {
        $page = $this->client->request('GET', '/');

        self::assertSame('', $page->filter('header a[href="/"] img')->attr('alt'));
        self::assertSame("Prêt'Outil", $page->filter('header a[href="/"]')->text());
        self::assertSame("Prêt'Outil, le prêt d'outils partagé", $page->filter('main img')->attr('alt'));
        self::assertCount(1, $page->filter('link[rel="icon"][type="image/svg+xml"]'), 'Favicon du logo');
        self::assertCount(1, $page->filter('footer'), 'Pied de page commun');
    }

    /**
     * Photo de l'objet sur sa fiche (chargée en priorité) et sur sa carte du catalogue (chargée
     * à l'approche) ; alternative vide, le nom est écrit à côté. Sans photo : vignette décorative.
     */
    public function testPhotoDeLObjet(): void
    {
        $this->objet->setImage('perceuse.webp');
        $this->entityManager->flush();

        $fiche = $this->client->request('GET', \sprintf('/catalogue/%d', $this->objet->getId()));
        $photo = $fiche->filter('main article img[src*="/images/objets/perceuse"]');
        self::assertSame(['', 'eager', 'high'], [$photo->attr('alt'), $photo->attr('loading'), $photo->attr('fetchpriority')]);

        $catalogue = $this->client->request('GET', '/catalogue?categorie='.$this->objet->getCategorie()->getId());
        self::assertSame('lazy', $catalogue->filter('main li img[src*="/images/objets/perceuse"]')->attr('loading'));
        self::assertGreaterThan(0, $catalogue->filter('main li div[aria-hidden="true"] img[src*="logo-icone"]')->count(), 'Vignette des objets sans photo');
    }

    /**
     * Charte (#95) : les gabarits et les scripts de assets/ n'emploient que les couleurs nommées de la charte (primary, ink,
     * danger, statut-*…), dont les contrastes sont calculés ; aucune couleur Tailwind brute
     * (teal-700, red-200, text-white…) qui échapperait à ce calcul.
     */
    public function testAucuneCouleurTailwindBruteDansLesGabarits(): void
    {
        $palette = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
        $motif = '/\b(?:bg|text|border|ring|outline|fill|stroke|from|via|to|divide|decoration|shadow|accent|caret)-(?:(?:'.$palette.')-\d{2,3}|white|black)\b/';
        $racine = \dirname(__DIR__, 2);
        $gabarits = (new \Symfony\Component\Finder\Finder())->files()->in([$racine.'/templates', $racine.'/assets'])->name(['*.twig', '*.js']);

        foreach ($gabarits as $gabarit) {
            preg_match_all($motif, $gabarit->getContents(), $trouvees);
            self::assertSame([], $trouvees[0], $gabarit->getRelativePathname());
        }
    }

    /** @param array<string, mixed> $donnees */
    private function ouvrir(string $methode, string $chemin, ?string $profil, array $donnees): Crawler
    {
        if (null !== $profil) {
            $this->client->loginUser($this->profils[$profil]);
        }
        $page = $this->client->request($methode, str_replace('{objet}', (string) $this->objet->getId(), $chemin), $donnees);
        self::assertLessThan(500, $this->client->getResponse()->getStatusCode());
        self::assertFalse($this->client->getResponse()->isRedirection(), 'Écran affiché, sans redirection');

        return $page;
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
