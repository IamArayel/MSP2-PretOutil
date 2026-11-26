<?php

declare(strict_types=1);

namespace App\Demo;

use App\Enum\EtatObjet;
use App\Enum\StatutReservation;

/**
 * Données du jeu d'essai, partagées par les fixtures (développement et test) et par la commande
 * app:demo:initialiser (démonstration en production, ticket #115) : une seule source.
 *
 * Plus de 20 objets pour la pagination (US3), un objet abîmé, un hors service et un retiré (RG5),
 * un compte par rôle et un compte désactivé (RG9), des réservations dans chaque statut.
 */
final class JeuDEssai
{
    /**
     * Mot de passe des comptes en développement et en test (README, section Installation).
     * Public : il est dans le dépôt. Refusé par app:demo:initialiser, qui demande un mot de passe secret.
     */
    public const string MOT_DE_PASSE_TEST = 'PretOutil-Test-2026';

    public const array CATEGORIES = ['Outillage', 'Jardinage', 'Camping', 'Cuisine', 'Audiovisuel', 'Sport'];

    /** Clé => [email, nom, prénom, rôles, actif]. */
    public const array COMPTES = [
        'adherent' => ['adherent@pretoutil.test', 'Martin', 'Camille', [], true],
        'adherent-quota' => ['adherent.quota@pretoutil.test', 'Durand', 'Noah', [], true],
        'gestionnaire' => ['gestionnaire@pretoutil.test', 'Bernard', 'Léa', ['ROLE_MANAGER'], true],
        'administrateur' => ['admin@pretoutil.test', 'Petit', 'Sacha', ['ROLE_ADMIN'], true],
        'desactive' => ['desactive@pretoutil.test', 'Moreau', 'Inès', [], false],
    ];

    /** Clé => [catégorie, nom, description, état, actif]. */
    public const array OBJETS = [
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

    /**
     * Clé d'objet => photo source de fixtures/photos-objets/ ; les autres objets ont la vignette de remplacement.
     * Chaque photo passe par StockagePhotoObjet, comme une photo envoyée depuis E7 (#106).
     */
    public const array PHOTOS = [
        'perceuse' => 'perceuse.webp', 'scie-sauteuse' => 'scie-sauteuse.webp', 'ponceuse' => 'ponceuse.webp', 'meuleuse' => 'meuleuse.webp',
        'taille-haie' => 'taille-haie.webp', 'brouette' => 'brouette.webp', 'tente' => 'tente.webp',
        'rechaud' => 'rechaud.webp', 'glaciere' => 'glaciere.webp', 'lampe-frontale' => 'lampe-frontale.webp',
        'appareil-raclette' => 'appareil-raclette.webp', 'machine-pates' => 'machine-pates.webp',
        'videoprojecteur' => 'videoprojecteur.webp', 'enceinte' => 'enceinte.webp',
    ];

    /**
     * [compte, objet, début, fin, statut, état au retour] ; début et fin en jours par rapport au jour
     * du chargement, pour que les écrans montrent toujours des réservations à venir, en cours et passées.
     *
     * - le compte « quota » a 3 réservations actives : une 4e doit être refusée (RG4) ;
     * - une sortie est à valider aujourd'hui et un retour est en retard (écran E6) ;
     * - les retours « abîmé » et « hors service » expliquent l'état des objets concernés (RG7).
     */
    public const array RESERVATIONS = [
        ['adherent', 'tente', 7, 9, StatutReservation::Confirmee, null],
        ['adherent', 'videoprojecteur', -2, 1, StatutReservation::EnCours, null],
        ['adherent', 'tondeuse', 3, 4, StatutReservation::Annulee, null],
        ['adherent', 'perceuse', -20, -18, StatutReservation::Terminee, EtatObjet::Bon],
        ['adherent', 'ponceuse', -30, -28, StatutReservation::Terminee, EtatObjet::Abime],
        ['adherent', 'meuleuse', -40, -38, StatutReservation::Terminee, EtatObjet::HorsService],
        ['adherent-quota', 'scie-sauteuse', 2, 4, StatutReservation::Confirmee, null],
        ['adherent-quota', 'taille-haie', 10, 12, StatutReservation::Confirmee, null],
        ['adherent-quota', 'raquettes', -1, 2, StatutReservation::EnCours, null],
        ['gestionnaire', 'enceinte', 0, 2, StatutReservation::Confirmee, null],
        ['administrateur', 'glaciere', -5, -1, StatutReservation::EnCours, null],
    ];
}
