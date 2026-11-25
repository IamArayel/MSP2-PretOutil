# Dictionnaire de données

Ticket : [#3 J2-A3 Dictionnaire de données et conventions de nommage](https://github.com/IamArayel/MSP2-PretOutil/issues/3) · Source de vérité : [mpd.sql](mpd.sql) et [audit_log.mongodb.js](audit_log.mongodb.js) · Conventions : [conventions-nommage.md](conventions-nommage.md)

Légende : **Obl.** = obligatoire (`NOT NULL`) · **DP** = donnée personnelle (RGPD) · RG = règle de gestion, US = user story ([cahier des charges](../cahier-des-charges.md)).

## 1. PostgreSQL

### utilisateur

Personne inscrite : adhérent, gestionnaire ou administrateur.

| Colonne | Type | Obl. | Défaut | Contraintes | Description | Règle | DP |
|---|---|---|---|---|---|---|---|
| `id` | INTEGER | Oui | identité | PK | Identifiant technique | | |
| `email` | VARCHAR(180) | Oui | | Unique, en minuscules | Identifiant de connexion | US1, US2 | Oui |
| `mot_de_passe` | VARCHAR(255) | Oui | | | Empreinte du mot de passe (hasher Symfony `auto`), jamais en clair | US1, US2 | Oui |
| `nom` | VARCHAR(100) | Oui | | | Nom de famille | US1 | Oui |
| `prenom` | VARCHAR(100) | Oui | | | Prénom | US1 | Oui |
| `telephone` | VARCHAR(20) | Non | | | Téléphone, pour joindre l'adhérent en cas de retard | US1 | Oui |
| `roles` | JSON | Oui | `[]` | | Rôles Symfony : `ROLE_USER`, `ROLE_MANAGER`, `ROLE_ADMIN` | US9 | |
| `actif` | BOOLEAN | Oui | `true` | | Faux : connexion refusée | RG9, US9 | |
| `cree_le` | TIMESTAMP(0) | Oui | maintenant | | Date de création du compte (UTC) | | |
| `derniere_activite_le` | TIMESTAMP(0) | Non | | | Dernière connexion ou réservation ; départ du délai de conservation | RGPD | Oui |

### categorie

Famille d'objets.

| Colonne | Type | Obl. | Défaut | Contraintes | Description | Règle | DP |
|---|---|---|---|---|---|---|---|
| `id` | INTEGER | Oui | identité | PK | Identifiant technique | | |
| `libelle` | VARCHAR(50) | Oui | | Unique | Nom affiché (Outillage, Camping, Audiovisuel...) | US3 | |

### objet

Article physique prêté par l'association.

| Colonne | Type | Obl. | Défaut | Contraintes | Description | Règle | DP |
|---|---|---|---|---|---|---|---|
| `id` | INTEGER | Oui | identité | PK | Identifiant technique | | |
| `categorie_id` | INTEGER | Oui | | FK → `categorie.id`, RESTRICT | Catégorie de l'objet | US3, US6 | |
| `nom` | VARCHAR(120) | Oui | | | Nom affiché | US3, US6 | |
| `description` | TEXT | Oui | | | Description courte | US3, US6 | |
| `caracteristiques` | TEXT | Non | | | Caractéristiques détaillées (écran Détail) | US4 | |
| `image` | VARCHAR(100) | Non | | nom simple en `.webp` (`^[a-z0-9-]+\.webp$`) | Photo de l'objet dans `assets/images/objets/` ; absente : vignette de remplacement | US3, US4 | |
| `etat` | VARCHAR(20) | Oui | `bon` | `bon`, `abime`, `hors_service` | État physique ; `hors_service` rend l'objet non réservable | RG5, RG7 | |
| `actif` | BOOLEAN | Oui | `true` | | Faux : retiré du catalogue (suppression logique) | RG5, US6 | |
| `cree_le` | TIMESTAMP(0) | Oui | maintenant | | Date d'ajout au catalogue (UTC) | | |

### reservation

Emprunt d'un objet par un adhérent, de la réservation au retour.

| Colonne | Type | Obl. | Défaut | Contraintes | Description | Règle | DP |
|---|---|---|---|---|---|---|---|
| `id` | INTEGER | Oui | identité | PK | Identifiant technique | | |
| `utilisateur_id` | INTEGER | Oui | | FK → `utilisateur.id`, RESTRICT | Adhérent qui réserve | US4, RG4 | Indirecte |
| `objet_id` | INTEGER | Oui | | FK → `objet.id`, RESTRICT | Objet réservé | US4, RG2 | |
| `date_debut` | DATE | Oui | | ≤ `date_fin` | Premier jour de la période, inclus | RG1, RG3, RG6 | |
| `date_fin` | DATE | Oui | | ≥ `date_debut` | Dernier jour de la période, inclus | RG1, RG3 | |
| `statut` | VARCHAR(20) | Oui | `confirmee` | `confirmee`, `en_cours`, `terminee`, `annulee` | Étape du cycle de vie ; `confirmee` et `en_cours` = réservation active | RG2, RG4, US5 | |
| `cree_le` | TIMESTAMP(0) | Oui | maintenant | | Date de la demande (UTC) | | |
| `sortie_le` | TIMESTAMP(0) | Non | | Renseigné avec `sortie_par_id` | Remise de l'objet à l'adhérent | US7 | |
| `sortie_par_id` | INTEGER | Non | | FK → `utilisateur.id`, RESTRICT | Gestionnaire qui a validé la sortie | US7 | Indirecte |
| `retour_le` | TIMESTAMP(0) | Non | | Après `sortie_le` ; renseigné avec `retour_par_id` et `etat_retour` | Restitution de l'objet | US8 | |
| `retour_par_id` | INTEGER | Non | | FK → `utilisateur.id`, RESTRICT | Gestionnaire qui a enregistré le retour | US8 | Indirecte |
| `etat_retour` | VARCHAR(20) | Non | | `bon`, `abime`, `hors_service` | État constaté au retour ; recopié dans `objet.etat` | US8, RG7 | |

**Contraintes de table** : `ex_reservation_chevauchement` (RG2), `ck_reservation_cycle_de_vie` (statut cohérent avec sortie et retour). Détail dans [mpd.md, section 3](mpd.md#3-contraintes-et-règles-de-gestion).

## 2. MongoDB : collection `audit_log`

Un document par action sensible (RG8, US10). Aucun champ en dehors de cette liste n'est accepté.

| Champ | Type BSON | Obl. | Valeurs | Description | DP |
|---|---|---|---|---|---|
| `_id` | objectId | Oui | | Identifiant généré par MongoDB | |
| `horodatage` | date | Oui | | Date et heure de l'action (UTC) ; base de l'index TTL d'un an | |
| `action` | string | Oui | 11 codes, voir ci-dessous | Type d'action | |
| `acteur.id` | int, long ou null | Oui | | Id de l'utilisateur ; `null` si anonyme | Indirecte |
| `acteur.email` | string (180) | Oui | | Email recopié au moment de l'action | Oui |
| `cible.type` | string | Oui | `utilisateur`, `objet`, `reservation` | Type de la donnée concernée | |
| `cible.id` | int, long ou null | Oui | | Id de la donnée concernée ; `null` si inconnue | |
| `details` | object | Oui | Libre selon l'action | Informations utiles à la lecture du journal | Possible |

**Codes d'action** et contenu attendu de `details` :

| Code | Cible | `details` |
|---|---|---|
| `COMPTE_CREE` | utilisateur | `{}` |
| `CONNEXION_ECHOUEE` | utilisateur | `{ tentative }` |
| `COMPTE_DESACTIVE` | utilisateur | `{ email }` |
| `COMPTE_REACTIVE` | utilisateur | `{ email }` |
| `ROLE_MODIFIE` | utilisateur | `{ email, ancien_role, nouveau_role }` |
| `OBJET_CREE` | objet | `{ nom }` |
| `OBJET_MODIFIE` | objet | `{ nom, champs_modifies }` |
| `OBJET_DESACTIVE` | objet | `{ nom }` |
| `OBJET_SUPPRIME` | objet | `{ nom, categorie }` |
| `RESERVATION_CREEE` | reservation | `{ objet, date_debut, date_fin }` |
| `RESERVATION_ANNULEE` | reservation | `{ objet, date_debut, date_fin }` |
| `SORTIE_VALIDEE` | reservation | `{ objet, adherent }` |
| `RETOUR_ENREGISTRE` | reservation | `{ objet, etat_retour }` |

`details` ne contient jamais de mot de passe ni de donnée personnelle autre que l'email.

## 3. Données personnelles (RGPD)

| Donnée | Où | Finalité | Base légale | Conservation | Accès |
|---|---|---|---|---|---|
| Nom, prénom | `utilisateur` | Identifier l'emprunteur | Exécution de l'adhésion | 3 ans après `derniere_activite_le`, puis anonymisation | L'adhérent, les gestionnaires (sorties et retours), l'administrateur |
| Email | `utilisateur` | Connexion, contact | Exécution de l'adhésion | Idem | L'adhérent, l'administrateur |
| Téléphone | `utilisateur` | Contact en cas de retard | Intérêt légitime (récupérer le matériel) | Idem | L'adhérent, l'administrateur |
| Empreinte du mot de passe | `utilisateur` | Authentification | Exécution de l'adhésion | Jusqu'à la suppression du compte | Personne (non réversible) |
| Dernière activité | `utilisateur` | Calcul de la durée de conservation | Obligation de limitation de conservation | Idem | Administrateur |
| Historique des réservations | `reservation` | Gestion des prêts, suivi de l'usure du matériel | Exécution de l'adhésion | Conservé, rattaché à un compte anonymisé | L'adhérent (les siennes), gestionnaires, administrateur |
| Email et actions dans le journal | `audit_log` | Traçabilité et sécurité | Intérêt légitime (sécurité) | 1 an (index TTL) | Administrateur |

**Anonymisation d'un compte** : `nom` et `prenom` remplacés par « Anonyme », `email` par une adresse technique unique (`anonyme-<id>@pretoutil.invalid`), `telephone` vidé, `actif` à faux. Les réservations restent pour l'historique ; les clés étrangères en `RESTRICT` empêchent une suppression qui les rendrait orphelines. Procédure décrite pour le MVP, non automatisée (voir cahier des charges, hors périmètre).

**Aucune adresse IP n'est stockée** : elle n'est pas nécessaire aux finalités ci-dessus.
