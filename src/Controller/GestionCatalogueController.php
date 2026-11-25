<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ObjetDto;
use App\Entity\Objet;
use App\Entity\Utilisateur;
use App\Exception\ObjetDejaReserveException;
use App\Exception\PhotoIllisibleException;
use App\Form\ObjetType;
use App\Form\RechercheObjetType;
use App\Repository\ObjetRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\ObjetVoter;
use App\Service\ObjetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran E7 Gestion du catalogue (US6).
 *
 * Deux protections : access_control sur /gestion (ROLE_MANAGER) et ObjetVoter sur
 * chaque action. Les actions qui modifient les données sont en POST avec un jeton CSRF.
 */
#[Route('/gestion/catalogue')]
#[IsGranted(ObjetVoter::GERER)]
final class GestionCatalogueController extends AbstractController
{
    public function __construct(private readonly ObjetService $objetService)
    {
    }

    #[Route('', name: 'app_gestion_catalogue', methods: ['GET'])]
    public function index(
        Request $request,
        ObjetRepository $objetRepository,
        ReservationRepository $reservationRepository,
        #[MapQueryParameter] int $page = 1,
    ): Response {
        // Recherche par nom (#109) : une saisie invalide (trop longue) affiche toute la liste.
        $recherche = $this->createForm(RechercheObjetType::class);
        $recherche->handleRequest($request);
        $texte = $recherche->isSubmitted() && $recherche->isValid() ? $recherche->get('q')->getData() : null;

        $page = $objetRepository->listerPourGestion($page, $texte);

        return $this->render('gestion_catalogue/index.html.twig', [
            'page' => $page,
            'recherche' => $recherche,
            'texte_recherche' => $texte,
            // Une seule requête pour toute la page, au lieu d'un contrôle par ligne
            'deja_reserves' => $reservationRepository->objetsDejaReserves($page->elements),
        ]);
    }

    #[Route('/nouveau', name: 'app_gestion_catalogue_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request, #[CurrentUser] Utilisateur $gestionnaire): Response
    {
        $dto = new ObjetDto();
        $form = $this->createForm(ObjetType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $objet = $this->objetService->creer($dto, $gestionnaire);
                $this->addFlash('succes', \sprintf('« %s » a été ajouté au catalogue.', $objet->getNom()));

                return $this->redirectToRoute('app_gestion_catalogue');
            } catch (PhotoIllisibleException $e) {
                $this->erreurPhoto($form, $e);
            }
        }

        return $this->render('gestion_catalogue/formulaire.html.twig', [
            'form' => $form,
            'objet' => null,
        ], $this->statut($form->isSubmitted()));
    }

    #[Route('/{id}/modifier', name: 'app_gestion_catalogue_modifier', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ObjetVoter::GERER, subject: 'objet')]
    public function modifier(Objet $objet, Request $request, #[CurrentUser] Utilisateur $gestionnaire): Response
    {
        $dto = ObjetDto::depuisObjet($objet);
        $form = $this->createForm(ObjetType::class, $dto, ['avec_photo' => null !== $objet->getImage()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->objetService->modifier($objet, $dto, $gestionnaire);
                $this->addFlash('succes', \sprintf('« %s » a été modifié.', $objet->getNom()));

                return $this->redirectToRoute('app_gestion_catalogue');
            } catch (PhotoIllisibleException $e) {
                $this->erreurPhoto($form, $e);
            }
        }

        return $this->render('gestion_catalogue/formulaire.html.twig', [
            'form' => $form,
            'objet' => $objet,
        ], $this->statut($form->isSubmitted()));
    }

    #[Route('/{id}/desactiver', name: 'app_gestion_catalogue_desactiver', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ObjetVoter::GERER, subject: 'objet')]
    public function desactiver(Objet $objet, Request $request, #[CurrentUser] Utilisateur $gestionnaire): Response
    {
        if (!$this->jetonValide('desactiver-objet-'.$objet->getId(), $request)) {
            return $this->redirectToRoute('app_gestion_catalogue');
        }

        $this->objetService->desactiver($objet, $gestionnaire);
        $this->addFlash('succes', \sprintf('« %s » a été retiré du catalogue.', $objet->getNom()));

        return $this->redirectToRoute('app_gestion_catalogue');
    }

    #[Route('/{id}/reactiver', name: 'app_gestion_catalogue_reactiver', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ObjetVoter::GERER, subject: 'objet')]
    public function reactiver(Objet $objet, Request $request, #[CurrentUser] Utilisateur $gestionnaire): Response
    {
        if (!$this->jetonValide('reactiver-objet-'.$objet->getId(), $request)) {
            return $this->redirectToRoute('app_gestion_catalogue');
        }

        $this->objetService->reactiver($objet, $gestionnaire);
        $this->addFlash('succes', \sprintf('« %s » est de retour dans le catalogue.', $objet->getNom()));

        return $this->redirectToRoute('app_gestion_catalogue');
    }

    #[Route('/{id}/supprimer', name: 'app_gestion_catalogue_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ObjetVoter::SUPPRIMER, subject: 'objet', message: 'Cet objet a déjà été réservé : il ne peut pas être supprimé.')]
    public function supprimer(Objet $objet, Request $request, #[CurrentUser] Utilisateur $gestionnaire): Response
    {
        if (!$this->jetonValide('supprimer-objet-'.$objet->getId(), $request)) {
            return $this->redirectToRoute('app_gestion_catalogue');
        }

        $nom = $objet->getNom();

        try {
            $this->objetService->supprimer($objet, $gestionnaire);
            $this->addFlash('succes', \sprintf('« %s » a été supprimé définitivement.', $nom));
        } catch (ObjetDejaReserveException $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('app_gestion_catalogue');
    }

    /**
     * Jeton CSRF propre à l'action et à l'objet : un jeton de désactivation ne peut pas
     * servir à supprimer, ni à agir sur un autre objet.
     */
    private function jetonValide(string $identifiant, Request $request): bool
    {
        if ($this->isCsrfTokenValid($identifiant, $request->getPayload()->getString('_token'))) {
            return true;
        }

        $this->addFlash('erreur', 'La page a expiré. Merci de réessayer.');

        return false;
    }

    /** Photo valide en apparence mais impossible à décoder : erreur affichée sous le champ. */
    private function erreurPhoto(FormInterface $form, PhotoIllisibleException $e): void
    {
        $form->get('photo')->addError(new FormError($e->getMessage()));
    }

    /** 422 quand le formulaire envoyé contient des erreurs, comme pour l'inscription. */
    private function statut(bool $envoye): Response
    {
        return new Response(status: $envoye ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
