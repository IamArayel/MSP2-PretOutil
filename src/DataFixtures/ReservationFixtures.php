<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Objet;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\EtatObjet;
use App\Enum\StatutReservation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Réservations du jeu d'essai, dans chaque statut. Les dates sont relatives au jour du
 * chargement : les écrans montrent toujours des réservations à venir, en cours et passées.
 *
 * Cas couverts :
 * - l'adhérent « quota » a 3 réservations actives : une 4e doit être refusée (RG4) ;
 * - une sortie est à valider aujourd'hui et un retour est en retard (écran E6) ;
 * - les retours « abîmé » et « hors service » expliquent l'état des objets concernés (RG7).
 */
final class ReservationFixtures extends Fixture implements DependentFixtureInterface
{
    /** [adhérent, objet, début, fin, statut, état au retour] ; début et fin en jours par rapport à aujourd'hui. */
    private const array RESERVATIONS = [
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

    public function load(ObjectManager $manager): void
    {
        $aujourdhui = new \DateTimeImmutable('today');
        $gestionnaire = $this->getReference(UtilisateurFixtures::reference('gestionnaire'), Utilisateur::class);

        foreach (self::RESERVATIONS as [$adherent, $objet, $debut, $fin, $statut, $etatRetour]) {
            $dateDebut = $aujourdhui->modify(\sprintf('%+d days', $debut));
            $dateFin = $aujourdhui->modify(\sprintf('%+d days', $fin));

            $reservation = new Reservation(
                $this->getReference(UtilisateurFixtures::reference($adherent), Utilisateur::class),
                $this->getReference(ObjetFixtures::reference($objet), Objet::class),
                $dateDebut,
                $dateFin,
            );
            $reservation->setStatut($statut);

            if (StatutReservation::EnCours === $statut || StatutReservation::Terminee === $statut) {
                $reservation->enregistrerSortie($gestionnaire, $dateDebut->setTime(9, 0));
            }
            if (null !== $etatRetour) {
                $reservation->enregistrerRetour($gestionnaire, $dateFin->setTime(18, 0), $etatRetour);
            }

            $manager->persist($reservation);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UtilisateurFixtures::class, ObjetFixtures::class];
    }
}
