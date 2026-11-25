<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Enum\StatutReservation;
use App\Service\AuditLogger;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests de sécurité (ticket #66) : chaque protection est démontrée par une vraie requête
 * HTTP, et chaque test échouerait si la protection était retirée.
 *
 * - CSRF : chaque formulaire en POST, jeton absent ou faux, rien n'est écrit ; le même
 *   envoi avec le vrai jeton aboutit (le refus vient donc bien du jeton) ;
 * - injection SQL : filtres du catalogue, paramètres d'URL, saisies de l'inscription ;
 * - injection NoSQL : filtre du journal d'audit (E9) ;
 * - XSS : nom et description d'objet piégés sur E3, E4, E7, E9 et dans l'API ;
 * - limitation des tentatives de connexion, empreinte du mot de passe.
 *
 * Chaque test s'exécute dans une transaction annulée à la fin : la base reste vide.
 * Prérequis : bases de test créées et migrées (README, section Tests).
 */
final class SecuriteTest extends WebTestCase
{
    private const string MOT_DE_PASSE = 'BonMotDePasse!2026';
    private const string EMAIL_ADHERENT = 'adherent.securite@pretoutil.test';
    private const string XSS_NOM = '<script>alert(1)</script>';
    private const string XSS_DESCRIPTION = '<img src=x onerror=alert(2)>';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private Connection $connexion;

    /** @var array<string, Utilisateur> */
    private array $utilisateurs = [];

    /** @var array<string, string> marqueur des cas => valeur (identifiants, dates) */
    private array $valeurs = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Même noyau pour toutes les requêtes du test : même connexion, donc même transaction.
        $this->client->disableReboot();
        // Compteurs des tentatives de connexion et de l'API remis à zéro (conservés sur disque).
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.limiteur_api')->clear();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $this->entityManager->getConnection();
        $this->connexion->beginTransaction();

        $adherent = $this->utilisateur(self::EMAIL_ADHERENT, []);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $adherent->setMotDePasse($hasher->hashPassword($adherent, self::MOT_DE_PASSE));
        $this->utilisateurs = [
            'adherent' => $adherent,
            'gestionnaire' => $this->utilisateur('gestionnaire.securite@pretoutil.test', ['ROLE_MANAGER']),
            'administrateur' => $this->utilisateur('admin.securite@pretoutil.test', ['ROLE_ADMIN']),
        ];

        $categorie = (new Categorie())->setLibelle('Bricolage (test sécurité)');
        $autreCategorie = (new Categorie())->setLibelle('Jardin (test sécurité)');
        $this->entityManager->persist($categorie);
        $this->entityManager->persist($autreCategorie);

        $aReserver = $this->objet($categorie, 'Scie sauteuse à réserver');
        $libre = $this->objet($categorie, 'Ponceuse jamais réservée');
        $retire = $this->objet($categorie, 'Tronçonneuse retirée')->setActif(false);
        $this->objet($autreCategorie, 'Tondeuse d\'une autre catégorie');

        // Réservation à venir (annulable), qui commence aujourd'hui (sortie), en cours (retour).
        $aAnnuler = new Reservation($adherent, $this->objet($categorie, 'Perceuse à annuler'), $this->jour(7), $this->jour(9));
        // Sortie et retour : un autre adhérent, pour laisser à celui-ci de la place dans son quota (RG4).
        $autreAdherent = $this->utilisateur('autre.securite@pretoutil.test', []);
        $aSortir = new Reservation($autreAdherent, $this->objet($categorie, 'Visseuse à sortir'), $this->jour(0), $this->jour(2));
        $aRendre = (new Reservation($autreAdherent, $this->objet($categorie, 'Meuleuse à rendre'), $this->jour(-2), $this->jour(1)))
            ->enregistrerSortie($this->utilisateurs['gestionnaire'], new \DateTimeImmutable('-2 days'))
            ->setStatut(StatutReservation::EnCours);
        foreach ([$aAnnuler, $aSortir, $aRendre] as $reservation) {
            $this->entityManager->persist($reservation);
        }
        $this->entityManager->flush();

        $this->valeurs = [
            '{categorie}' => (string) $categorie->getId(),
            '{autreCategorie}' => (string) $autreCategorie->getId(),
            '{objetAReserver}' => (string) $aReserver->getId(),
            '{objetLibre}' => (string) $libre->getId(),
            '{objetRetire}' => (string) $retire->getId(),
            '{aAnnuler}' => (string) $aAnnuler->getId(),
            '{aSortir}' => (string) $aSortir->getId(),
            '{aRendre}' => (string) $aRendre->getId(),
            '{J+20}' => $this->jour(20)->format('Y-m-d'),
            '{J+21}' => $this->jour(21)->format('Y-m-d'),
        ];
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------ CSRF

    /**
     * Formulaire => [profil connecté, page qui l'affiche, sélecteur du formulaire,
     * champ du jeton, valeurs saisies, état avant, état après un envoi valide].
     * L'état est lu par observer() : il ne change que si l'action a eu lieu.
     */
    private const array FORMULAIRES = [
        'réserver (E4)' => ['adherent', '/catalogue/{objetAReserver}', 'form[name="reservation"]', 'reservation[_token]',
            ['reservation' => ['debut' => '{J+20}', 'fin' => '{J+21}']], '0', '1'],
        'annuler (E5)' => ['adherent', '/mes-reservations', 'form[action="/mes-reservations/{aAnnuler}/annuler"]', '_token',
            [], 'confirmee', 'annulee'],
        'valider une sortie (E6)' => ['gestionnaire', '/gestion/sorties-retours', 'form[action="/gestion/sorties-retours/{aSortir}/sortie"]', '_token',
            [], 'confirmee', 'en_cours'],
        'enregistrer un retour (E6)' => ['gestionnaire', '/gestion/sorties-retours', 'form[action="/gestion/sorties-retours/{aRendre}/retour"]', '_token',
            ['etat' => 'abime'], 'en_cours', 'terminee'],
        'désactiver un objet (E7)' => ['gestionnaire', '/gestion/catalogue', 'form[action="/gestion/catalogue/{objetLibre}/desactiver"]', '_token',
            [], 'actif', 'inactif'],
        'supprimer un objet (E7)' => ['gestionnaire', '/gestion/catalogue', 'form[action="/gestion/catalogue/{objetLibre}/supprimer"]', '_token',
            [], '1', '0'],
        // Recherche : la ligne d'un objet retiré est en fin de liste, peut-être sur une autre page.
        'réactiver un objet (E7)' => ['gestionnaire', '/gestion/catalogue?q=tronconneuse', 'form[action="/gestion/catalogue/{objetRetire}/reactiver"]', '_token',
            [], 'inactif', 'actif'],
        'créer un objet (E7)' => ['gestionnaire', '/gestion/catalogue/nouveau', 'form[name="objet"]', 'objet[_token]',
            ['objet' => ['categorie' => '{categorie}', 'nom' => 'Objet créé (test CSRF)', 'description' => 'Test CSRF', 'etat' => 'bon']], '0', '1'],
        'modifier un objet (E7)' => ['gestionnaire', '/gestion/catalogue/{objetLibre}/modifier', 'form[name="objet"]', 'objet[_token]',
            ['objet' => ['nom' => 'Nom modifié (test CSRF)']], 'Ponceuse jamais réservée', 'Nom modifié (test CSRF)'],
        'inscription (E2)' => [null, '/inscription', 'form[name="inscription"]', 'inscription[_token]',
            ['inscription' => ['prenom' => 'Camille', 'nom' => 'Csrf', 'email' => 'csrf.inscription@pretoutil.test', 'motDePasse' => 'MotDePasse-Csrf-2026', 'accepteCgu' => '1']], '0', '1'],
        'connexion (E1)' => [null, '/connexion', 'form[action="/connexion"]', '_csrf_token',
            ['email' => self::EMAIL_ADHERENT, 'mot_de_passe' => self::MOT_DE_PASSE], 'anonyme', 'connecté'],
    ];

    /** @return iterable<string, array{string, string}> */
    public static function casCsrfRefuses(): iterable
    {
        foreach (array_keys(self::FORMULAIRES) as $formulaire) {
            yield $formulaire.', jeton absent' => [$formulaire, 'absent'];
            yield $formulaire.', jeton faux' => [$formulaire, 'faux'];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function formulaires(): iterable
    {
        foreach (array_keys(self::FORMULAIRES) as $formulaire) {
            yield $formulaire => [$formulaire];
        }
    }

    #[DataProvider('casCsrfRefuses')]
    public function testJetonCsrfAbsentOuFauxRefuse(string $formulaire, string $jeton): void
    {
        [, , , , , $avant] = self::FORMULAIRES[$formulaire];
        self::assertSame($avant, $this->observer($formulaire), 'État de départ');

        $this->envoyer($formulaire, $jeton);

        self::assertLessThan(500, $this->client->getResponse()->getStatusCode(), 'Refus propre, sans erreur serveur');
        self::assertSame($avant, $this->observer($formulaire), 'Rien n\'est écrit en base');
    }

    /** Témoin : le même envoi avec le vrai jeton aboutit. Le refus ci-dessus vient donc du jeton seul. */
    #[DataProvider('formulaires')]
    public function testJetonCsrfValideAccepte(string $formulaire): void
    {
        [, , , , , , $apres] = self::FORMULAIRES[$formulaire];

        $this->envoyer($formulaire, 'valide');

        self::assertSame($apres, $this->observer($formulaire), 'Action effectuée avec le vrai jeton');
    }

    // ------------------------------------------------------------ Injection SQL

    /** @return iterable<string, array{string, string}> */
    public static function filtresInjectes(): iterable
    {
        yield 'catégorie, OR 1=1' => ['categorie', '\' OR 1=1 --'];
        yield 'catégorie, identifiant suivi de OR 1=1' => ['categorie', '{categorie} OR 1=1'];
        yield 'catégorie, requête empilée' => ['categorie', '{categorie}; DROP TABLE objet; --'];
        yield 'catégorie, joker %' => ['categorie', '%'];
        yield 'catégorie, joker _' => ['categorie', '_'];
        yield 'date, joker %' => ['debut', '%'];
        yield 'date, OR 1=1 seul' => ['debut', '\' OR \'1\'=\'1'];
    }

    /**
     * Saisie malveillante dans un filtre de E3 : valeur refusée par le formulaire avec un
     * message, sans erreur SQL, sans filtre détourné, tables intactes.
     */
    #[DataProvider('filtresInjectes')]
    public function testFiltreDuCatalogueInjecteRefuse(string $champ, string $saisie): void
    {
        $parametres = [$champ => $this->remplacer($saisie)];
        if ('debut' === $champ) {
            $parametres['fin'] = $this->valeurs['{J+21}'];
        }
        $objetsAvant = (int) $this->connexion->fetchOne('SELECT COUNT(*) FROM objet');

        $this->client->request('GET', '/catalogue', $parametres);

        // 422 : formulaire de filtres invalide, réaffiché avec le message d'erreur.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'categorie' === $champ ? 'Cette catégorie n\'existe pas.' : 'Cette date n\'est pas valide.');
        self::assertStringNotContainsStringIgnoringCase('SQLSTATE', (string) $this->client->getResponse()->getContent());
        self::assertSame($objetsAvant, (int) $this->connexion->fetchOne('SELECT COUNT(*) FROM objet'), 'Table objet intacte');
    }

    /**
     * Date suivie de SQL : seule la date est lue (conversion en objet date par le formulaire),
     * le reste n'atteint jamais la requête. Résultats identiques à ceux de la date seule.
     */
    public function testDateSuivieDeSqlLueCommeUneDateSeule(): void
    {
        $fin = $this->valeurs['{J+21}'];
        $this->client->request('GET', '/catalogue', ['debut' => $this->valeurs['{J+20}'], 'fin' => $fin]);
        $attendu = $this->client->getCrawler()->filter('main h2')->each(static fn ($titre): string => $titre->text());

        $this->client->request('GET', '/catalogue', ['debut' => $this->valeurs['{J+20}'].'\' OR \'1\'=\'1', 'fin' => $fin]);

        self::assertResponseIsSuccessful();
        self::assertNotEmpty($attendu);
        self::assertSame($attendu, $this->client->getCrawler()->filter('main h2')->each(static fn ($titre): string => $titre->text()), 'Aucun résultat en plus');
        self::assertStringNotContainsStringIgnoringCase('SQLSTATE', (string) $this->client->getResponse()->getContent());
    }

    /** Témoin : un filtre valide ne renvoie que les objets de la catégorie choisie. */
    public function testFiltreDuCatalogueValideRestreintLesResultats(): void
    {
        $this->client->request('GET', '/catalogue', ['categorie' => $this->valeurs['{categorie}']]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Scie sauteuse à réserver');
        self::assertSelectorTextNotContains('main', 'Tondeuse d\'une autre catégorie');
    }

    /** @return iterable<string, array{string}> */
    public static function urlsInjectees(): iterable
    {
        yield 'numéro de page du catalogue' => ['/catalogue?page=1%20OR%201=1'];
        yield 'numéro de page de E7' => ['/gestion/catalogue?page=1;DROP%20TABLE%20objet'];
        yield 'identifiant d\'objet' => ['/catalogue/1%20OR%201=1'];
    }

    /** Paramètre d'URL typé (entier) : une saisie non numérique est refusée avant toute requête. */
    #[DataProvider('urlsInjectees')]
    public function testParametreDUrlInjecteRefuse(string $url): void
    {
        $this->client->loginUser($this->utilisateurs['gestionnaire']);
        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(404);
    }

    /** Paramètres de l'API : format AAAA-MM-JJ exigé, 400 sans erreur SQL. */
    public function testParametreDeLApiInjecteRefuse(): void
    {
        $this->client->request('GET', '/api/objets/'.$this->valeurs['{objetAReserver}'].'/disponibilites', [
            'debut' => '2026-12-01\' OR \'1\'=\'1',
            'fin' => '2026-12-02',
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertStringNotContainsStringIgnoringCase('SQLSTATE', (string) $this->client->getResponse()->getContent());
    }

    /**
     * Requêtes paramétrées : une saisie qui contient du SQL est enregistrée telle quelle.
     * Concaténée dans la requête, elle provoquerait une erreur de syntaxe (500).
     */
    public function testSaisieContenantDuSqlEnregistreeTelleQuelle(): void
    {
        $prenom = 'Robert\'); DROP TABLE utilisateur; --';
        $nom = '\' OR \'1\'=\'1';

        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer mon compte', [
            'inscription[prenom]' => $prenom,
            'inscription[nom]' => $nom,
            'inscription[email]' => 'sql.inscription@pretoutil.test',
            'inscription[motDePasse]' => 'MotDePasse-Sql-2026',
            'inscription[accepteCgu]' => true,
        ]);

        self::assertResponseRedirects('/connexion');
        $ligne = $this->connexion->fetchAssociative('SELECT prenom, nom FROM utilisateur WHERE email = ?', ['sql.inscription@pretoutil.test']);
        self::assertSame(['prenom' => $prenom, 'nom' => $nom], $ligne, 'Saisie stockée comme une simple valeur');
    }

    // ---------------------------------------------------------- Injection NoSQL

    /** @return iterable<string, array{string, int}> */
    public static function filtresDuJournalInjectes(): iterable
    {
        // Tableau dans l'URL : PHP le transmettrait à MongoDB comme un opérateur.
        yield 'action[$ne]=x' => ['action[$ne]=x', 404];
        yield 'action[$gt]=' => ['action[$gt]=', 404];
        yield 'action[$regex]=.*' => ['action[$regex]=.*', 404];
        // Opérateur dans une chaîne : valeur inconnue de l'énumération, filtre ignoré.
        yield 'action=$ne' => ['action=$ne', 200];
        yield 'action={"$ne":null}' => ['action='.rawurlencode('{"$ne":null}'), 200];
    }

    /**
     * Filtre de E9 avec un opérateur MongoDB : refusé (404) ou ignoré avec un message,
     * jamais transmis à MongoDB (le filtre passe par l'énumération ActionAudit).
     */
    #[DataProvider('filtresDuJournalInjectes')]
    public function testFiltreDuJournalInjecteRefuse(string $requete, int $attendu): void
    {
        $this->client->loginUser($this->utilisateurs['administrateur']);
        $this->client->request('GET', '/admin/journal?'.$requete);

        self::assertResponseStatusCodeSame($attendu);
        if (200 === $attendu) {
            self::assertSelectorTextContains('main', 'Type d\'action inconnu : toutes les actions sont affichées.');
        }
    }

    // ---------------------------------------------------------------------- XSS

    /** Nom et description piégés : affichés comme du texte sur E3, E4 et E7. */
    public function testObjetPiegeEchappeSurLesPages(): void
    {
        $id = $this->objetPiege();

        foreach (['/catalogue', '/catalogue/'.$id, '/gestion/catalogue'] as $page) {
            $this->client->loginUser($this->utilisateurs['gestionnaire']);
            $this->client->request('GET', $page);
            $html = (string) $this->client->getResponse()->getContent();

            self::assertResponseIsSuccessful($page);
            self::assertStringNotContainsString(self::XSS_NOM, $html, $page.' : balise <script> exécutable');
            self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html, $page.' : nom affiché échappé');
        }

        // La description n'est affichée en entier que sur la fiche (E4)
        $this->client->request('GET', '/catalogue/'.$id);
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString(self::XSS_DESCRIPTION, $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(2)&gt;', $html);
    }

    /** Détail du journal (E9) construit à partir du nom de l'objet : échappé lui aussi. */
    public function testObjetPiegeEchappeDansLeJournal(): void
    {
        $id = $this->objetPiege();
        self::getContainer()->get(AuditLogger::class)->journaliser(
            ActionAudit::ObjetCree,
            $this->utilisateurs['gestionnaire'],
            'objet',
            $id,
            ['nom' => self::XSS_NOM, 'categorie' => 'Bricolage (test sécurité)'],
        );

        $this->client->loginUser($this->utilisateurs['administrateur']);
        $this->client->request('GET', '/admin/journal?action=OBJET_CREE');
        $html = (string) $this->client->getResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(self::XSS_NOM, $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    /** API : les caractères < et > sont encodés en \u003C et \u003E, la valeur décodée est intacte. */
    public function testObjetPiegeEchappeDansLApi(): void
    {
        $id = $this->objetPiege();

        $this->client->request('GET', '/api/objets/'.$id.'/disponibilites');
        $json = (string) $this->client->getResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertStringNotContainsString('<script>', $json, 'Aucune balise brute dans la réponse');
        self::assertStringContainsString(trim(json_encode('<script>', \JSON_HEX_TAG), '"'), $json, 'Balise encodée (\\u003C, \\u003E)');
        self::assertSame(self::XSS_NOM, json_decode($json, true, flags: \JSON_THROW_ON_ERROR)['objet']['nom'], 'Donnée restituée sans altération');
    }

    // ------------------------------------------- Limitation des tentatives (US2)

    /** Après 5 échecs, même le bon mot de passe est refusé : une attaque par force brute est bloquée. */
    public function testConnexionBloqueeApresCinqEchecs(): void
    {
        for ($essai = 1; $essai <= 5; ++$essai) {
            $this->seConnecter(self::EMAIL_ADHERENT, 'mauvais-mot-de-passe-'.$essai);
            self::assertFalse($this->estConnecte(), 'Échec n° '.$essai);
        }

        $this->seConnecter(self::EMAIL_ADHERENT, self::MOT_DE_PASSE);

        self::assertSelectorTextContains('body', 'Trop de tentatives de connexion.');
        self::assertFalse($this->estConnecte(), 'Bon mot de passe refusé pendant le blocage');
    }

    /** Témoin : sous le seuil, le bon mot de passe ouvre la session. */
    public function testConnexionPossibleSousLeSeuil(): void
    {
        for ($essai = 1; $essai <= 4; ++$essai) {
            $this->seConnecter(self::EMAIL_ADHERENT, 'mauvais-mot-de-passe-'.$essai);
        }

        $this->seConnecter(self::EMAIL_ADHERENT, self::MOT_DE_PASSE);

        self::assertTrue($this->estConnecte());
    }

    /** Dictionnaire de données : la dernière activité est la dernière connexion ou réservation. */
    public function testConnexionReussieEnregistreLaDerniereActivite(): void
    {
        $derniereActivite = fn (): mixed => $this->connexion->fetchOne('SELECT derniere_activite_le FROM utilisateur WHERE email = ?', [self::EMAIL_ADHERENT]);

        $this->seConnecter(self::EMAIL_ADHERENT, 'mauvais-mot-de-passe');
        self::assertNull($derniereActivite(), 'Un échec ne compte pas comme une activité');

        $this->seConnecter(self::EMAIL_ADHERENT, self::MOT_DE_PASSE);
        self::assertNotNull($derniereActivite());
    }

    // -------------------------------------------------------------- Mot de passe

    /** Le mot de passe saisi à l'inscription n'est jamais stocké : seule son empreinte l'est. */
    public function testMotDePasseStockeSousFormeDEmpreinte(): void
    {
        $motDePasse = 'MotDePasse-EnClair-2026';

        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer mon compte', [
            'inscription[prenom]' => 'Camille',
            'inscription[nom]' => 'Empreinte',
            'inscription[email]' => 'empreinte@pretoutil.test',
            'inscription[motDePasse]' => $motDePasse,
            'inscription[accepteCgu]' => true,
        ]);
        self::assertResponseRedirects('/connexion');

        $empreinte = (string) $this->connexion->fetchOne('SELECT mot_de_passe FROM utilisateur WHERE email = ?', ['empreinte@pretoutil.test']);

        self::assertStringNotContainsString($motDePasse, $empreinte, 'Jamais en clair');
        self::assertMatchesRegularExpression('/^\$(2y|argon2id?)\$/', $empreinte, 'Empreinte bcrypt ou Argon2');
        self::assertTrue(password_verify($motDePasse, $empreinte), 'L\'empreinte correspond au mot de passe saisi');
        self::assertFalse(password_verify('autre-mot-de-passe', $empreinte));

        // Le même mot de passe haché une seconde fois donne une autre empreinte (sel aléatoire).
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertNotSame($empreinte, $hasher->hashPassword($this->utilisateurs['adherent'], $motDePasse));
    }

    // ------------------------------------------------------------------ Outils

    /**
     * Ouvre la page du formulaire, modifie le jeton (absent, faux ou valide), complète
     * les champs et envoie. Les champs sont ajoutés au corps de la requête : certains
     * sont hors du formulaire dans le HTML (état au retour, attribut form=).
     */
    private function envoyer(string $formulaire, string $jeton): void
    {
        [$profil, $page, $selecteur, $champJeton, $saisies] = self::FORMULAIRES[$formulaire];
        if (null !== $profil) {
            $this->client->loginUser($this->utilisateurs[$profil]);
        }

        $crawler = $this->client->request('GET', $this->remplacer($page));
        self::assertResponseIsSuccessful('Page du formulaire');
        $form = $crawler->filter($this->remplacer($selecteur))->form();

        match ($jeton) {
            'absent' => $form->remove($champJeton),
            'faux' => $form[$champJeton]->setValue('jeton-falsifie'),
            default => null,
        };

        $donnees = array_replace_recursive($form->getPhpValues(), $this->remplacer($saisies));
        $this->client->request($form->getMethod(), $form->getUri(), $donnees);
    }

    /** État que l'action modifie, lu directement en base (ou la session pour la connexion). */
    private function observer(string $formulaire): string
    {
        $v = $this->valeurs;

        return match ($formulaire) {
            'réserver (E4)' => (string) $this->connexion->fetchOne('SELECT COUNT(*) FROM reservation WHERE objet_id = ?', [$v['{objetAReserver}']]),
            'annuler (E5)' => (string) $this->connexion->fetchOne('SELECT statut FROM reservation WHERE id = ?', [$v['{aAnnuler}']]),
            'valider une sortie (E6)' => (string) $this->connexion->fetchOne('SELECT statut FROM reservation WHERE id = ?', [$v['{aSortir}']]),
            'enregistrer un retour (E6)' => (string) $this->connexion->fetchOne('SELECT statut FROM reservation WHERE id = ?', [$v['{aRendre}']]),
            'désactiver un objet (E7)' => $this->connexion->fetchOne('SELECT actif FROM objet WHERE id = ?', [$v['{objetLibre}']]) ? 'actif' : 'inactif',
            'supprimer un objet (E7)' => (string) $this->connexion->fetchOne('SELECT COUNT(*) FROM objet WHERE id = ?', [$v['{objetLibre}']]),
            'réactiver un objet (E7)' => $this->connexion->fetchOne('SELECT actif FROM objet WHERE id = ?', [$v['{objetRetire}']]) ? 'actif' : 'inactif',
            'créer un objet (E7)' => (string) $this->connexion->fetchOne('SELECT COUNT(*) FROM objet WHERE nom = ?', ['Objet créé (test CSRF)']),
            'modifier un objet (E7)' => (string) $this->connexion->fetchOne('SELECT nom FROM objet WHERE id = ?', [$v['{objetLibre}']]),
            'inscription (E2)' => (string) $this->connexion->fetchOne('SELECT COUNT(*) FROM utilisateur WHERE email = ?', ['csrf.inscription@pretoutil.test']),
            'connexion (E1)' => $this->estConnecte() ? 'connecté' : 'anonyme',
        };
    }

    private function seConnecter(string $email, string $motDePasse): void
    {
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', ['email' => $email, 'mot_de_passe' => $motDePasse]);
        if ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }
    }

    /** Une page réservée aux adhérents s'ouvre-t-elle sans renvoi vers la connexion ? */
    private function estConnecte(): bool
    {
        $this->client->request('GET', '/mes-reservations');

        return $this->client->getResponse()->isSuccessful();
    }

    private function objetPiege(): int
    {
        $categorie = $this->entityManager->find(Categorie::class, (int) $this->valeurs['{categorie}']);
        $objet = (new Objet())->setCategorie($categorie)->setNom(self::XSS_NOM)->setDescription(self::XSS_DESCRIPTION);
        $this->entityManager->persist($objet);
        $this->entityManager->flush();

        return (int) $objet->getId();
    }

    /**
     * @template T of string|array
     *
     * @param T $valeur
     *
     * @return T
     */
    private function remplacer(string|array $valeur): string|array
    {
        if (\is_array($valeur)) {
            return array_map($this->remplacer(...), $valeur);
        }

        return strtr($valeur, $this->valeurs);
    }

    /** @param list<string> $roles */
    private function utilisateur(string $email, array $roles): Utilisateur
    {
        $utilisateur = (new Utilisateur())
            ->setEmail($email)
            ->setNom('Testeur')
            ->setPrenom('Sécurité')
            ->setMotDePasse('empreinte-non-utilisee')
            ->setRoles($roles);
        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $utilisateur;
    }

    private function objet(Categorie $categorie, string $nom): Objet
    {
        $objet = (new Objet())->setCategorie($categorie)->setNom($nom)->setDescription('Objet de test');
        $this->entityManager->persist($objet);

        return $objet;
    }

    private function jour(int $decalage): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('today %+d days', $decalage));
    }
}
