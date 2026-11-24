<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;

/**
 * Auteur d'une action journalisée (champ acteur de audit_log).
 *
 * L'email est recopié, pas référencé : le journal reste lisible même si le compte
 * change ou disparaît. id vaut null pour une action anonyme (connexion échouée).
 */
#[ODM\EmbeddedDocument]
class ActeurAudit
{
    /** Écrit même s'il vaut null : le validateur exige la présence du champ. */
    #[ODM\Field(type: 'int', nullable: true)]
    private ?int $id;

    #[ODM\Field(type: 'string')]
    private string $email;

    public function __construct(?int $id, string $email)
    {
        $this->id = $id;
        // Limite du validateur (maxLength 180), utile pour un email saisi lors d'une connexion échouée
        $this->email = mb_substr($email, 0, 180);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
