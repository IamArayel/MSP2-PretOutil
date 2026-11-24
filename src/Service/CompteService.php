<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Enum\RoleCompte;
use App\Exception\ModificationCompteImpossibleException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion des comptes par l'administrateur (US9, écran E8). Le contrôleur vérifie
 * le rôle et le jeton CSRF ; les règles sont ici.
 *
 * Règle : un administrateur n'agit pas sur son propre compte. Comme seul un
 * administrateur peut utiliser E8, l'association garde toujours au moins un
 * administrateur actif.
 *
 * Chaque changement est journalisé après l'enregistrement (RG8). Une action sans
 * effet (même rôle, compte déjà désactivé) n'écrit rien.
 */
class CompteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /** @throws ModificationCompteImpossibleException si l'administrateur vise son propre compte */
    public function changerRole(Utilisateur $compte, RoleCompte $nouveauRole, Utilisateur $administrateur): void
    {
        $this->refuserSurSoiMeme($compte, $administrateur, 'Vous ne pouvez pas changer votre propre rôle.');

        $ancienRole = RoleCompte::de($compte);
        if ($ancienRole === $nouveauRole) {
            return;
        }

        $compte->setRoles($nouveauRole->roles());
        $this->entityManager->flush();

        $this->auditLogger->journaliser(ActionAudit::RoleModifie, $administrateur, 'utilisateur', $compte->getId(), [
            'email' => $compte->getEmail(),
            'ancien_role' => $ancienRole->value,
            'nouveau_role' => $nouveauRole->value,
        ]);
    }

    /**
     * RG9 : le compte ne peut plus se connecter, et sa session en cours est fermée
     * à sa requête suivante (Utilisateur::isEqualTo).
     *
     * @throws ModificationCompteImpossibleException si l'administrateur vise son propre compte
     */
    public function desactiver(Utilisateur $compte, Utilisateur $administrateur): void
    {
        $this->refuserSurSoiMeme($compte, $administrateur, 'Vous ne pouvez pas désactiver votre propre compte.');

        $this->changerActivation($compte, false, ActionAudit::CompteDesactive, $administrateur);
    }

    public function reactiver(Utilisateur $compte, Utilisateur $administrateur): void
    {
        $this->changerActivation($compte, true, ActionAudit::CompteReactive, $administrateur);
    }

    private function changerActivation(Utilisateur $compte, bool $actif, ActionAudit $action, Utilisateur $administrateur): void
    {
        if ($compte->isActif() === $actif) {
            return;
        }

        $compte->setActif($actif);
        $this->entityManager->flush();

        $this->auditLogger->journaliser($action, $administrateur, 'utilisateur', $compte->getId(), [
            'email' => $compte->getEmail(),
        ]);
    }

    private function refuserSurSoiMeme(Utilisateur $compte, Utilisateur $administrateur, string $message): void
    {
        if ($compte->getId() === $administrateur->getId()) {
            throw new ModificationCompteImpossibleException($message);
        }
    }
}
