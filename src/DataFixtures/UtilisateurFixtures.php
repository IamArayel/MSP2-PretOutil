<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comptes de test, un par rôle, plus un compte désactivé (RG9).
 * Mot de passe commun, réservé aux environnements dev et test (voir README, section Installation).
 */
final class UtilisateurFixtures extends Fixture
{
    public const string MOT_DE_PASSE_TEST = 'PretOutil-Test-2026';

    /** Clé de référence => [email, nom, prénom, rôles, actif]. */
    private const array COMPTES = [
        'adherent' => ['adherent@pretoutil.test', 'Martin', 'Camille', [], true],
        'adherent-quota' => ['adherent.quota@pretoutil.test', 'Durand', 'Noah', [], true],
        'gestionnaire' => ['gestionnaire@pretoutil.test', 'Bernard', 'Léa', ['ROLE_MANAGER'], true],
        'administrateur' => ['admin@pretoutil.test', 'Petit', 'Sacha', ['ROLE_ADMIN'], true],
        'desactive' => ['desactive@pretoutil.test', 'Moreau', 'Inès', [], false],
    ];

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::COMPTES as $cle => [$email, $nom, $prenom, $roles, $actif]) {
            $utilisateur = (new Utilisateur())
                ->setEmail($email)
                ->setNom($nom)
                ->setPrenom($prenom)
                ->setRoles($roles)
                ->setActif($actif);
            $utilisateur->setMotDePasse($this->hasher->hashPassword($utilisateur, self::MOT_DE_PASSE_TEST));
            $manager->persist($utilisateur);
            $this->addReference(self::reference($cle), $utilisateur);
        }

        $manager->flush();
    }

    public static function reference(string $cle): string
    {
        return 'utilisateur-'.$cle;
    }
}
