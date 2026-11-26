<?php

declare(strict_types=1);

namespace App\Demo;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\StatutReservation;
use App\Service\StockagePhotoObjet;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Écrit le jeu d'essai (JeuDEssai) en base, en une seule fois. Utilisé par les fixtures, avec le
 * mot de passe public de développement, et par app:demo:initialiser, avec un mot de passe secret.
 * Ne vide rien : c'est à l'appelant de partir d'une base vide.
 */
final class ChargeurJeuDEssai
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly StockagePhotoObjet $stockagePhoto,
        private readonly ClockInterface $horloge,
        #[Autowire('%kernel.project_dir%/fixtures/photos-objets')]
        private readonly string $dossierPhotos,
    ) {
    }

    /**
     * @return array{categories: int, comptes: int, objets: int, photos: int, reservations: int}
     */
    public function charger(ObjectManager $manager, string $motDePasse): array
    {
        $categories = [];
        foreach (JeuDEssai::CATEGORIES as $libelle) {
            $categories[$libelle] = (new Categorie())->setLibelle($libelle);
            $manager->persist($categories[$libelle]);
        }

        $comptes = [];
        foreach (JeuDEssai::COMPTES as $cle => [$email, $nom, $prenom, $roles, $actif]) {
            $compte = (new Utilisateur())
                ->setEmail($email)
                ->setNom($nom)
                ->setPrenom($prenom)
                ->setRoles($roles)
                ->setActif($actif);
            $compte->setMotDePasse($this->hasher->hashPassword($compte, $motDePasse));
            $manager->persist($compte);
            $comptes[$cle] = $compte;
        }

        $objets = [];
        foreach (JeuDEssai::OBJETS as $cle => [$categorie, $nom, $description, $etat, $actif]) {
            $photo = JeuDEssai::PHOTOS[$cle] ?? null;
            $objets[$cle] = (new Objet())
                ->setCategorie($categories[$categorie])
                ->setNom($nom)
                ->setDescription($description)
                ->setEtat($etat)
                ->setActif($actif)
                ->setImage(null === $photo ? null : $this->stockagePhoto->enregistrer(new File($this->dossierPhotos.'/'.$photo)));
            $manager->persist($objets[$cle]);
        }

        $aujourdhui = $this->horloge->now()->setTime(0, 0);
        $gestionnaire = $comptes['gestionnaire'];
        foreach (JeuDEssai::RESERVATIONS as [$compte, $objet, $debut, $fin, $statut, $etatRetour]) {
            $dateDebut = $aujourdhui->modify(\sprintf('%+d days', $debut));
            $dateFin = $aujourdhui->modify(\sprintf('%+d days', $fin));

            $reservation = (new Reservation($comptes[$compte], $objets[$objet], $dateDebut, $dateFin))->setStatut($statut);
            if (StatutReservation::EnCours === $statut || StatutReservation::Terminee === $statut) {
                $reservation->enregistrerSortie($gestionnaire, $dateDebut->setTime(9, 0));
            }
            if (null !== $etatRetour) {
                $reservation->enregistrerRetour($gestionnaire, $dateFin->setTime(18, 0), $etatRetour);
            }
            $manager->persist($reservation);
        }

        $manager->flush();

        return [
            'categories' => \count($categories),
            'comptes' => \count($comptes),
            'objets' => \count($objets),
            'photos' => \count(JeuDEssai::PHOTOS),
            'reservations' => \count(JeuDEssai::RESERVATIONS),
        ];
    }
}
