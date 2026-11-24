<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Enum\ActionAudit;
use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * RG8 : chaque échec de connexion est journalisé (CONNEXION_ECHOUEE).
 *
 * Le journal ne révèle pas si le compte existe : l'acteur n'a jamais d'identifiant,
 * seulement l'email saisi, et le motif ne distingue pas un email inconnu d'un mauvais
 * mot de passe ou d'un compte désactivé. Seul le blocage (login_throttling) est signalé.
 * L'adresse IP n'est pas conservée (minimisation des données, RGPD).
 */
#[AsEventListener(event: LoginFailureEvent::class)]
final class ConnexionEchoueeListener
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function __invoke(LoginFailureEvent $evenement): void
    {
        $emailSaisi = mb_strtolower(trim($evenement->getRequest()->getPayload()->getString('email')));
        $erreur = $evenement->getException();

        $details = $erreur instanceof TooManyLoginAttemptsAuthenticationException
            ? ['motif' => 'blocage', 'minutes' => (int) ($erreur->getMessageData()['%minutes%'] ?? 15)]
            : ['motif' => 'echec'];

        $this->auditLogger->journaliserAnonyme(ActionAudit::ConnexionEchouee, '' === $emailSaisi ? '(vide)' : $emailSaisi, $details);
    }
}
