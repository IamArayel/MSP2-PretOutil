<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Visiteur sur une page réservée (Style-C2, #101) : redirection vers la connexion (E1) avec un
 * message qui explique pourquoi. Après la connexion, Symfony le renvoie sur la page demandée
 * (adresse mémorisée en session par le pare-feu avant d'appeler ce point d'entrée).
 */
final class ConnexionRequiseEntryPoint implements AuthenticationEntryPointInterface
{
    public const string MESSAGE = 'Connectez-vous pour accéder à cette page : elle s\'ouvrira juste après.';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        // Seulement pour une page ouverte par un lien : un formulaire envoyé (POST) n'est pas rejoué.
        if ($request->isMethod('GET') && $request->hasSession() && ($session = $request->getSession()) instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('information', self::MESSAGE);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_connexion'));
    }
}
