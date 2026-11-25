<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Types d'actions sensibles journalisées (RG8). Valeurs identiques à
 * l'énumération du validateur de la collection audit_log
 * (docs/conception/audit_log.mongodb.js).
 */
enum ActionAudit: string
{
    case CompteCree = 'COMPTE_CREE';
    case ConnexionEchouee = 'CONNEXION_ECHOUEE';
    case CompteDesactive = 'COMPTE_DESACTIVE';
    case CompteReactive = 'COMPTE_REACTIVE';
    case RoleModifie = 'ROLE_MODIFIE';
    case ObjetCree = 'OBJET_CREE';
    case ObjetModifie = 'OBJET_MODIFIE';
    case ObjetDesactive = 'OBJET_DESACTIVE';
    case ObjetReactive = 'OBJET_REACTIVE';
    case ObjetSupprime = 'OBJET_SUPPRIME';
    case ReservationCreee = 'RESERVATION_CREEE';
    case ReservationAnnulee = 'RESERVATION_ANNULEE';
    case SortieValidee = 'SORTIE_VALIDEE';
    case RetourEnregistre = 'RETOUR_ENREGISTRE';

    /** Libellé lisible, pour la liste du filtre de E9 (le code reste affiché dans le badge). */
    public function libelle(): string
    {
        return match ($this) {
            self::CompteCree => 'Compte créé',
            self::ConnexionEchouee => 'Connexion échouée',
            self::CompteDesactive => 'Compte désactivé',
            self::CompteReactive => 'Compte réactivé',
            self::RoleModifie => 'Rôle modifié',
            self::ObjetCree => 'Objet créé',
            self::ObjetModifie => 'Objet modifié',
            self::ObjetDesactive => 'Objet désactivé',
            self::ObjetReactive => 'Objet réactivé',
            self::ObjetSupprime => 'Objet supprimé',
            self::ReservationCreee => 'Réservation créée',
            self::ReservationAnnulee => 'Réservation annulée',
            self::SortieValidee => 'Sortie validée',
            self::RetourEnregistre => 'Retour enregistré',
        };
    }
}
