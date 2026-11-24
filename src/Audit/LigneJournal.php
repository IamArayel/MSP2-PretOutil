<?php

declare(strict_types=1);

namespace App\Audit;

use App\Document\AuditLog;
use App\Enum\ActionAudit;
use App\Enum\EtatObjet;
use App\Enum\RoleCompte;

/**
 * Une ligne de l'écran E9 : l'entrée du journal, l'auteur à afficher et le détail
 * rédigé selon l'action. Le texte est échappé par Twig à l'affichage.
 */
final readonly class LigneJournal
{
    public string $utilisateur;
    public string $detail;

    /** @param array<int, string> $noms « Prénom Nom » des acteurs, indexés par identifiant */
    public function __construct(public AuditLog $entree, array $noms)
    {
        $acteur = $entree->getActeur();
        $this->utilisateur = match (true) {
            null === $acteur->getId() => 'Système',
            isset($noms[$acteur->getId()]) => $noms[$acteur->getId()],
            // Compte supprimé depuis : l'email recopié dans le journal reste lisible
            default => $acteur->getEmail(),
        };
        $this->detail = self::rediger($entree);
    }

    private static function rediger(AuditLog $entree): string
    {
        $d = $entree->getDetails();
        $periode = static fn (): string => isset($d['date_debut'], $d['date_fin'])
            ? \sprintf(', du %s au %s', self::date($d['date_debut'], 'd/m'), self::date($d['date_fin'], 'd/m/Y'))
            : '';

        return match ($entree->getAction()) {
            ActionAudit::CompteCree => \sprintf('Compte créé : %s', $entree->getActeur()->getEmail()),
            ActionAudit::ConnexionEchouee => 'blocage' === ($d['motif'] ?? null)
                ? \sprintf('%s, connexion bloquée %d min', $entree->getActeur()->getEmail(), $d['minutes'] ?? 15)
                : \sprintf('%s, échec de connexion', $entree->getActeur()->getEmail()),
            ActionAudit::CompteDesactive => \sprintf('Compte désactivé : %s', $d['email'] ?? '?'),
            ActionAudit::CompteReactive => \sprintf('Compte réactivé : %s', $d['email'] ?? '?'),
            ActionAudit::RoleModifie => \sprintf('%s : %s → %s', $d['email'] ?? '?', self::role($d['ancien_role'] ?? null), self::role($d['nouveau_role'] ?? null)),
            ActionAudit::ObjetCree => \sprintf('%s (%s), état : %s', $d['nom'] ?? '?', $d['categorie'] ?? '?', self::etat($d['etat'] ?? null)),
            ActionAudit::ObjetModifie => ($d['nom'] ?? '?').(isset($d['etat']['avant'], $d['etat']['apres'])
                ? \sprintf(', état : %s → %s', self::etat($d['etat']['avant']), self::etat($d['etat']['apres']))
                : ''),
            ActionAudit::ObjetDesactive => \sprintf('%s, retiré du catalogue', $d['nom'] ?? '?'),
            ActionAudit::ObjetSupprime => \sprintf('%s (%s), supprimé définitivement', $d['nom'] ?? '?', $d['categorie'] ?? '?'),
            ActionAudit::ReservationCreee, ActionAudit::ReservationAnnulee => ($d['objet'] ?? '?').$periode(),
            ActionAudit::SortieValidee => \sprintf('%s, réservation n° %d', $d['objet'] ?? '?', $entree->getCible()->getId() ?? 0),
            ActionAudit::RetourEnregistre => \sprintf('%s, état : %s', $d['objet'] ?? '?', self::etat($d['etat_retour'] ?? null)),
            default => json_encode($d, \JSON_UNESCAPED_UNICODE) ?: '',
        };
    }

    private static function etat(mixed $valeur): string
    {
        return \is_string($valeur) ? (EtatObjet::tryFrom($valeur)?->libelle() ?? $valeur) : '?';
    }

    private static function role(mixed $valeur): string
    {
        return \is_string($valeur) ? (RoleCompte::tryFrom($valeur)?->libelle() ?? $valeur) : '?';
    }

    private static function date(mixed $valeur, string $format): string
    {
        $date = \is_string($valeur) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur) : false;

        return false === $date ? '?' : $date->format($format);
    }
}
