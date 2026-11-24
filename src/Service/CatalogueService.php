<?php

declare(strict_types=1);

namespace App\Service;

use App\Catalogue\FicheObjet;
use App\Catalogue\PageCatalogue;
use App\Dto\CriteresCatalogue;
use App\Entity\Objet;
use App\Enum\EtatObjet;
use App\Enum\StatutDuJour;
use App\Enum\StatutReservation;
use App\Repository\ObjetRepository;
use App\Repository\ReservationRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Lecture du catalogue pour les écrans E3 et E4 : assemble les résultats des
 * repositories et en déduit le statut du jour de chaque objet.
 */
class CatalogueService
{
    public function __construct(
        private readonly ObjetRepository $objetRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly ClockInterface $horloge,
    ) {
    }

    public function rechercher(CriteresCatalogue $criteres, int $numeroPage): PageCatalogue
    {
        $aujourdhui = $this->aujourdhui();
        $page = $this->objetRepository->rechercherCatalogue($criteres, $aujourdhui, $numeroPage);
        $reservations = $this->reservationRepository->statutsActifsAuJour($page->elements, $aujourdhui);

        $statuts = [];
        foreach ($page->elements as $objet) {
            $statuts[$objet->getId()] = $this->statutDuJour($objet, $reservations[$objet->getId()] ?? []);
        }

        return new PageCatalogue($page, $statuts);
    }

    public function fiche(Objet $objet): FicheObjet
    {
        $aujourdhui = $this->aujourdhui();
        $reservations = $this->reservationRepository->statutsActifsAuJour([$objet], $aujourdhui);

        return new FicheObjet(
            $objet,
            $this->statutDuJour($objet, $reservations[$objet->getId()] ?? []),
            $this->reservationRepository->periodesReservees($objet, $aujourdhui),
        );
    }

    /**
     * Priorité : hors service, puis sorti (y compris un retour en retard), puis réservé aujourd'hui.
     *
     * @param list<StatutReservation|string> $statutsReservations réservations actives qui couvrent aujourd'hui
     */
    private function statutDuJour(Objet $objet, array $statutsReservations): StatutDuJour
    {
        if (EtatObjet::HorsService === $objet->getEtat()) {
            return StatutDuJour::HorsService;
        }

        $statuts = array_map(
            static fn (StatutReservation|string $statut): StatutReservation => $statut instanceof StatutReservation ? $statut : StatutReservation::from($statut),
            $statutsReservations,
        );

        return match (true) {
            \in_array(StatutReservation::EnCours, $statuts, true) => StatutDuJour::Sorti,
            \in_array(StatutReservation::Confirmee, $statuts, true) => StatutDuJour::Reserve,
            default => StatutDuJour::Disponible,
        };
    }

    private function aujourdhui(): \DateTimeImmutable
    {
        return $this->horloge->now()->setTime(0, 0);
    }
}
