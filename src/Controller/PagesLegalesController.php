<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages légales, ouvertes à tous (#106) : conditions générales d'utilisation, avec les
 * mentions légales, et politique de confidentialité, avec la section sur les cookies.
 * Contenu éditorial seul, dans les gabarits de templates/pages_legales/.
 */
final class PagesLegalesController extends AbstractController
{
    #[Route('/cgu', name: 'app_cgu', methods: ['GET'])]
    public function cgu(): Response
    {
        return $this->render('pages_legales/cgu.html.twig');
    }

    #[Route('/confidentialite', name: 'app_confidentialite', methods: ['GET'])]
    public function confidentialite(): Response
    {
        return $this->render('pages_legales/confidentialite.html.twig');
    }
}
