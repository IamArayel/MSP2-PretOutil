<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Exception\ReservationRefuseeException;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationService;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran E5 Mes réservations (US5) : liste des réservations de l'adhérent connecté
 * et annulation jusqu'à la veille du début (RG6).
 *
 * L'adhérent ne voit que ses propres réservations ; le droit d'annuler est décidé
 * par ReservationVoter::ANNULER, les règles d'état par ReservationService.
 */
#[IsGranted('ROLE_USER')]
final class MesReservationsController extends AbstractController
{
    #[Route('/mes-reservations', name: 'app_mes_reservations', methods: ['GET'])]
    public function index(
        #[CurrentUser] Utilisateur $adherent,
        ReservationRepository $reservationRepository,
        ClockInterface $horloge,
    ): Response {
        return $this->render('mes_reservations/index.html.twig', [
            'reservations' => $reservationRepository->listerPourAdherent($adherent),
            'actives' => $reservationRepository->compterActives($adherent),
            'quota' => ReservationService::QUOTA_RESERVATIONS_ACTIVES,
            'aujourdhui' => $horloge->now(),
        ]);
    }

    #[Route('/mes-reservations/{id}/annuler', name: 'app_mes_reservations_annuler', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ReservationVoter::ANNULER, subject: 'reservation', message: 'Cette réservation ne peut pas être annulée.')]
    public function annuler(
        Reservation $reservation,
        Request $request,
        #[CurrentUser] Utilisateur $adherent,
        ReservationService $reservationService,
    ): Response {
        // Jeton propre à la réservation : il ne peut pas servir à en annuler une autre.
        if (!$this->isCsrfTokenValid('annuler-reservation-'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('erreur', 'La page a expiré. Merci de réessayer.');

            return $this->redirectToRoute('app_mes_reservations', status: Response::HTTP_SEE_OTHER);
        }

        try {
            $reservationService->annuler($reservation, $adherent);
            $this->addFlash('succes', \sprintf(
                'Votre réservation de « %s » du %s au %s est annulée.',
                $reservation->getObjet()->getNom(),
                $reservation->getDateDebut()->format('d/m/Y'),
                $reservation->getDateFin()->format('d/m/Y'),
            ));
        } catch (ReservationRefuseeException $refus) {
            $this->addFlash('erreur', $refus->getMessage());
        }

        return $this->redirectToRoute('app_mes_reservations', status: Response::HTTP_SEE_OTHER);
    }
}
