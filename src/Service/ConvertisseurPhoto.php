<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\PhotoIllisibleException;

/**
 * Réencode une photo au format du catalogue : recadrée au centre en 4:3, 960 × 720 px, WebP (#106).
 *
 * Le fichier envoyé n'est jamais gardé tel quel : seuls les pixels sont recopiés dans une image neuve.
 * Tout contenu caché dans le fichier (script, polyglotte) et toutes ses métadonnées EXIF, dont la
 * position GPS d'une photo prise au téléphone, disparaissent.
 */
class ConvertisseurPhoto
{
    public const int LARGEUR = 960;
    public const int HAUTEUR = 720;
    private const int QUALITE = 80;

    /** Rotation à appliquer selon l'orientation EXIF d'une photo JPEG (prise de vue tournée). */
    private const array ROTATIONS = [3 => 180, 6 => -90, 8 => 90];

    /**
     * @throws PhotoIllisibleException si GD ne peut pas décoder le fichier
     */
    public function convertir(string $source, string $destination): void
    {
        $image = $this->ouvrir($source);
        $photo = $this->recadrer($image);

        if (!imagewebp($photo, $destination, self::QUALITE)) {
            throw new \RuntimeException('Écriture de la photo impossible.');
        }
    }

    private function ouvrir(string $source): \GdImage
    {
        $contenu = @file_get_contents($source);
        $image = false === $contenu ? false : @imagecreatefromstring($contenu);
        if (false === $image) {
            throw new PhotoIllisibleException();
        }

        $rotation = self::ROTATIONS[$this->orientation($source)] ?? 0;
        if (0 !== $rotation) {
            $image = imagerotate($image, $rotation, 0) ?: $image;
        }

        return $image;
    }

    private function orientation(string $source): int
    {
        if (!\function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($source);

        return \is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    /** Plus grande zone 4:3 centrée, réduite à 960 × 720 sur fond blanc (transparence d'un PNG). */
    private function recadrer(\GdImage $image): \GdImage
    {
        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $zoneLargeur = min($largeur, (int) round($hauteur * self::LARGEUR / self::HAUTEUR));
        $zoneHauteur = min($hauteur, (int) round($largeur * self::HAUTEUR / self::LARGEUR));

        $photo = imagecreatetruecolor(self::LARGEUR, self::HAUTEUR);
        imagefill($photo, 0, 0, (int) imagecolorallocate($photo, 255, 255, 255));
        imagecopyresampled(
            $photo, $image,
            0, 0, intdiv($largeur - $zoneLargeur, 2), intdiv($hauteur - $zoneHauteur, 2),
            self::LARGEUR, self::HAUTEUR, $zoneLargeur, $zoneHauteur,
        );

        return $photo;
    }
}
