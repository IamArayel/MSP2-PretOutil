<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\Utilisateur;
use App\Service\StockagePhotoObjet;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Photo d'un objet depuis l'écran E7 (#106) : ajout, remplacement, retrait, refus d'un fichier
 * qui n'est pas une photo, effacement avec l'objet. Base dans une transaction annulée à la fin ;
 * photos écrites dans le dossier de test (var/test/uploads/objets), vidé à la fin.
 */
final class GestionCataloguePhotoTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connexion;
    private EntityManagerInterface $entityManager;
    private StockagePhotoObjet $stockage;
    private Categorie $categorie;

    /** @var list<string> fichiers envoyés, effacés à la fin du test */
    private array $envois = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $this->entityManager->getConnection();
        $this->connexion->beginTransaction();
        $this->stockage = self::getContainer()->get(StockagePhotoObjet::class);

        $gestionnaire = (new Utilisateur())->setEmail('gestionnaire.photo@pretoutil.test')->setNom('Bernard')->setPrenom('Léa')
            ->setMotDePasse('empreinte-non-utilisee')->setRoles(['ROLE_MANAGER']);
        $this->categorie = (new Categorie())->setLibelle('Outillage (test photo)');
        $this->entityManager->persist($gestionnaire);
        $this->entityManager->persist($this->categorie);
        $this->entityManager->flush();
        $this->client->loginUser($gestionnaire);
    }

    protected function tearDown(): void
    {
        while ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }
        $this->stockage->vider();
        array_map(unlink(...), $this->envois);

        parent::tearDown();
    }

    public function testAjoutDUnObjetAvecSaPhoto(): void
    {
        $objet = $this->creer('Perceuse avec photo', $this->jpeg());

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.webp$/', (string) $objet->getImage());
        self::assertFileExists($this->stockage->chemin((string) $objet->getImage()));

        $this->client->request('GET', \sprintf('/catalogue/%d', $objet->getId()));
        self::assertSelectorExists(\sprintf('main article img[src="/uploads/objets/%s"]', $objet->getImage()));
    }

    public function testPhotoRemplaceeLAncienneEstEffacee(): void
    {
        $objet = $this->creer('Ponceuse', $this->jpeg());
        $ancienne = (string) $objet->getImage();

        $page = $this->client->request('GET', \sprintf('/gestion/catalogue/%d/modifier', $objet->getId()));
        self::assertSelectorTextContains('label[for="objet_photo"]', 'Remplacer la photo');
        $formulaire = $page->selectButton('Enregistrer les modifications')->form();
        $formulaire['objet[photo]']->upload($this->jpeg());
        $this->client->submit($formulaire);

        self::assertResponseRedirects('/gestion/catalogue');
        $nouvelle = (string) $this->photoEnBase($objet);
        self::assertNotSame($ancienne, $nouvelle);
        self::assertFileExists($this->stockage->chemin($nouvelle));
        self::assertFileDoesNotExist($this->stockage->chemin($ancienne), 'Ancienne photo effacée');
    }

    public function testPhotoRetiree(): void
    {
        $objet = $this->creer('Scie', $this->jpeg());
        $photo = (string) $objet->getImage();

        $page = $this->client->request('GET', \sprintf('/gestion/catalogue/%d/modifier', $objet->getId()));
        $this->client->submit($page->selectButton('Enregistrer les modifications')->form(), ['objet[supprimerPhoto]' => true]);

        self::assertResponseRedirects('/gestion/catalogue');
        self::assertNull($this->photoEnBase($objet), 'Vignette de remplacement');
        self::assertFileDoesNotExist($this->stockage->chemin($photo));
    }

    public function testCaseRetirerAbsenteSansPhoto(): void
    {
        $objet = $this->creer('Escabeau', null);

        $this->client->request('GET', \sprintf('/gestion/catalogue/%d/modifier', $objet->getId()));

        self::assertSelectorNotExists('#objet_supprimerPhoto');
        self::assertSelectorTextContains('label[for="objet_photo"]', 'Photo (facultatif)');
    }

    /** Un fichier texte renommé en .jpg est refusé : le type est lu dans le contenu, pas dans l'extension. */
    public function testFichierQuiNEstPasUnePhotoRefuse(): void
    {
        $faux = $this->envoi('<?php echo "piege"; ?>', '.jpg');

        $page = $this->client->request('GET', '/gestion/catalogue/nouveau');
        $formulaire = $page->selectButton('Ajouter au catalogue')->form($this->champs('Objet piégé'));
        $formulaire['objet[photo]']->upload($faux);
        $this->client->submit($formulaire);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#objet_photo_erreurs', 'La photo doit être au format JPEG, PNG ou WebP.');
        self::assertFalse($this->connexion->fetchOne('SELECT 1 FROM objet WHERE nom = ?', ['Objet piégé']), 'Objet non créé');
        self::assertSame([], glob(\dirname($this->stockage->chemin('x.webp')).'/*.webp') ?: [], 'Aucun fichier écrit');
    }

    public function testPhotoTropPetiteRefusee(): void
    {
        $page = $this->client->request('GET', '/gestion/catalogue/nouveau');
        $formulaire = $page->selectButton('Ajouter au catalogue')->form($this->champs('Petite photo'));
        $formulaire['objet[photo]']->upload($this->jpeg(320, 240));
        $this->client->submit($formulaire);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#objet_photo_erreurs', 'au moins 640 pixels de large');
    }

    public function testPhotoEffaceeAvecLObjet(): void
    {
        $objet = $this->creer('Objet à supprimer', $this->jpeg());
        $photo = (string) $objet->getImage();

        $page = $this->client->request('GET', '/gestion/catalogue?q=');
        $bouton = $page->filter(\sprintf('form[action="/gestion/catalogue/%d/supprimer"] button', $objet->getId()));
        $this->client->submit($bouton->form());

        self::assertResponseRedirects('/gestion/catalogue');
        self::assertFileDoesNotExist($this->stockage->chemin($photo));
    }

    private function creer(string $nom, ?string $photo): Objet
    {
        $page = $this->client->request('GET', '/gestion/catalogue/nouveau');
        $formulaire = $page->selectButton('Ajouter au catalogue')->form($this->champs($nom));
        if (null !== $photo) {
            $formulaire['objet[photo]']->upload($photo);
        }
        $this->client->submit($formulaire);
        self::assertResponseRedirects('/gestion/catalogue');

        return $this->entityManager->getRepository(Objet::class)->findOneBy(['nom' => $nom]);
    }

    /** @return array<string, string> */
    private function champs(string $nom): array
    {
        return [
            'objet[categorie]' => (string) $this->categorie->getId(),
            'objet[nom]' => $nom,
            'objet[description]' => 'Objet de test',
            'objet[etat]' => 'bon',
        ];
    }

    /** Lue en SQL : chaque requête du client vide le gestionnaire d'entités du test. */
    private function photoEnBase(Objet $objet): ?string
    {
        $photo = $this->connexion->fetchOne('SELECT image FROM objet WHERE id = ?', [$objet->getId()]);

        return false === $photo ? null : $photo;
    }

    private function jpeg(int $largeur = 1200, int $hauteur = 900): string
    {
        $image = imagecreatetruecolor($largeur, $hauteur);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 27, 110, 145));
        ob_start();
        imagejpeg($image);

        return $this->envoi((string) ob_get_clean(), '.jpg');
    }

    private function envoi(string $contenu, string $extension): string
    {
        $chemin = sys_get_temp_dir().'/envoi-'.bin2hex(random_bytes(6)).$extension;
        file_put_contents($chemin, $contenu);
        $this->envois[] = $chemin;

        return $chemin;
    }
}
