<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;

/**
 * Élément concerné par une action journalisée (champ cible de audit_log) :
 * type (utilisateur, objet, reservation) et identifiant PostgreSQL.
 */
#[ODM\EmbeddedDocument]
class CibleAudit
{
    public const array TYPES = ['utilisateur', 'objet', 'reservation'];

    #[ODM\Field(type: 'string')]
    private string $type;

    /** Écrit même s'il vaut null : le validateur exige la présence du champ. */
    #[ODM\Field(type: 'int', nullable: true)]
    private ?int $id;

    public function __construct(string $type, ?int $id)
    {
        if (!\in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('Type de cible inconnu : "%s".', $type));
        }

        $this->type = $type;
        $this->id = $id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
