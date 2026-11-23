# Conventions de nommage

Ticket : [#3 J2-A3 Dictionnaire de données et conventions de nommage](https://github.com/IamArayel/MSP2-PretOutil/issues/3)

Ces règles s'appliquent à toute la base de données et au code qui y accède. Elles répondent aux critères « Les règles de nommage ont été respectées » (base de données) et « Les règles de nommage sont conformes aux normes de qualité » (composants métier).

## 1. Langue

| Élément | Langue | Exemple |
|---|---|---|
| Vocabulaire métier (tables, colonnes, entités, propriétés, valeurs) | Français, sans accent | `reservation`, `date_debut`, `etat_retour`, `abime` |
| Termes techniques imposés par le framework | Anglais | `roles`, `getRoles()`, `Repository`, `Controller` |
| Commentaires et documentation | Français | |

Les accents sont exclus des identifiants : ils compliquent les requêtes, l'autocomplétion et certains outils.

## 2. PostgreSQL

| Élément | Règle | Exemple |
|---|---|---|
| Table | `snake_case`, singulier | `utilisateur`, `reservation` |
| Colonne | `snake_case` | `date_debut`, `mot_de_passe` |
| Clé primaire | `id` | `reservation.id` |
| Clé étrangère | `<table>_id` ; `<rôle>_par_id` quand plusieurs clés visent la même table | `objet_id`, `sortie_par_id` |
| Booléen | Adjectif ou participe, sans préfixe `is_` | `actif` |
| Date ou horodatage d'une action | `<action>_le` | `cree_le`, `sortie_le`, `retour_le` |
| Période | `date_debut`, `date_fin` | |
| Valeur contrôlée | `snake_case` minuscule, sans accent | `confirmee`, `en_cours`, `hors_service` |

### Nommage des contraintes et index

Format : `<préfixe>_<table>_<objet>`.

| Préfixe | Type | Exemple |
|---|---|---|
| `pk_` | Clé primaire | `pk_reservation` |
| `fk_` | Clé étrangère | `fk_reservation_objet` |
| `uq_` | Unicité | `uq_utilisateur_email` |
| `ck_` | Contrainte `CHECK` | `ck_reservation_dates` |
| `ex_` | Contrainte d'exclusion | `ex_reservation_chevauchement` |
| `idx_` | Index | `idx_reservation_utilisateur_statut` |

Ces noms s'appliquent au script de référence [mpd.sql](mpd.sql) et aux contraintes écrites à la main dans les migrations. Les clés et index générés par Doctrine gardent leurs noms automatiques (`IDX_...`, `FK_...`) : on ne les renomme pas.

## 3. MongoDB

| Élément | Règle | Exemple |
|---|---|---|
| Collection | `snake_case`, singulier | `audit_log` |
| Champ | `snake_case`, français | `horodatage`, `etat_retour` |
| Code d'action | `MAJUSCULES_SNAKE_CASE`, `<OBJET>_<PARTICIPE>` | `RESERVATION_CREEE`, `RETOUR_ENREGISTRE` |
| Index | `idx_<champs>` ; `ttl_<champ>_<durée>` pour un index TTL | `idx_action_horodatage`, `ttl_horodatage_1an` |

## 4. Symfony et Doctrine

| Élément | Règle | Exemple |
|---|---|---|
| Entité | `PascalCase`, singulier | `Utilisateur`, `Reservation` |
| Propriété | `camelCase` | `dateDebut`, `sortiePar` |
| Relation | Nom de l'objet lié, sans `Id` | `$objet`, `$sortiePar` |
| Enum PHP | `PascalCase` ; cas en `PascalCase` ; valeur = valeur stockée en base | `StatutReservation::EnCours` = `'en_cours'` |
| Document ODM | `PascalCase` | `AuditLog` |
| Repository | `<Entité>Repository` | `ReservationRepository` |
| Service métier | `<Domaine>Service` ou nom d'action | `ReservationService`, `AuditLogger` |
| Contrôleur | `<Domaine>Controller` | `ReservationController` |
| Voter | `<Entité>Voter` | `ReservationVoter` |

Le passage de `camelCase` (PHP) à `snake_case` (base) est assuré par la stratégie de nommage Doctrine `underscore_number_aware`, configurée par défaut par Symfony : `dateDebut` devient `date_debut` sans annotation.
