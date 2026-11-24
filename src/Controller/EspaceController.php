<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Entrée de l'espace administrateur : renvoie vers l'écran E8 Gestion des comptes.
 *
 * Deux protections, comme les autres écrans : access_control (^/admin) et IsGranted
 * (remarque de relecture de J7 : la page n'avait que access_control).
 */
final class EspaceController extends AbstractController
{
    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(): Response
    {
        return $this->redirectToRoute('app_gestion_comptes');
    }
}
