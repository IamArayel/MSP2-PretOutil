<?php

declare(strict_types=1);

namespace App\Service;

use App\Document\ActeurAudit;
use App\Document\AuditLog;
use App\Document\CibleAudit;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Journal d'audit des actions sensibles (RG8), stocké dans MongoDB (collection audit_log).
 *
 * Appelé par les services métier après le COMMIT PostgreSQL : on ne journalise que ce
 * qui a réellement eu lieu. Si MongoDB est indisponible ou refuse le document, l'erreur
 * est tracée dans les logs et l'action métier, déjà enregistrée, n'est pas annulée.
 */
class AuditLogger
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly ClockInterface $horloge,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param 'utilisateur'|'objet'|'reservation' $cibleType
     * @param array<string, mixed>                $details  contenu libre selon l'action
     */
    public function journaliser(
        ActionAudit $action,
        ?Utilisateur $acteur,
        string $cibleType,
        ?int $cibleId,
        array $details = [],
    ): void {
        $this->enregistrer(new AuditLog(
            $this->horloge->now(),
            $action,
            new ActeurAudit($acteur?->getId(), $acteur?->getEmail() ?? 'systeme'),
            new CibleAudit($cibleType, $cibleId),
            $details,
        ));
    }

    /**
     * Action sans utilisateur authentifié (connexion échouée) : seul l'email saisi est
     * connu. L'identifiant reste null, que le compte existe ou non.
     *
     * @param array<string, mixed> $details
     */
    public function journaliserAnonyme(ActionAudit $action, string $emailSaisi, array $details = []): void
    {
        $this->enregistrer(new AuditLog(
            $this->horloge->now(),
            $action,
            new ActeurAudit(null, $emailSaisi),
            new CibleAudit('utilisateur', null),
            $details,
        ));
    }

    private function enregistrer(AuditLog $entree): void
    {
        try {
            $this->documentManager->persist($entree);
            $this->documentManager->flush();
        } catch (\Throwable $erreur) {
            $this->logger->error('Journal d\'audit : écriture impossible ({action}).', [
                'action' => $entree->getAction()->value,
                'exception' => $erreur,
            ]);
        } finally {
            // Le journal n'est jamais relu dans la même requête : on libère la mémoire.
            $this->documentManager->detach($entree);
        }
    }
}
