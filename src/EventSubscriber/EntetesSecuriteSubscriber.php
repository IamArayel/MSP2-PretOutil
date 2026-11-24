<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En-têtes de sécurité HTTP de chaque réponse de l'application (veille OWASP, A02 et A05 :
 * docs/securite/veille-owasp.md).
 *
 * La politique de sécurité du contenu (CSP) n'autorise que les scripts qui portent le
 * nonce de la requête : un script injecté dans une page (XSS) ne s'exécute pas, même si
 * l'échappement de Twig était contourné. Le nonce est tiré au hasard à chaque requête et
 * placé sur les balises <script> par csp_nonce() (CspExtension).
 */
final class EntetesSecuriteSubscriber implements EventSubscriberInterface
{
    /** Attribut de la requête qui porte le nonce, partagé avec CspExtension. */
    public const string ATTRIBUT_NONCE = '_csp_nonce';

    public function __construct(
        // 0 en développement : HSTS sur localhost s'imposerait aux autres projets locaux.
        #[Autowire('%app.hsts_max_age%')]
        private readonly int $hstsMaxAge,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'ajouterEntetes'];
    }

    /** Nonce de la requête, créé au premier appel (16 octets aléatoires, en base64). */
    public static function nonce(Request $request): string
    {
        if (!$request->attributes->has(self::ATTRIBUT_NONCE)) {
            $request->attributes->set(self::ATTRIBUT_NONCE, base64_encode(random_bytes(16)));
        }

        return (string) $request->attributes->get(self::ATTRIBUT_NONCE);
    }

    public function ajouterEntetes(ResponseEvent $evenement): void
    {
        if (!$evenement->isMainRequest()) {
            return;
        }

        $requete = $evenement->getRequest();
        $entetes = $evenement->getResponse()->headers;

        // Le navigateur ne devine pas le type d'un fichier : un texte ne devient pas un script.
        $entetes->set('X-Content-Type-Options', 'nosniff');
        // Aucune page ne peut être affichée dans un cadre d'un autre site (clickjacking).
        $entetes->set('X-Frame-Options', 'DENY');
        // Adresse complète jamais transmise à un autre site (identifiants dans les URL).
        $entetes->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Fonctions du navigateur que l'application n'utilise pas.
        $entetes->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($this->hstsMaxAge > 0 && $requete->isSecure()) {
            $entetes->set('Strict-Transport-Security', \sprintf('max-age=%d; includeSubDomains', $this->hstsMaxAge));
        }

        // Page d'erreur de débogage de Symfony (styles et scripts en ligne) : laissée lisible.
        if ($entetes->has('X-Debug-Exception')) {
            return;
        }

        $entetes->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            // 'strict-dynamic' : les modules chargés par un script autorisé (importmap) le sont aussi.
            \sprintf("script-src 'nonce-%s' 'strict-dynamic'", self::nonce($requete)),
            "style-src 'self'",
            "img-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));
    }
}
