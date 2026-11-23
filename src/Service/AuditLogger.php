<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Utilisateur;
use App\Enum\ActionAudit;

/**
 * Journal d'audit des actions sensibles (RG8), stocké dans MongoDB.
 *
 * J3 : la signature est posée pour que les services métier appellent déjà
 * le journal ; l'écriture du document AuditLog (Doctrine ODM) est ajoutée à J6
 * sans modifier les appelants.
 */
class AuditLogger
{
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
        // Branché à J6 : création et persistance d'un document AuditLog.
    }
}
