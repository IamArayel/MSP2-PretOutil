<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\PhotoIllisibleException;
use App\Service\ConvertisseurPhoto;
use App\Service\StockagePhotoObjet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Réencodage et stockage des photos des objets (#106), dans un dossier temporaire.
 * Les photos de test sont fabriquées avec GD : moitié gauche rouge, moitié droite bleue.
 */
final class PhotoObjetTest extends TestCase
{
    private string $dossier;
    private StockagePhotoObjet $stockage;

    /** @var list<string> fichiers envoyés, effacés à la fin du test */
    private array $envois = [];

    protected function setUp(): void
    {
        $this->dossier = sys_get_temp_dir().'/photos-objets-'.bin2hex(random_bytes(4));
        $this->stockage = new StockagePhotoObjet(new ConvertisseurPhoto(), $this->dossier);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), [...(glob($this->dossier.'/*') ?: []), ...$this->envois]);
        @rmdir($this->dossier);
    }

    public function testPhotoRecadreeEn4Sur3EtConvertieEnWebp(): void
    {
        $nom = $this->stockage->enregistrer($this->fichier($this->jpeg(1600, 900)));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.webp$/', $nom, 'Nom aléatoire, accepté par ck_objet_image');
        $infos = getimagesize($this->stockage->chemin($nom));
        self::assertSame([960, 720, 'image/webp'], [$infos[0], $infos[1], $infos['mime']]);
    }

    public function testPngTransparentAccepte(): void
    {
        $image = imagecreatetruecolor(800, 600);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        ob_start();
        imagepng($image);

        $nom = $this->stockage->enregistrer($this->fichier((string) ob_get_clean()));

        self::assertSame('image/webp', getimagesize($this->stockage->chemin($nom))['mime']);
    }

    /** Métadonnées EXIF et contenu ajouté au fichier (script) ne survivent pas au réencodage. */
    public function testMetadonneesEtContenuCacheEffaces(): void
    {
        $jpeg = $this->avecExif($this->jpeg(1600, 1200), $this->exif(1, 'GPS-secret-45.123')).'<?php echo "piege"; ?>';

        $contenu = (string) file_get_contents($this->stockage->chemin($this->stockage->enregistrer($this->fichier($jpeg))));

        self::assertStringNotContainsString('GPS-secret', $contenu);
        self::assertStringNotContainsString('Exif', $contenu);
        self::assertStringNotContainsString('<?php', $contenu);
    }

    /** Photo prise téléphone tourné (orientation EXIF 6) : redressée, le rouge de gauche passe en haut. */
    public function testPhotoRedresseeSelonLOrientationExif(): void
    {
        $jpeg = $this->avecExif($this->jpeg(1600, 1200), $this->exif(6));

        $photo = imagecreatefromwebp($this->stockage->chemin($this->stockage->enregistrer($this->fichier($jpeg))));

        self::assertGreaterThan(200, imagecolorsforindex($photo, imagecolorat($photo, 480, 20))['red'], 'Rouge en haut');
        self::assertGreaterThan(200, imagecolorsforindex($photo, imagecolorat($photo, 480, 700))['blue'], 'Bleu en bas');
    }

    public function testFichierIllisibleRefuse(): void
    {
        $this->expectException(PhotoIllisibleException::class);

        $this->stockage->enregistrer($this->fichier("\xFF\xD8\xFF\xE0 fichier tronqué"));
    }

    public function testSupprimerIgnoreUnNomQuiSortDuDossier(): void
    {
        $nom = $this->stockage->enregistrer($this->fichier($this->jpeg(800, 600)));
        $voisin = \dirname($this->dossier).'/voisin-'.bin2hex(random_bytes(4)).'.webp';
        file_put_contents($voisin, 'à garder');

        $this->stockage->supprimer('../'.basename($voisin));
        self::assertFileExists($voisin, 'Chemin refusé : rien n\'est effacé hors du dossier');
        unlink($voisin);

        $this->stockage->supprimer($nom);
        self::assertFileDoesNotExist($this->stockage->chemin($nom));
        $this->stockage->supprimer(null);
    }

    private function jpeg(int $largeur, int $hauteur): string
    {
        $image = imagecreatetruecolor($largeur, $hauteur);
        imagefilledrectangle($image, 0, 0, intdiv($largeur, 2) - 1, $hauteur, (int) imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, intdiv($largeur, 2), 0, $largeur, $hauteur, (int) imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    /** Segment APP1 minimal : orientation, et un texte libre (description) pour simuler des données personnelles. */
    private function exif(int $orientation, string $texte = ''): string
    {
        $entrees = [pack('vvVv', 0x0112, 3, 1, $orientation)."\x00\x00"];
        $donnees = '';
        if ('' !== $texte) {
            $texte .= "\x00";
            $entrees[] = pack('vvVV', 0x010E, 2, \strlen($texte), 8 + 2 + 12 * 2 + 4);
            $donnees = $texte;
        }
        $tiff = "II*\x00".pack('V', 8).pack('v', \count($entrees)).implode('', $entrees).pack('V', 0).$donnees;
        $app1 = "Exif\x00\x00".$tiff;

        return "\xFF\xE1".pack('n', \strlen($app1) + 2).$app1;
    }

    private function avecExif(string $jpeg, string $app1): string
    {
        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }

    private function fichier(string $contenu): File
    {
        $chemin = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($chemin, $contenu);
        $this->envois[] = $chemin;

        return new File($chemin);
    }
}
