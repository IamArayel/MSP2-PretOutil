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
    case RoleModifie = 'ROLE_MODIFIE';
    case ObjetCree = 'OBJET_CREE';
    case ObjetModifie = 'OBJET_MODIFIE';
    case ObjetDesactive = 'OBJET_DESACTIVE';
    case ReservationCreee = 'RESERVATION_CREEE';
    case ReservationAnnulee = 'RESERVATION_ANNULEE';
    case SortieValidee = 'SORTIE_VALIDEE';
    case RetourEnregistre = 'RETOUR_ENREGISTRE';
}
