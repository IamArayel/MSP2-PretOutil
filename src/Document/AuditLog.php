<?php

declare(strict_types=1);

namespace App\Document;

use App\Enum\ActionAudit;
use App\Repository\AuditLogRepository;
use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;

/**
 * Entrée du journal d'audit (RG8, US10), collection MongoDB audit_log.
 *
 * Le mapping suit le validateur $jsonSchema de docs/conception/audit_log.mongodb.js,
 * qui refuse tout document non conforme (additionalProperties: false). Le journal est
 * en ajout seul : le document est en lecture seule pour l'ODM, et le compte applicatif
 * n'a que les droits find et insert.
 */
#[ODM\Document(collection: 'audit_log', repositoryClass: AuditLogRepository::class, readOnly: true)]
class AuditLog
{
    #[ODM\Id]
    private ?string $id = null;

    /** Date et heure de l'action, stockées en UTC (type BSON date). */
    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $horodatage;

    #[ODM\Field(type: 'string', enumType: ActionAudit::class)]
    private ActionAudit $action;

    #[ODM\EmbedOne(targetDocument: ActeurAudit::class)]
    private ActeurAudit $acteur;

    #[ODM\EmbedOne(targetDocument: CibleAudit::class)]
    private CibleAudit $cible;

    /**
     * Contenu libre selon l'action : c'est la raison du choix de MongoDB.
     * Le type hash est toujours écrit comme un objet BSON, même vide ({} et non []).
     *
     * @var array<string, mixed>
     */
    #[ODM\Field(type: 'hash')]
    private array $details;

    /** @param array<string, mixed> $details */
    public function __construct(
        \DateTimeImmutable $horodatage,
        ActionAudit $action,
        ActeurAudit $acteur,
        CibleAudit $cible,
        array $details = [],
    ) {
        $this->horodatage = $horodatage;
        $this->action = $action;
        $this->acteur = $acteur;
        $this->cible = $cible;
        $this->details = $details;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getHorodatage(): \DateTimeImmutable
    {
        return $this->horodatage;
    }

    public function getAction(): ActionAudit
    {
        return $this->action;
    }

    public function getActeur(): ActeurAudit
    {
        return $this->acteur;
    }

    public function getCible(): CibleAudit
    {
        return $this->cible;
    }

    /** @return array<string, mixed> */
    public function getDetails(): array
    {
        return $this->details;
    }
}
