<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\PhotoIllisibleException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Dossier des photos des objets (#106), servi tel quel par le serveur web (public/uploads/objets/).
 *
 * Chaque photo reçoit un nom aléatoire : aucun nom fourni par l'utilisateur n'arrive sur le disque,
 * et le nom respecte la contrainte ck_objet_image (lettres, chiffres, tirets, .webp).
 */
class StockagePhotoObjet
{
    private const string MOTIF_NOM = '/^[a-z0-9-]+\.webp$/';

    public function __construct(
        private readonly ConvertisseurPhoto $convertisseur,
        #[Autowire('%app.dossier_photos_objets%')]
        private readonly string $dossier,
    ) {
    }

    /**
     * Réencode la photo dans le dossier et renvoie son nom, à enregistrer dans objet.image.
     *
     * @throws PhotoIllisibleException
     */
    public function enregistrer(File $fichier): string
    {
        if (!is_dir($this->dossier) && !mkdir($this->dossier, 0o775, true) && !is_dir($this->dossier)) {
            throw new \RuntimeException('Dossier des photos impossible à créer.');
        }

        $nom = bin2hex(random_bytes(16)).'.webp';
        $this->convertisseur->convertir($fichier->getPathname(), $this->chemin($nom));

        return $nom;
    }

    /** Efface une photo devenue inutile (remplacée, retirée, objet supprimé). Un nom invalide est ignoré. */
    public function supprimer(?string $nom): void
    {
        if (null === $nom || 1 !== preg_match(self::MOTIF_NOM, $nom)) {
            return;
        }

        $chemin = $this->chemin($nom);
        if (is_file($chemin)) {
            unlink($chemin);
        }
    }

    /** Vide le dossier avant le chargement des fixtures, qui recréent toutes les photos. */
    public function vider(): void
    {
        foreach (glob($this->dossier.'/*.webp') ?: [] as $chemin) {
            unlink($chemin);
        }
    }

    public function chemin(string $nom): string
    {
        return $this->dossier.'/'.$nom;
    }
}
