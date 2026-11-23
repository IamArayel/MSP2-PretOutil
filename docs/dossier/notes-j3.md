# Notes pour le dossier : J3

Ticket : [#24 J3-D Relecture croisée, PR et mise à jour du README](https://github.com/IamArayel/MSP2-PretOutil/issues/24)

Suite des [notes J1-J2](notes-j1-j2.md). J3 fait passer la conception au code : modèle de données (bloc A), sécurité et comptes (bloc B), exploitation (bloc C).

## 1. Preuves par compétence

| Compétence du CCP 2                                | Preuves ajoutées à J3                                                                                                                                          | Où                                                                                                                   |
| -------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Définir l'architecture logicielle                  | Pare-feu Symfony (`form_login`, `role_hierarchy`, `access_control`), contrôleurs fins et logique dans des services (`InscriptionService`), DTO validé          | [security.yaml](../../config/packages/security.yaml), [src/Service/](../../src/Service/), [src/Dto/](../../src/Dto/) |
| Concevoir et mettre en place la base relationnelle | Entités et enums Doctrine, migration générée et migration écrite à la main (`CHECK`, `EXCLUDE`), fixtures, droits minimaux, sauvegarde et restauration testées | [src/Entity/](../../src/Entity/), [migrations/](../../migrations/), [scripts/](../../scripts/)                       |
| Accès aux données SQL et NoSQL                     | Repositories Doctrine, connexion applicative `pretoutil_app` ; MongoDB initialisé avec le validateur `audit_log` et un utilisateur limité à `find` et `insert` | [config/packages/doctrine.yaml](../../config/packages/doctrine.yaml), [docker/](../../docker/)                       |
| Sécurité (transverse)                              | Hachage `auto`, blocage après 5 échecs, message générique, compte désactivé refusé, CSRF partout, aucun mot de passe dans le dépôt                             | README, section Authentification                                                                                     |

Reste à venir pour l'accès aux données : requêtes de disponibilité (J4), `ReservationService` (J5), document `AuditLog` et `AuditLogger` (J6).

## 2. Extraits de code à citer

| Extrait                                   | Fichier                                                                                                          | Ce qu'il montre                                                              |
| ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| Contrainte `ex_reservation_chevauchement` | [Version20261123095233.php](../../migrations/Version20261123095233.php)                                          | RG2 garantie par la base (`EXCLUDE USING gist`, `daterange` bornes incluses) |
| `IndexChevauchementListener`              | [src/Doctrine/](../../src/Doctrine/IndexChevauchementListener.php)                                               | Faire cohabiter une contrainte écrite à la main et les migrations générées   |
| `StatutReservation::estActive()`          | [src/Enum/StatutReservation.php](../../src/Enum/StatutReservation.php)                                           | Une seule définition de « réservation active » pour RG2 et RG4               |
| Droits de `pretoutil_app`                 | [Version20261123140000.php](../../migrations/Version20261123140000.php)                                          | `GRANT` minimaux, `DELETE` limité à `objet`                                  |
| Pare-feu                                  | [security.yaml](../../config/packages/security.yaml)                                                             | Hiérarchie des rôles, `login_throttling`, CSRF, `access_control`             |
| `UtilisateurChecker::checkPostAuth()`     | [src/Security/UtilisateurChecker.php](../../src/Security/UtilisateurChecker.php)                                 | RG9 sans révéler l'existence d'un compte                                     |
| `InscriptionDto` et `EmailDisponible`     | [src/Dto/](../../src/Dto/InscriptionDto.php), [src/Validator/](../../src/Validator/)                             | Validation déclarative, contrainte maison sur un DTO                         |
| `InscriptionService::inscrire()`          | [src/Service/InscriptionService.php](../../src/Service/InscriptionService.php)                                   | Hachage, normalisation, doublon intercepté en base, appel au journal         |
| Scripts d'initialisation                  | [docker/postgres/initdb/](../../docker/postgres/initdb/), [docker/mongodb/initdb/](../../docker/mongodb/initdb/) | Comptes applicatifs créés sans mot de passe dans le dépôt                    |

Règles de nommage : [conventions-nommage.md](../conception/conventions-nommage.md) (entités et propriétés en français, termes imposés par le framework en anglais, `snake_case` en base par la stratégie `underscore_number_aware`).

## 3. Choix techniques à justifier

Le détail et la date de chaque décision sont dans le [journal du README](../../README.md#16-journal-des-décisions).

| Choix                                                         | Justification courte                                                                               |
| ------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| Deux migrations : générée, puis écrite à la main              | La migration générée reste régénérable ; les contraintes métier sont relisibles en un seul endroit |
| `form_login` natif plutôt qu'un authentificateur maison       | Hachage, session, CSRF et blocage fournis et éprouvés ; moins de code à sécuriser                  |
| `checkPostAuth` pour le compte désactivé                      | Sans le bon mot de passe, la réponse est la même que pour un email inconnu                         |
| DTO pour l'inscription                                        | L'entité n'est jamais dans un état invalide ; la validation est séparée de la persistance          |
| Deux connexions Doctrine (`default`, `proprietaire`)          | L'application n'a que les droits utiles ; migrations et fixtures gardent le propriétaire           |
| Mots de passe dans `.env.local`, transmis par `env_file`      | Aucun secret versionné ; Docker Compose ne lit pas `.env.local` de lui-même                        |
| `AuditLogger` appelé dès J3, vide jusqu'à J6                  | Les services n'auront pas à changer quand le journal sera branché                                  |
| Fuseau `Indian/Reunion`                                       | « Aujourd'hui » est celui de l'association (RG1, RG6)                                              |
| Sauvegarde `pg_dump` custom + `mongodump`, empreintes SHA-256 | Restauration en une transaction, intégrité vérifiée avant de remplacer les données                 |

## 4. Démarche qualité : écarts trouvés et corrigés

| Écart                                                                                           | Comment il a été trouvé                              | Correction                                                                                 |
| ----------------------------------------------------------------------------------------------- | ---------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| Doctrine proposait de supprimer l'index de la contrainte `EXCLUDE`                              | `make:migration` après la migration écrite à la main | `IndexChevauchementListener` déclare l'index ; `migrations:diff` ne détecte plus rien      |
| Noms de contraintes différents de `mpd.sql` (cas 17 à 19)                                       | Jeu d'essai rejoué sur la base migrée                | Comportement identique, noms générés par Doctrine acceptés et documentés dans `mpd.md`     |
| La recette `symfony/form` passait la connexion en jetons CSRF sans état, qui demandent Stimulus | Revue des fichiers ajoutés par la recette            | `csrf.yaml` supprimé, jetons en session                                                    |
| Docker Compose n'utilise pas `.env.local` pour ses variables                                    | Essai de `COMPOSE_ENV_FILES` dans `.env`             | `env_file` sur les conteneurs                                                              |
| Variables utilisées dans `.env` avant d'être définies                                           | Revue de l'ordre de lecture de Symfony Dotenv        | Bloc des identifiants déplacé avant les URL                                                |
| Avertissements de Compose sur les `${...}` de `.env`                                            | Démarrage des conteneurs                             | Syntaxe `${VAR:-}`, comprise par Compose et par Symfony                                    |
| `app:mongodb:check` ne reconnaissait pas le refus d'écriture                                    | Exécution avec le compte restreint                   | Exception parente `ServerException`, code 13 (`Unauthorized`)                              |
| « Aujourd'hui » calculé en UTC                                                                  | Relecture croisée du bloc A                          | Fuseau `Indian/Reunion` pour PHP et PostgreSQL                                             |
| Commentaire de `security.yaml` annonçant un réhachage automatique des mots de passe             | Relecture des notes pour le dossier                  | Commentaire corrigé : ce réhachage demanderait `PasswordUpgraderInterface`, non implémenté |
| Page en erreur 500 après recréation du conteneur                                                | Tests après `docker compose down -v`                 | `var/` n'est pas conservé : `tailwind:build` ajouté aux étapes de relance                  |

Tests de bout en bout (scripts `curl` contre `https://localhost`) : accès des 3 rôles et du visiteur, déconnexion avec et sans jeton, compte désactivé, email inconnu, blocage au 6e essai, inscription valide et 4 cas d'erreur. Tous conformes, rejoués après J3-C1 avec les comptes restreints.

## 5. Relance depuis zéro (2026-11-23)

Sur la machine de Chloé, après fusion des blocs A, B et C :

1. `docker compose down -v`, puis `docker compose build --pull` et `docker compose up -d --wait` : 3 conteneurs sains, 3 migrations appliquées automatiquement par le propriétaire.
2. `doctrine:fixtures:load -n --em=proprietaire`, puis `tailwind:build`.
3. Vérifications : PHP et PostgreSQL en `Indian/Reunion`, application connectée en `pretoutil_app`, `app:mongodb:check` conforme.
4. Connexion avec chaque compte de test :

| Compte                          | Résultat                                    |
| ------------------------------- | ------------------------------------------- |
| `adherent@pretoutil.test`       | Connecté (Camille Martin)                   |
| `adherent.quota@pretoutil.test` | Connecté (Noah Durand)                      |
| `gestionnaire@pretoutil.test`   | Connecté (Léa Bernard)                      |
| `admin@pretoutil.test`          | Connecté (Sacha Petit)                      |
| `desactive@pretoutil.test`      | Refusé : « Ce compte est désactivé… » (RG9) |

Jeu d'essai chargé : 11 réservations dont 7 actives, dates calculées autour du jour du chargement.

## 6. Relecture croisée

Le jury interroge chaque candidat seul : chacun doit pouvoir expliquer le travail de l'autre.

**Bloc A (entités, migrations, fixtures), relu par Chloé**

- Constructeurs qui imposent les champs obligatoires (`Reservation` exige adhérent, objet et dates).
- `enregistrerSortie()` et `enregistrerRetour()` remplissent leurs champs ensemble, comme l'exigent `ck_reservation_sortie` et `ck_reservation_retour`.
- Enums aux valeurs identiques à la base, protégées par les `CHECK`.
- Fixtures sans `id` forcé, dates relatives, chaque cas d'écran couvert (quota, sortie du jour, retour en retard, objet abîmé ou hors service).
- Point soulevé et corrigé : fuseau horaire (section 4).

**Bloc B (authentification, inscription), relu par Anthony**

- À compléter par Anthony.

## 7. Questions probables à l'entretien

| Question                                                                    | Éléments de réponse                                                                                                                                                                                               | Source                                                                     |
| --------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| Comment sont stockés les mots de passe ?                                    | Empreinte produite par le hasher `auto` (bcrypt actuellement, `$2y$13$`), jamais en clair ; le réhachage à la connexion n'est pas activé (il demanderait `PasswordUpgraderInterface` sur `UtilisateurRepository`) | [security.yaml](../../config/packages/security.yaml)                       |
| Que se passe-t-il après 5 mots de passe faux ?                              | `login_throttling` bloque le couple email + IP 15 minutes, même avec le bon mot de passe ensuite                                                                                                                  | README, section Authentification                                           |
| Pourquoi le même message pour un email inconnu et un mauvais mot de passe ? | Ne pas permettre de deviner quels emails ont un compte                                                                                                                                                            | [ConnexionController.php](../../src/Controller/ConnexionController.php)    |
| Deux personnes s'inscrivent au même instant avec le même email ?            | Les deux passent la validation, l'index unique refuse le second `INSERT`, le service lève `EmailDejaUtiliseException`, l'erreur s'affiche sur le champ                                                            | [InscriptionService.php](../../src/Service/InscriptionService.php)         |
| Si l'application est compromise, que peut faire l'attaquant en base ?       | Ni `DELETE` sur les réservations et les comptes, ni `DROP`, ni modification ou suppression du journal MongoDB                                                                                                     | README, section Comptes des bases de données                               |
| Comment sont lancées les migrations si l'application n'a pas les droits ?   | Connexion Doctrine `proprietaire`, utilisée seulement par les migrations et les fixtures                                                                                                                          | [doctrine_migrations.yaml](../../config/packages/doctrine_migrations.yaml) |
| Comment restaurer la base ?                                                 | `scripts/restauration.sh`, empreintes vérifiées, une seule transaction PostgreSQL ; testé sur des volumes neufs                                                                                                   | README, section Sauvegarde et restauration                                 |

## 8. Captures

À enregistrer dans [captures/](captures/) :

| Fichier                      | Contenu                                                                          | Partie du dossier       |
| ---------------------------- | -------------------------------------------------------------------------------- | ----------------------- |
| `j3-migration-exclude.png`   | Migration `Version20261123095233.php`, contrainte `ex_reservation_chevauchement` | Base de données         |
| `j3-connexion-e1.png`        | Écran de connexion E1                                                            | Interface et sécurité   |
| `j3-connexion-blocage.png`   | Message « Trop de tentatives de connexion… » après 5 échecs                      | Sécurité                |
| `j3-inscription-erreurs.png` | Écran E2 avec les erreurs sur les champs                                         | Interface et validation |
| `j3-comptes-restreints.png`  | Terminal : `SELECT current_user` puis `DELETE FROM reservation` refusé           | Sécurité de la base     |
