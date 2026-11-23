<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Enum\EtatObjet;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Objets du jeu d'essai : plus de 20 pour tester la pagination (US3), avec un objet
 * abîmé, un hors service et un désactivé pour tester RG5.
 */
final class ObjetFixtures extends Fixture implements DependentFixtureInterface
{
    /** Clé de référence => [catégorie, nom, description, état, actif]. */
    private const array OBJETS = [
        'perceuse' => ['Outillage', 'Perceuse-visseuse 18 V', 'Deux batteries et un chargeur.', EtatObjet::Bon, true],
        'scie-sauteuse' => ['Outillage', 'Scie sauteuse', 'Lames bois et métal fournies.', EtatObjet::Bon, true],
        'ponceuse' => ['Outillage', 'Ponceuse orbitale', 'Avec un lot de disques abrasifs.', EtatObjet::Abime, true],
        'meuleuse' => ['Outillage', 'Meuleuse d\'angle 125 mm', 'Disque de coupe et disque à lamelles.', EtatObjet::HorsService, true],
        'niveau-laser' => ['Outillage', 'Niveau laser', 'Croix laser autonivelante, trépied inclus.', EtatObjet::Bon, true],
        'escabeau' => ['Outillage', 'Escabeau 5 marches', 'Aluminium, charge maximale 150 kg.', EtatObjet::Bon, true],
        'tondeuse' => ['Jardinage', 'Tondeuse thermique', 'Largeur de coupe 46 cm, bac de ramassage.', EtatObjet::Bon, true],
        'taille-haie' => ['Jardinage', 'Taille-haie électrique', 'Lame de 60 cm.', EtatObjet::Bon, true],
        'broyeur' => ['Jardinage', 'Broyeur de végétaux', 'Branches jusqu\'à 4 cm de diamètre.', EtatObjet::Bon, true],
        'brouette' => ['Jardinage', 'Brouette', 'Cuve de 100 litres.', EtatObjet::Bon, true],
        'motobineuse' => ['Jardinage', 'Motobineuse', 'Retirée du catalogue : remplacée par un modèle récent.', EtatObjet::Bon, false],
        'tente' => ['Camping', 'Tente 4 places', 'Montage en 10 minutes, deux chambres.', EtatObjet::Bon, true],
        'rechaud' => ['Camping', 'Réchaud à gaz', 'Cartouche non fournie.', EtatObjet::Bon, true],
        'glaciere' => ['Camping', 'Glacière électrique', 'Branchement 12 V et 230 V.', EtatObjet::Bon, true],
        'lampe-frontale' => ['Camping', 'Lampe frontale', 'Rechargeable par USB.', EtatObjet::Bon, true],
        'appareil-raclette' => ['Cuisine', 'Appareil à raclette', 'Huit poêlons.', EtatObjet::Bon, true],
        'robot-patissier' => ['Cuisine', 'Robot pâtissier', 'Fouet, crochet et batteur plat.', EtatObjet::Bon, true],
        'machine-pates' => ['Cuisine', 'Machine à pâtes', 'Manuelle, trois épaisseurs.', EtatObjet::Bon, true],
        'videoprojecteur' => ['Audiovisuel', 'Vidéoprojecteur', 'Full HD, câble HDMI fourni.', EtatObjet::Bon, true],
        'ecran-projection' => ['Audiovisuel', 'Écran de projection', 'Sur trépied, 180 cm.', EtatObjet::Bon, true],
        'enceinte' => ['Audiovisuel', 'Enceinte Bluetooth', 'Autonomie de 12 heures.', EtatObjet::Bon, true],
        'velo-enfant' => ['Sport', 'Vélo enfant 20 pouces', 'Pour les 6 à 9 ans, casque fourni.', EtatObjet::Bon, true],
        'raquettes' => ['Sport', 'Raquettes de badminton', 'Quatre raquettes et un tube de volants.', EtatObjet::Bon, true],
        'paddle' => ['Sport', 'Paddle gonflable', 'Pagaie, pompe et leash fournis.', EtatObjet::Bon, true],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::OBJETS as $cle => [$categorie, $nom, $description, $etat, $actif]) {
            $objet = (new Objet())
                ->setCategorie($this->getReference(CategorieFixtures::reference($categorie), Categorie::class))
                ->setNom($nom)
                ->setDescription($description)
                ->setEtat($etat)
                ->setActif($actif);
            $manager->persist($objet);
            $this->addReference(self::reference($cle), $objet);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CategorieFixtures::class];
    }

    public static function reference(string $cle): string
    {
        return 'objet-'.$cle;
    }
}
