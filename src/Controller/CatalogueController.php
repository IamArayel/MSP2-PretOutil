<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CriteresCatalogue;
use App\Entity\Objet;
use App\Form\FiltreCatalogueType;
use App\Service\CatalogueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écrans E3 Catalogue et E4 Détail d'un objet (US3), ouverts aux visiteurs.
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
        // Un objet retiré du catalogue n'est plus consultable, comme un objet inconnu.
        if (!$objet->isActif()) {
            throw $this->createNotFoundException('Objet introuvable.');
        }

        return $this->render('catalogue/objet.html.twig', [
            'fiche' => $this->catalogueService->fiche($objet),
        ]);
    }
}
