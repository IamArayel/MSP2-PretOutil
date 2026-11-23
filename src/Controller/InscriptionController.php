<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\InscriptionDto;
use App\Exception\EmailDejaUtiliseException;
use App\Form\InscriptionType;
use App\Service\InscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran E2 Inscription (US1) : affiche le formulaire, délègue la création
 * du compte à InscriptionService, puis renvoie vers la connexion.
 */
final class InscriptionController extends AbstractController
{
    #[Route('/inscription', name: 'app_inscription', methods: ['GET', 'POST'])]
    public function inscription(Request $request, InscriptionService $inscriptionService): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

        $dto = new InscriptionDto();
        $form = $this->createForm(InscriptionType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $inscriptionService->inscrire($dto);
                $this->addFlash('succes', 'Votre compte est créé. Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('app_connexion');
            } catch (EmailDejaUtiliseException $e) {
                $form->get('email')->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('inscription/index.html.twig', [
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
