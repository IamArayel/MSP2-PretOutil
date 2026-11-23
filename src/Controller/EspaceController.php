<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages d'entrée provisoires des trois espaces protégés, pour vérifier
 * l'access_control dès J3. Chacune sera remplacée par son écran :
 * E5 Mes réservations (J6), E6 Sorties et retours (J6), E8 Gestion des comptes.
 * Les droits sont vérifiés par access_control (config/packages/security.yaml).
 */
final class EspaceController extends AbstractController
{
    #[Route('/mes-reservations', name: 'app_mes_reservations', methods: ['GET'])]
    public function mesReservations(): Response
    {
        return $this->render('espace/index.html.twig', [
            'titre' => 'Mes réservations',
            'role' => 'Adhérent',
        ]);
    }

    #[Route('/gestion', name: 'app_gestion', methods: ['GET'])]
    public function gestion(): Response
    {
        return $this->render('espace/index.html.twig', [
            'titre' => 'Sorties et retours',
            'role' => 'Gestionnaire',
        ]);
    }

    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function admin(): Response
    {
        return $this->render('espace/index.html.twig', [
            'titre' => 'Administration',
            'role' => 'Administrateur',
        ]);
    }
}
