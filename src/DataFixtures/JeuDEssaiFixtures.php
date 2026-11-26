<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Demo\ChargeurJeuDEssai;
use App\Demo\JeuDEssai;
use App\Service\StockagePhotoObjet;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu d'essai des environnements dev et test (doctrine:fixtures:load --em=proprietaire).
 * Données et chargement partagés avec app:demo:initialiser : voir App\Demo\JeuDEssai.
 * Mot de passe de tous les comptes : JeuDEssai::MOT_DE_PASSE_TEST.
 */
final class JeuDEssaiFixtures extends Fixture
{
    public function __construct(
        private readonly ChargeurJeuDEssai $chargeur,
        private readonly StockagePhotoObjet $stockagePhoto,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // La base vient d'être vidée par le chargeur de fixtures ; les photos des chargements
        // précédents ne correspondent plus à aucun objet.
        $this->stockagePhoto->vider();

        $this->chargeur->charger($manager, JeuDEssai::MOT_DE_PASSE_TEST);
    }
}
