<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\StatutDuJour;
use App\Repository\ObjetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page de référence des composants de style (ticket #95), figure du dossier.
 *
 * Route déclarée en environnement de développement seulement (env: 'dev') : elle n'existe ni
 * en test ni en production, où l'adresse répond 404. Aucune donnée personnelle n'y est affichée.
 */
final class ComposantsController extends AbstractController
{
    #[Route('/dev/composants', name: 'app_composants', methods: ['GET'], env: 'dev')]
    public function index(ObjetRepository $objetRepository): Response
    {
        $objets = $objetRepository->findBy(['actif' => true], ['nom' => 'ASC'], 4);

        return $this->render('composants/index.html.twig', [
            // Une carte par statut, pour montrer les quatre badges.
            'cartes' => array_map(
                static fn (int $i) => ['objet' => $objets[$i], 'statut' => StatutDuJour::cases()[$i % 4]],
                array_keys($objets),
            ),
            'statuts' => StatutDuJour::cases(),
        ]);
    }
}
