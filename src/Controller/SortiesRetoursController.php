<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\EtatObjet;
use App\Exception\ReservationRefuseeException;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran E6 Sorties et retours (US7, US8, RG7), réservé aux gestionnaires.
 *
 * Deux protections : access_control sur /gestion et IsGranted ci-dessous. Les transitions
 * sont en POST avec un jeton CSRF propre à l'action et à la réservation ; les règles sont
 * dans ReservationService, le contrôleur affiche son message en cas de refus.
 */
#[Route('/gestion/sorties-retours')]
#[IsGranted('ROLE_MANAGER')]
final class SortiesRetoursController extends AbstractController
{
    public function __construct(private readonly ReservationService $reservationService)
    {
    }

    #[Route('', name: 'app_sorties_retours', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository, ClockInterface $horloge): Response
    {
        $aujourdhui = $horloge->now();

        return $this->render('sorties_retours/index.html.twig', [
            'sorties' => $reservationRepository->sortiesAValider($aujourdhui),
            'retours' => $reservationRepository->retoursAttendus(),
            'etats' => EtatObjet::cases(),
            'aujourdhui' => $aujourdhui,
        ]);
    }

    #[Route('/{id}/sortie', name: 'app_sorties_retours_sortie', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sortie(Reservation $reservation, Request $request, #[CurrentUser] Utilisateur $gestionnaire): Response
    {
        if ($this->jetonValide('sortie-reservation-'.$reservation->getId(), $request)) {
            try {
                $this->reservationService->validerSortie($reservation, $gestionnaire);
                $this->addFlash('succes', \sprintf(
                    'Sortie validée : %s remis à %s.',
                    $reservation->getObjet()->getNom(),
                    $this->nomAdherent($reservation),
                ));
            } catch (ReservationRefuseeException $refus) {
                $this->addFlash('erreur', $refus->getMessage());
            }
        }

        return $this->redirectToRoute('app_sorties_retours', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/retour', name: 'app_sorties_retours_retour', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function retour(Reservation $reservation, Request $request, #[CurrentUser] Utilisateur $gestionnaire): Response
    {
        if (!$this->jetonValide('retour-reservation-'.$reservation->getId(), $request)) {
            return $this->redirectToRoute('app_sorties_retours', status: Response::HTTP_SEE_OTHER);
        }

        // Valeur reçue d'une liste fermée : toute autre valeur est refusée.
        $etat = EtatObjet::tryFrom($request->getPayload()->getString('etat'));
        if (null === $etat) {
            $this->addFlash('erreur', 'Choisissez l\'état de l\'objet au retour : bon, abîmé ou hors service.');

            return $this->redirectToRoute('app_sorties_retours', status: Response::HTTP_SEE_OTHER);
        }

        try {
            $aSignaler = $this->reservationService->enregistrerRetour($reservation, $gestionnaire, $etat);
            $this->addFlash('succes', \sprintf(
                'Retour enregistré : %s, état « %s ».',
                $reservation->getObjet()->getNom(),
                $etat->libelle(),
            ));

            // RG7 : objet hors service, réservations à venir signalées, pas annulées.
            if (EtatObjet::HorsService === $etat) {
                $this->addFlash('avertissement', $this->messageReservationsASignaler($reservation, $aSignaler));
            }
        } catch (ReservationRefuseeException $refus) {
            $this->addFlash('erreur', $refus->getMessage());
        }

        return $this->redirectToRoute('app_sorties_retours', status: Response::HTTP_SEE_OTHER);
    }

    /** @param list<Reservation> $aSignaler */
    private function messageReservationsASignaler(Reservation $retour, array $aSignaler): string
    {
        $objet = $retour->getObjet()->getNom();

        if ([] === $aSignaler) {
            return \sprintf('L\'objet « %s » est maintenant hors service : il n\'est plus réservable. Aucune réservation à venir.', $objet);
        }

        $lignes = array_map(
            fn (Reservation $r): string => \sprintf(
                '%s (%s), du %s au %s',
                $this->nomAdherent($r),
                $r->getUtilisateur()->getEmail(),
                $r->getDateDebut()->format('d/m/Y'),
                $r->getDateFin()->format('d/m/Y'),
            ),
            $aSignaler,
        );

        $nombre = \count($aSignaler);

        return \sprintf(
            'L\'objet « %s » est maintenant hors service : il n\'est plus réservable. %s, non %s automatiquement : %s.',
            $objet,
            1 === $nombre
                ? 'Prévenez l\'adhérent de sa réservation à venir'
                : \sprintf('Prévenez les adhérents de ses %d réservations à venir', $nombre),
            1 === $nombre ? 'annulée' : 'annulées',
            implode(' ; ', $lignes),
        );
    }

    private function nomAdherent(Reservation $reservation): string
    {
        return $reservation->getUtilisateur()->getPrenom().' '.$reservation->getUtilisateur()->getNom();
    }

    private function jetonValide(string $identifiant, Request $request): bool
    {
        if ($this->isCsrfTokenValid($identifiant, $request->getPayload()->getString('_token'))) {
            return true;
        }

        $this->addFlash('erreur', 'La page a expiré. Merci de réessayer.');

        return false;
    }
}
