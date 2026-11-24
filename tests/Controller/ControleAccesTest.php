<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\StatutReservation;
use App\Security\UtilisateurChecker;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Contrôle d'accès de chaque route (critère O5, docs/tests/plan-de-tests.md).
 *
 * La matrice ROUTES donne, pour chaque route et chaque profil, la réponse attendue.
 * Un test vérifie qu'elle contient toutes les routes de l'application : une route
 * ajoutée sans y être placée fait échouer la suite.
 *
 * Les requêtes en POST sont envoyées sans jeton CSRF : on vérifie ici qui passe le
 * contrôle d'accès, pas l'action elle-même (le CSRF est testé à part, ticket #66).
 *
 * Chaque test s'exécute dans une transaction annulée à la fin : la base reste vide.
 * Prérequis : base créée et migrée (README, section Tests).
 */
final class ControleAccesTest extends WebTestCase
{
    /** Redirection vers la page de connexion (visiteur sur une page protégée). */
    private const string CONNEXION = 'connexion';

    private const array PROFILS = ['visiteur', 'adherent', 'gestionnaire', 'administrateur'];

    /**
     * Route => [méthode, chemin, visiteur, adhérent, gestionnaire, administrateur].
     *
     * {objet} : objet réservé ; {objetLibre} : objet jamais réservé (supprimable) ;
     * {reservation} : réservation confirmée de l'adhérent, qui commence dans 7 jours.
     * Une réponse en 3xx sans redirection vers la connexion est un accès autorisé :
     * l'action a répondu (formulaire déjà connecté, jeton absent, etc.).
     */
    private const array ROUTES = [
        // Pages publiques
        'app_accueil' => ['GET', '/', 200, 200, 200, 200],
        'app_catalogue' => ['GET', '/catalogue', 200, 200, 200, 200],
        'app_catalogue_objet' => ['GET', '/catalogue/{objet}', 200, 200, 200, 200],
        'api_objet_disponibilites' => ['GET', '/api/objets/{objet}/disponibilites', 200, 200, 200, 200],
        // Connexion et inscription : un utilisateur déjà connecté est renvoyé à l'accueil
        'app_connexion' => ['GET', '/connexion', 200, 302, 302, 302],
        'app_inscription' => ['GET', '/inscription', 200, 302, 302, 302],
        // Déconnexion sans jeton CSRF : refusée (voir testDeconnexionAvecJeton)
        'app_deconnexion' => ['GET', '/deconnexion', 403, 403, 403, 403],
        // Adhérent (ROLE_USER)
        'app_catalogue_reserver' => ['POST', '/catalogue/{objet}/reserver', self::CONNEXION, 422, 422, 422],
        'app_mes_reservations' => ['GET', '/mes-reservations', self::CONNEXION, 200, 200, 200],
        // Annuler : seulement sa propre réservation (ReservationVoter, RG6)
        'app_mes_reservations_annuler' => ['POST', '/mes-reservations/{reservation}/annuler', self::CONNEXION, 303, 403, 403],
        // Gestionnaire (ROLE_MANAGER)
        'app_gestion_catalogue' => ['GET', '/gestion/catalogue', self::CONNEXION, 403, 200, 200],
        'app_gestion_catalogue_nouveau' => ['GET', '/gestion/catalogue/nouveau', self::CONNEXION, 403, 200, 200],
        'app_gestion_catalogue_modifier' => ['GET', '/gestion/catalogue/{objet}/modifier', self::CONNEXION, 403, 200, 200],
        'app_gestion_catalogue_desactiver' => ['POST', '/gestion/catalogue/{objetLibre}/desactiver', self::CONNEXION, 403, 302, 302],
        'app_gestion_catalogue_supprimer' => ['POST', '/gestion/catalogue/{objetLibre}/supprimer', self::CONNEXION, 403, 302, 302],
        'app_sorties_retours' => ['GET', '/gestion/sorties-retours', self::CONNEXION, 403, 200, 200],
        'app_sorties_retours_sortie' => ['POST', '/gestion/sorties-retours/{reservation}/sortie', self::CONNEXION, 403, 303, 303],
        'app_sorties_retours_retour' => ['POST', '/gestion/sorties-retours/{reservation}/retour', self::CONNEXION, 403, 303, 303],
        // Administrateur (ROLE_ADMIN)
        'app_admin' => ['GET', '/admin', self::CONNEXION, 403, 403, 200],
        'app_journal_audit' => ['GET', '/admin/journal', self::CONNEXION, 403, 403, 200],
    ];

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private Connection $connexion;

    /** @var array<string, Utilisateur> profil => utilisateur */
    private array $utilisateurs = [];

    /** @var array<string, int> paramètre du chemin => identifiant */
    private array $identifiants = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Même noyau pour toutes les requêtes du test : même connexion, donc même transaction.
        $this->client->disableReboot();
        // Compteurs de la limite de débit de l'API remis à zéro : le test du 429
        // (DisponibiliteControllerTest) les laisse au maximum pour la minute en cours.
        self::getContainer()->get('cache.limiteur_api')->clear();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $this->entityManager->getConnection();
        $this->connexion->beginTransaction();

        $this->utilisateurs = [
            'adherent' => $this->utilisateur('adherent.acces@pretoutil.test', []),
            'gestionnaire' => $this->utilisateur('gestionnaire.acces@pretoutil.test', ['ROLE_MANAGER']),
            'administrateur' => $this->utilisateur('admin.acces@pretoutil.test', ['ROLE_ADMIN']),
        ];

        $categorie = (new Categorie())->setLibelle('Catégorie (test accès)');
        $this->entityManager->persist($categorie);
        $objet = $this->objet($categorie, 'Perceuse de test');
        $objetLibre = $this->objet($categorie, 'Ponceuse jamais réservée');
        $reservation = new Reservation($this->utilisateurs['adherent'], $objet, $this->jour(7), $this->jour(9));
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $this->identifiants = [
            '{objet}' => (int) $objet->getId(),
            '{objetLibre}' => (int) $objetLibre->getId(),
            '{reservation}' => (int) $reservation->getId(),
        ];
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }

        parent::tearDown();
    }

    /** Toutes les routes de l'application sont dans la matrice, et seulement elles. */
    public function testMatriceCouvreToutesLesRoutes(): void
    {
        $routes = array_keys(self::getContainer()->get(RouterInterface::class)->getRouteCollection()->all());
        // Routes internes de Symfony (barre de débogage, pages d'erreur) : hors application.
        $routes = array_values(array_filter($routes, static fn (string $nom): bool => !str_starts_with($nom, '_')));
        sort($routes);
        $matrice = array_keys(self::ROUTES);
        sort($matrice);

        self::assertSame($routes, $matrice, 'Chaque route doit avoir une ligne dans ControleAccesTest::ROUTES');
    }

    /** @return iterable<string, array{string, string, string, int|string}> */
    public static function casDAcces(): iterable
    {
        foreach (self::ROUTES as $route => $ligne) {
            [$methode, $chemin] = $ligne;
            foreach (self::PROFILS as $i => $profil) {
                yield $route.' / '.$profil => [$methode, $chemin, $profil, $ligne[$i + 2]];
            }
        }
    }

    #[DataProvider('casDAcces')]
    public function testAcces(string $methode, string $chemin, string $profil, int|string $attendu): void
    {
        if ('visiteur' !== $profil) {
            $this->client->loginUser($this->utilisateurs[$profil]);
        }

        $this->client->request($methode, strtr($chemin, $this->identifiants));
        $reponse = $this->client->getResponse();

        if (self::CONNEXION === $attendu) {
            self::assertResponseRedirects('/connexion', 302, 'Visiteur renvoyé vers la connexion');

            return;
        }

        self::assertSame($attendu, $reponse->getStatusCode(), \sprintf('%s %s en tant que %s', $methode, $chemin, $profil));
        if ($reponse->isRedirection()) {
            self::assertStringNotContainsString('/connexion', (string) $reponse->headers->get('Location'), 'Accès autorisé : pas de renvoi vers la connexion');
        }
    }

    /** @return iterable<string, array{string}> */
    public static function routesEnPost(): iterable
    {
        foreach (self::ROUTES as $route => [$methode, $chemin]) {
            if ('POST' === $methode) {
                yield $route => [$chemin];
            }
        }
    }

    /** Une action qui modifie des données n'est jamais accessible en GET (lien piégé). */
    #[DataProvider('routesEnPost')]
    public function testActionRefuseeEnGet(string $chemin): void
    {
        $this->client->loginUser($this->utilisateurs['administrateur']);
        $this->client->request('GET', strtr($chemin, $this->identifiants));

        self::assertResponseStatusCodeSame(405);
    }

    /** Le lien de déconnexion des pages porte un jeton CSRF : avec lui, la déconnexion fonctionne. */
    public function testDeconnexionAvecJeton(): void
    {
        $this->client->loginUser($this->utilisateurs['adherent']);
        $lien = $this->client->request('GET', '/')->filter('a[href^="/deconnexion"]')->link();

        $this->client->click($lien);
        self::assertResponseRedirects('/');

        $this->client->request('GET', '/mes-reservations');
        self::assertResponseRedirects('/connexion', 302, 'Session fermée');
    }

    /** RG9 : un compte désactivé ne peut pas se connecter, même avec le bon mot de passe. */
    public function testCompteDesactiveNePeutPasSeConnecter(): void
    {
        // Chaque lancement ajoute un échec de connexion pour cet email, conservé sur disque :
        // sans remise à zéro, login_throttling bloquerait le compte au 6e lancement.
        self::getContainer()->get('cache.rate_limiter')->clear();
        $desactive = $this->utilisateur('desactive.acces@pretoutil.test', [])->setActif(false);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $desactive->setMotDePasse($hasher->hashPassword($desactive, 'BonMotDePasse!2026'));
        $this->entityManager->flush();

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', ['email' => 'desactive.acces@pretoutil.test', 'mot_de_passe' => 'BonMotDePasse!2026']);

        self::assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', UtilisateurChecker::MESSAGE_COMPTE_DESACTIVE);

        $this->client->request('GET', '/mes-reservations');
        self::assertResponseRedirects('/connexion', 302, 'Aucune session ouverte');
    }

    /** IDOR : un adhérent ne peut pas annuler la réservation d'un autre en changeant l'identifiant. */
    public function testAnnulationDeLaReservationDUnAutreAdherentRefusee(): void
    {
        $this->client->loginUser($this->utilisateur('autre.acces@pretoutil.test', []));
        $this->client->request('POST', strtr('/mes-reservations/{reservation}/annuler', $this->identifiants));

        self::assertResponseStatusCodeSame(403);
        $statut = $this->connexion->fetchOne('SELECT statut FROM reservation WHERE id = ?', [$this->identifiants['{reservation}']]);
        self::assertSame(StatutReservation::Confirmee->value, $statut, 'Réservation inchangée');
    }

    /** Un adhérent ne voit que ses propres réservations sur E5. */
    public function testMesReservationsNAfficheQueLesSiennes(): void
    {
        $this->client->loginUser($this->utilisateur('autre.acces@pretoutil.test', []));
        $this->client->request('GET', '/mes-reservations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('main', 'Perceuse de test');
    }

    /** Un objet retiré du catalogue ou inconnu n'est accessible par aucune route publique. */
    public function testObjetRetireOuInconnuIntrouvable(): void
    {
        $objet = $this->entityManager->find(Objet::class, $this->identifiants['{objet}'])->setActif(false);
        $this->entityManager->flush();
        $this->client->loginUser($this->utilisateurs['adherent']);

        foreach ([$objet->getId(), 999999] as $id) {
            foreach ([['GET', '/catalogue/%d'], ['GET', '/api/objets/%d/disponibilites'], ['POST', '/catalogue/%d/reserver']] as [$methode, $chemin]) {
                $this->client->request($methode, \sprintf($chemin, $id));
                self::assertResponseStatusCodeSame(404, \sprintf('%s %s', $methode, \sprintf($chemin, $id)));
            }
        }
    }

    /** @param list<string> $roles */
    private function utilisateur(string $email, array $roles): Utilisateur
    {
        $utilisateur = (new Utilisateur())
            ->setEmail($email)
            ->setNom('Testeur')
            ->setPrenom('Accès')
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
