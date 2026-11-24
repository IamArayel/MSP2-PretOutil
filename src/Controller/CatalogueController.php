<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CriteresCatalogue;
use App\Dto\ReservationDto;
use App\Entity\Objet;
use App\Entity\Utilisateur;
use App\Exception\ReservationRefuseeException;
use App\Form\FiltreCatalogueType;
use App\Form\ReservationType;
use App\Service\CatalogueService;
use App\Service\ReservationService;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écrans E3 Catalogue et E4 Détail d'un objet (US3), ouverts aux visiteurs,
 * et réservation depuis E4 (US4), réservée aux adhérents.
 */
final class CatalogueController extends AbstractController
{
    public function __construct(private readonly CatalogueService $catalogueService)
    {
    }

    #[Route('/catalogue', name: 'app_catalogue', methods: ['GET'])]
    public function index(Request $request, #[MapQueryParameter] int $page = 1): Response
    {
        $criteres = new CriteresCatalogue();
        $filtres = $this->createForm(FiltreCatalogueType::class, $criteres);
        $filtres->handleRequest($request);

        // Filtres invalides : les erreurs sont affichées et le catalogue complet est proposé.
        if ($filtres->isSubmitted() && !$filtres->isValid()) {
            $criteres = new CriteresCatalogue();
        }

        return $this->render('catalogue/index.html.twig', [
            'filtres' => $filtres,
            'catalogue' => $this->catalogueService->rechercher($criteres, $page),
            'criteres' => $criteres,
        ]);
    }

    #[Route('/catalogue/{id}', name: 'app_catalogue_objet', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function objet(Objet $objet): Response
    {
        $this->verifierDansLeCatalogue($objet);

        return $this->afficherFiche($objet, $this->formulaireReservation($objet));
    }

    /**
     * Panneau « Réserver cet objet » de l'écran E4 (US4). Le contrôleur ne connaît aucune
     * règle : il transmet la période à ReservationService et affiche le message de refus.
     */
    #[Route('/catalogue/{id}/reserver', name: 'app_catalogue_reserver', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reserver(
        Objet $objet,
        Request $request,
        #[CurrentUser] Utilisateur $adherent,
        ReservationService $reservationService,
    ): Response {
        $this->verifierDansLeCatalogue($objet);

        $dto = new ReservationDto();
        $form = $this->formulaireReservation($objet, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $reservationService->reserver($adherent, $objet, $dto->debut, $dto->fin);
                $this->addFlash('succes', \sprintf(
                    'Réservation confirmée : %s du %s au %s.',
                    $objet->getNom(),
                    $dto->debut->format('d/m/Y'),
                    $dto->fin->format('d/m/Y'),
                ));

                // 303 : après un POST réussi, le navigateur affiche E5 en GET (pas de double envoi).
                return $this->redirectToRoute('app_mes_reservations', status: Response::HTTP_SEE_OTHER);
            } catch (ReservationRefuseeException $refus) {
                $form->addError(new FormError($refus->getMessage()));
            }
        }

        // Refus : la fiche est réaffichée avec le message et les dates saisies.
        return $this->afficherFiche($objet, $form, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Un objet retiré du catalogue n'est plus consultable, comme un objet inconnu. */
    private function verifierDansLeCatalogue(Objet $objet): void
    {
        if (!$objet->isActif()) {
            throw $this->createNotFoundException('Objet introuvable.');
        }
    }

    private function formulaireReservation(Objet $objet, ?ReservationDto $dto = null): FormInterface
    {
        return $this->createForm(ReservationType::class, $dto ?? new ReservationDto(), [
            'action' => $this->generateUrl('app_catalogue_reserver', ['id' => $objet->getId()]),
        ]);
    }

    private function afficherFiche(Objet $objet, FormInterface $reservation, int $statut = Response::HTTP_OK): Response
    {
        return $this->render('catalogue/objet.html.twig', [
            'fiche' => $this->catalogueService->fiche($objet),
            'reservation' => $reservation,
        ], new Response(status: $statut));
    }
}
