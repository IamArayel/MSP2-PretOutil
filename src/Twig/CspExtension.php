<?php

declare(strict_types=1);

namespace App\Twig;

use App\EventSubscriber\EntetesSecuriteSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * csp_nonce() : nonce de la requête en cours, à placer sur chaque balise <script> des
 * gabarits. Le même nonce figure dans l'en-tête Content-Security-Policy.
 *
 * Stocké dans la requête, et non dans ce service : en mode worker, FrankenPHP garde les
 * services en mémoire d'une requête à l'autre.
 */
final class CspExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    #[AsTwigFunction('csp_nonce')]
    public function nonce(): string
    {
        $requete = $this->requestStack->getMainRequest();

        return null === $requete ? '' : EntetesSecuriteSubscriber::nonce($requete);
    }
}
