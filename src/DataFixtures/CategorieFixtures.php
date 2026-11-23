<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Categorie;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Catégories du jeu d'essai (environnements dev et test uniquement).
 */
final class CategorieFixtures extends Fixture
{
    public const array LIBELLES = ['Outillage', 'Jardinage', 'Camping', 'Cuisine', 'Audiovisuel', 'Sport'];

    public function load(ObjectManager $manager): void
    {
        foreach (self::LIBELLES as $libelle) {
            $categorie = (new Categorie())->setLibelle($libelle);
            $manager->persist($categorie);
            $this->addReference(self::reference($libelle), $categorie);
        }

        $manager->flush();
    }

    public static function reference(string $libelle): string
    {
        return 'categorie-'.$libelle;
    }
}
