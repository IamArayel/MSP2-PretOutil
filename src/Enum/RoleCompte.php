<?php

declare(strict_types=1);

namespace App\Enum;

use App\Entity\Utilisateur;

/**
 * Rôle d'un compte tel que l'administrateur le choisit sur l'écran E8 (US9).
 *
 * Un seul rôle par compte ; les droits cumulés viennent de role_hierarchy
 * (un administrateur est aussi gestionnaire et adhérent). La valeur est celle
 * écrite dans le journal d'audit (ROLE_MODIFIE : ancien_role, nouveau_role).
 */
enum RoleCompte: string
{
    case Adherent = 'adherent';
    case Gestionnaire = 'gestionnaire';
    case Administrateur = 'administrateur';

    public static function de(Utilisateur $compte): self
    {
        $roles = $compte->getRoles();

        return match (true) {
            \in_array('ROLE_ADMIN', $roles, true) => self::Administrateur,
            \in_array('ROLE_MANAGER', $roles, true) => self::Gestionnaire,
            default => self::Adherent,
        };
    }

    /**
     * Rôles enregistrés dans la colonne roles ; ROLE_USER est ajouté par Utilisateur::getRoles().
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return match ($this) {
            self::Adherent => [],
            self::Gestionnaire => ['ROLE_MANAGER'],
            self::Administrateur => ['ROLE_ADMIN'],
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Adherent => 'Adhérent',
            self::Gestionnaire => 'Gestionnaire',
            self::Administrateur => 'Administrateur',
        };
    }
}
