<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Écran E8 Gestion des comptes (US9) par de vraies requêtes HTTP : actions de
 * l'administrateur, refus sur son propre compte, jeton CSRF, journalisation sur E9,
 * et fermeture de la session d'un compte désactivé (RG9).
 *
 * PostgreSQL : transaction annulée après chaque test. MongoDB (pretoutil_test) : les
 * documents restent (compte en ajout seul), retrouvés par un email unique par test.
 */
final class GestionComptesControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private Connection $connexion;
    private Utilisateur $administrateur;
    private Utilisateur $adherent;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $this->entityManager->getConnection();
        $this->connexion->beginTransaction();

        $marqueur = bin2hex(random_bytes(4));
        $this->administrateur = $this->utilisateur("admin.comptes.$marqueur@pretoutil.test", 'Sacha', ['ROLE_ADMIN']);
        $this->adherent = $this->utilisateur("camille.comptes.$marqueur@pretoutil.test", 'Camille', []);
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }

        parent::tearDown();
    }

    public function testListeDesComptesSansActionSurSoiMeme(): void
    {
        $this->client->loginUser($this->administrateur);
        $crawler = $this->client->request('GET', '/admin/comptes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Gestion des comptes');
        self::assertCount(1, $crawler->filter('button[aria-label="Désactiver le compte de Camille Testeur"]'));
        self::assertCount(0, $crawler->filter('button[aria-label="Désactiver le compte de Sacha Testeur"]'), 'Aucune action sur son propre compte');
    }

    public function testAdministrationRenvoieVersLaGestionDesComptes(): void
    {
        $this->client->loginUser($this->administrateur);
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/admin/comptes');
    }

    public function testChangerLeRoleDepuisLaListe(): void
    {
        $this->client->loginUser($this->administrateur);
        $crawler = $this->client->request('GET', '/admin/comptes');

        $this->client->submit($crawler->filter('button[aria-label="Changer le rôle de Camille Testeur"]')->form(), ['role' => 'gestionnaire']);

        self::assertResponseRedirects('/admin/comptes', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Camille Testeur est maintenant gestionnaire.');
        self::assertSame('["ROLE_MANAGER"]', $this->colonne('roles'));
        $this->assertJournalise(ActionAudit::RoleModifie, ['email' => $this->adherent->getEmail(), 'ancien_role' => 'adherent', 'nouveau_role' => 'gestionnaire']);
    }

    public function testDesactiverPuisReactiverDepuisLaListe(): void
    {
        $this->client->loginUser($this->administrateur);
        $crawler = $this->client->request('GET', '/admin/comptes');
        $this->client->submit($crawler->filter('button[aria-label="Désactiver le compte de Camille Testeur"]')->form());

        self::assertResponseRedirects('/admin/comptes', 303);
        self::assertFalse((bool) $this->colonne('actif'));
        $this->assertJournalise(ActionAudit::CompteDesactive, ['email' => $this->adherent->getEmail()]);

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Le compte de Camille Testeur est désactivé.');
        $this->client->submit($crawler->filter('button[aria-label="Réactiver le compte de Camille Testeur"]')->form());

        self::assertTrue((bool) $this->colonne('actif'));
        $this->assertJournalise(ActionAudit::CompteReactive, ['email' => $this->adherent->getEmail()]);
    }

    /** Envoi forgé (le bouton n'est pas affiché) : refusé par le service, message affiché. */
    public function testAdministrateurNePeutPasSeDesactiver(): void
    {
        $this->client->loginUser($this->administrateur);
        $this->client->request('GET', '/admin/comptes');
        // Jeton valide pour son propre compte, que la page n'affiche pas : créé dans la session du client.
        $requete = $this->client->getRequest();
        $pile = self::getContainer()->get('request_stack');
        $pile->push($requete);
        $jeton = self::getContainer()->get('security.csrf.token_manager')->getToken('desactiver-compte-'.$this->administrateur->getId())->getValue();
        $requete->getSession()->save();
        $pile->pop();

        $this->client->request('POST', \sprintf('/admin/comptes/%d/desactiver', $this->administrateur->getId()), ['_token' => $jeton]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('[role="alert"]', 'Vous ne pouvez pas désactiver votre propre compte.');
        self::assertTrue((bool) $this->connexion->fetchOne('SELECT actif FROM utilisateur WHERE id = ?', [$this->administrateur->getId()]));
    }

    public function testJetonFauxOuRoleInconnuRefuses(): void
    {
        $this->client->loginUser($this->administrateur);
        $crawler = $this->client->request('GET', '/admin/comptes');
        $form = $crawler->filter('button[aria-label="Changer le rôle de Camille Testeur"]')->form();

        $this->client->request('POST', $form->getUri(), ['_token' => 'jeton-falsifie', 'role' => 'administrateur']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'La page a expiré.');

        $this->client->request('POST', $form->getUri(), ['_token' => $form['_token']->getValue(), 'role' => 'super_admin']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Rôle inconnu.');

        self::assertSame('[]', $this->colonne('roles'), 'Rôle inchangé');
    }

    /** RG9 : un compte désactivé pendant sa session est déconnecté à sa requête suivante. */
    public function testCompteDesactiveEnCoursDeSessionDeconnecte(): void
    {
        $this->client->loginUser($this->adherent);
        $this->client->request('GET', '/mes-reservations');
        self::assertResponseIsSuccessful('Session ouverte');

        // Désactivé par un administrateur pendant que l'adhérent est connecté
        $this->adherent->setActif(false);
        $this->entityManager->flush();

        $this->client->request('GET', '/mes-reservations');
        self::assertResponseRedirects('/connexion', 302, 'Session fermée');
    }

    /** Un changement de rôle ferme aussi la session : les nouveaux droits s'appliquent à la reconnexion. */
    public function testRoleModifieEnCoursDeSessionDeconnecte(): void
    {
        $this->client->loginUser($this->adherent);
        $this->client->request('GET', '/mes-reservations');
        self::assertResponseIsSuccessful();

        $this->adherent->setRoles(['ROLE_MANAGER']);
        $this->entityManager->flush();

        $this->client->request('GET', '/mes-reservations');
        self::assertResponseRedirects('/connexion');
    }

    /** Le journal (E9) décrit chaque action de E8. */
    public function testActionsLisiblesSurLeJournal(): void
    {
        $this->client->loginUser($this->administrateur);
        $crawler = $this->client->request('GET', '/admin/comptes');
        $this->client->submit($crawler->filter('button[aria-label="Changer le rôle de Camille Testeur"]')->form(), ['role' => 'administrateur']);

        $this->client->request('GET', '/admin/journal?action=ROLE_MODIFIE');

        self::assertSelectorTextContains('main', $this->adherent->getEmail().' : Adhérent → Administrateur');
    }

    /** @param array<string, string> $details */
    private function assertJournalise(ActionAudit $action, array $details): void
    {
        $entrees = self::getContainer()->get(AuditLogRepository::class)->lister($action, 1)->elements;
        $trouvee = array_filter($entrees, static fn ($entree): bool => $entree->getDetails() === $details);

        self::assertCount(1, $trouvee, $action->value.' journalisée une fois');
        $entree = array_values($trouvee)[0];
        self::assertSame($this->administrateur->getId(), $entree->getActeur()->getId(), 'Acteur : l\'administrateur');
        self::assertSame($this->adherent->getId(), $entree->getCible()->getId(), 'Cible : le compte modifié');
    }

    private function colonne(string $colonne): mixed
    {
        return $this->connexion->fetchOne("SELECT $colonne FROM utilisateur WHERE id = ?", [$this->adherent->getId()]);
    }

    /** @param list<string> $roles */
    private function utilisateur(string $email, string $prenom, array $roles): Utilisateur
    {
        $utilisateur = (new Utilisateur())
            ->setEmail($email)
            ->setNom('Testeur')
            ->setPrenom($prenom)
            ->setMotDePasse('empreinte-non-utilisee')
            ->setRoles($roles);
        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $utilisateur;
    }
}
