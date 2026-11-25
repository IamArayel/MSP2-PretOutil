<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Remise au catalogue d'un objet désactivé depuis l'écran E7 (#111), par de vraies requêtes HTTP.
 * PostgreSQL : transaction annulée à la fin. MongoDB (pretoutil_test) : l'entrée du journal
 * reste (ajout seul), retrouvée par un nom d'objet unique par test.
 */
final class ReactivationObjetTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connexion;
    private Utilisateur $gestionnaire;
    private Objet $retire;
    private Objet $actif;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $entityManager->getConnection();
        $this->connexion->beginTransaction();

        $marqueur = bin2hex(random_bytes(4));
        $this->gestionnaire = (new Utilisateur())->setEmail("gestionnaire.reactivation.$marqueur@pretoutil.test")->setNom('Bernard')
            ->setPrenom('Léa')->setMotDePasse('empreinte-non-utilisee')->setRoles(['ROLE_MANAGER']);
        $categorie = (new Categorie())->setLibelle('Jardin (test réactivation)');
        $this->retire = (new Objet())->setCategorie($categorie)->setNom("Tronçonneuse $marqueur")->setDescription('Objet de test')->setActif(false);
        $this->actif = (new Objet())->setCategorie($categorie)->setNom("Sécateur $marqueur")->setDescription('Objet de test');
        foreach ([$this->gestionnaire, $categorie, $this->retire, $this->actif] as $entite) {
            $entityManager->persist($entite);
        }
        $entityManager->flush();
        $this->client->loginUser($this->gestionnaire);
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }

        parent::tearDown();
    }

    /** « Réactiver » sur la ligne retirée, « Désactiver » sur la ligne active, jamais les deux. */
    public function testBoutonReactiverSeulementSurUnObjetRetire(): void
    {
        $page = $this->client->request('GET', '/gestion/catalogue');

        self::assertCount(1, $page->filter(\sprintf('form[action="/gestion/catalogue/%d/reactiver"] button[aria-label^="Réactiver"]', $this->retire->getId())));
        self::assertCount(0, $page->filter(\sprintf('form[action="/gestion/catalogue/%d/desactiver"]', $this->retire->getId())));
        self::assertCount(0, $page->filter(\sprintf('form[action="/gestion/catalogue/%d/reactiver"]', $this->actif->getId())));
    }

    public function testObjetReactiveDeRetourDansLeCatalogueEtJournalise(): void
    {
        $nom = (string) $this->retire->getNom();
        $page = $this->client->request('GET', '/gestion/catalogue?'.http_build_query(['q' => $nom]));
        $this->client->submit($page->filter(\sprintf('form[action="/gestion/catalogue/%d/reactiver"]', $this->retire->getId()))->form());

        self::assertResponseRedirects('/gestion/catalogue');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', \sprintf('« %s » est de retour dans le catalogue.', $nom));
        self::assertTrue((bool) $this->connexion->fetchOne('SELECT actif FROM objet WHERE id = ?', [$this->retire->getId()]));

        // Visible et consultable dans le catalogue public (E3, E4).
        $this->client->request('GET', '/catalogue?'.http_build_query(['q' => $nom]));
        self::assertSelectorTextContains('.grille-cartes h2', $nom);
        $this->client->request('GET', \sprintf('/catalogue/%d', $this->retire->getId()));
        self::assertResponseIsSuccessful();

        $entrees = self::getContainer()->get(AuditLogRepository::class)->lister(ActionAudit::ObjetReactive, 1)->elements;
        $trouvees = array_filter($entrees, static fn ($entree): bool => $entree->getDetails() === ['nom' => $nom]);
        self::assertCount(1, $trouvees, 'OBJET_REACTIVE journalisée une fois');
        self::assertSame($this->gestionnaire->getId(), array_values($trouvees)[0]->getActeur()->getId());
    }
}
