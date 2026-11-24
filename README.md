# MSP2-PretOutil

Prêt'Outil : prêt de matériel en association.

Projet réalisé pour la mise en situation professionnelle du CCP 2 « Concevoir et développer une application sécurisée organisée en couches » du titre professionnel **Concepteur Développeur d'Applications** (CDA, niveau 6, référentiel d'évaluation TP-01281 V04 du 24/05/2023).

**Équipe** : projet réalisé en binôme par Anthony Dégeilh et Chloé Baïsse.

**Suivi des tâches** : [GitHub Project Prêt'Outil](https://github.com/users/IamArayel/projects/14/views/1) (tickets liés aux PR) et [tableau Trello Prêt'Outil](https://trello.com/b/hk6mi5ST/pretoutil) (créé par Chloé Baïsse).

---

## Sommaire

1. [Compétences visées](#1-compétences-visées)
2. [Contexte](#2-contexte)
3. [Problèmes constatés](#3-problèmes-constatés)
4. [Objectifs SMART](#4-objectifs-smart)
5. [Acteurs](#5-acteurs)
6. [User stories du MVP](#6-user-stories-du-mvp)
7. [Règles de gestion](#7-règles-de-gestion)
8. [Exigences non fonctionnelles](#8-exigences-non-fonctionnelles)
9. [Hors périmètre](#9-hors-périmètre)
10. [Stack technique](#10-stack-technique)
11. [Architecture](#11-architecture)
12. [Livrables](#12-livrables)
13. [Planning](#13-planning)
14. [Points de vigilance](#14-points-de-vigilance)
15. [Installation](#15-installation)
16. [Journal des décisions](#16-journal-des-décisions)

---

## 1. Compétences visées

Compétences du CCP 2 obligatoirement mises en œuvre par le projet :

| Compétence | Mise en œuvre dans Prêt'Outil |
|---|---|
| Analyser les besoins et maquetter une application | Expression des besoins, user stories, maquettes Figma, schéma d'enchaînement des écrans |
| Définir l'architecture logicielle d'une application | Architecture en couches Symfony, stratégie de sécurité par couche, besoins d'éco-conception |
| Concevoir et mettre en place une base de données relationnelle | MCD / MPD Merise, PostgreSQL, contraintes d'intégrité, jeu d'essai restaurable |
| Développer des composants d'accès aux données SQL et NoSQL | Repositories Doctrine ORM (PostgreSQL) et journal d'audit Doctrine ODM (MongoDB) |

Contraintes de format de l'épreuve :

- Dossier de projet : 30 à 40 pages hors page de garde, sommaire et annexes ; annexes : 20 pages maximum.
- Présentation orale : 30 min, suivie de 30 min d'entretien technique.

## 2. Contexte

L'association prête du matériel à ses adhérents : outillage, matériel de camping, matériel audiovisuel. Le parc compte environ 150 objets pour 200 adhérents. Le suivi se fait aujourd'hui sur un tableur partagé.

## 3. Problèmes constatés

- Réservations en double sur un même objet, faute de contrôle des dates.
- Pas de vue claire de ce qui est disponible, sorti ou en panne.
- Aucune traçabilité des modifications : on ne sait pas qui a changé quoi.
- Données personnelles des adhérents accessibles à tous les bénévoles.

## 4. Objectifs SMART

### Objectifs produit

| # | Objectif | Indicateur de mesure |
|---|---|---|
| O1 | Supprimer les réservations en double | 0 chevauchement possible, garanti par une contrainte en BDD et vérifié par les tests |
| O3 | Assurer la traçabilité | 100 % des actions sensibles (réservation, sortie, retour, changement de rôle) sont journalisées |
| O4 | Protéger les données personnelles | Seuls l'admin et l'adhérent concerné voient les coordonnées de cet adhérent |
| O5 | Garantir la qualité du code | 100 % des règles RG1 à RG6 couvertes par au moins un test unitaire ; contrôle d'accès testé sur chaque route protégée |

> O2 (« réserver en moins de 2 minutes ») a été retiré faute de temps pour un test utilisateur (voir [Journal des décisions](#16-journal-des-décisions)).

### Objectif projet

Livrer le MVP fonctionnel, le dossier de projet et le diaporama en **10 jours ouvrés** (plus 2 jours de marge).

## 5. Acteurs

| Rôle | Description |
|---|---|
| Visiteur | Personne non connectée : consulte le catalogue et crée un compte |
| Adhérent (`ROLE_USER`) | Réserve et suit ses emprunts |
| Gestionnaire (`ROLE_MANAGER`) | Bénévole qui gère le catalogue, les sorties et les retours |
| Administrateur (`ROLE_ADMIN`) | Gère les comptes et les rôles, consulte le journal d'audit |

## 6. User stories du MVP

| ID | En tant que... | je veux... | afin de... | Critères d'acceptation |
|---|---|---|---|---|
| US1 | Visiteur | créer un compte | pouvoir réserver | Email unique, mot de passe d'au moins 12 caractères, compte actif dès la création |
| US2 | Utilisateur | me connecter et me déconnecter | accéder à mon espace | Message d'erreur générique en cas d'échec, blocage temporaire après 5 échecs |
| US3 | Visiteur ou adhérent | consulter et filtrer le catalogue | trouver un objet | Filtres par catégorie et par disponibilité sur une période, pagination de 20 objets |
| US4 | Adhérent | réserver un objet sur une période | l'emprunter | Respect des règles RG1 à RG5, confirmation affichée |
| US5 | Adhérent | voir et annuler mes réservations | gérer mes emprunts | Annulation possible jusqu'à la veille du début (RG6) |
| US6 | Gestionnaire | ajouter, modifier ou retirer un objet | tenir le catalogue à jour | Un objet déjà réservé n'est jamais supprimé physiquement : il est désactivé |
| US7 | Gestionnaire | valider la sortie d'un objet | savoir ce qui est dehors | La réservation passe au statut « En cours » |
| US8 | Gestionnaire | enregistrer un retour et l'état de l'objet | suivre l'usure du matériel | État bon, abîmé ou hors service (RG7) |
| US9 | Administrateur | changer le rôle d'un compte ou le désactiver | contrôler les accès | Action journalisée |
| US10 | Administrateur | consulter le journal d'audit | tracer les actions | Liste paginée, filtre par type d'action |

## 7. Règles de gestion

| ID | Règle |
|---|---|
| RG1 | La date de fin est postérieure ou égale à la date de début, et la date de début n'est pas dans le passé |
| RG2 | Un objet ne peut pas avoir deux réservations actives dont les périodes se chevauchent |
| RG3 | La durée d'une réservation est de 14 jours maximum |
| RG4 | Un adhérent a 3 réservations actives au maximum |
| RG5 | Un objet hors service ou désactivé n'est pas réservable |
| RG6 | L'annulation est possible jusqu'à la veille de la date de début |
| RG7 | Un retour avec l'état « hors service » rend l'objet indisponible |
| RG8 | Toute action sensible est enregistrée dans le journal d'audit (MongoDB) |
| RG9 | Un compte désactivé ne peut plus se connecter |

Cycle de vie d'une réservation :

```text
Confirmée ──> En cours (sortie validée) ──> Terminée (retour enregistré)
    │
    └──> Annulée
```

## 8. Exigences non fonctionnelles

- **Sécurité**
  - Mots de passe hachés (password hasher Symfony `auto` : bcrypt ou Argon2id).
  - Limitation des tentatives de connexion (`login_throttling`).
  - Contrôle des rôles côté serveur (`access_control` et Voters).
  - Requêtes préparées via Doctrine, aucune concaténation SQL.
  - Validation de toutes les entrées (Symfony Validator).
  - Protection CSRF (formulaires Symfony) et XSS (échappement Twig).
  - Utilisateur PostgreSQL applicatif aux droits minimaux.
- **RGPD**
  - Données personnelles limitées au nom, à l'email et au téléphone.
  - Durée de conservation : 3 ans après la dernière activité.
  - Droits d'export et de suppression décrits dans le dossier (non automatisés dans le MVP).
- **Éco-conception** : pagination, pas de polling, requêtes indexées, assets minifiés.
- **Accessibilité** : contrastes suffisants, formulaires labellisés, navigation au clavier.
- **Exploitabilité** : environnement Docker, fixtures du jeu d'essai, procédure de sauvegarde et de restauration de la BDD.

## 9. Hors périmètre

Renvoyé à une version ultérieure :

- Paiement, caution, adhésion en ligne
- Notifications par email ou SMS
- Photos des objets
- Application mobile native
- Gestion de plusieurs associations
- Plusieurs exemplaires d'un même objet (dans le MVP : 1 objet = 1 article physique)
- Validation des comptes par l'administrateur
- Export et suppression RGPD automatisés
- Mesures de performance et d'accessibilité chiffrées

## 10. Stack technique

| Besoin | Choix | Justification |
|---|---|---|
| Framework | Symfony 7 + Twig | Rendu côté serveur : pas d'API ni de SPA à construire |
| Style | Tailwind CSS via `symfonycasts/tailwind-bundle` + AssetMapper | Pas de Node ni de webpack |
| BDD relationnelle | PostgreSQL + Doctrine ORM + Doctrine Migrations | Contrainte `EXCLUDE` pour garantir RG2 |
| BDD NoSQL | MongoDB + `doctrine/mongodb-odm-bundle` | Journal d'audit : volumineux, sans jointure, en ajout seul |
| Sécurité | Symfony Security (hasher, `login_throttling`, Voters, CSRF) | Fonctionnalités natives, à configurer et justifier |
| Validation | Symfony Validator | Contrôle de toutes les entrées |
| Tests | PHPUnit (`KernelTestCase`, `WebTestCase`) | Tests unitaires et tests de sécurité |
| Environnement | Docker Compose (PHP, PostgreSQL, MongoDB) | Environnement reproductible, base de test restaurable |
| Versionnement | Git + GitHub | Historique, commits au format Conventional Commits |

## 11. Architecture

```text
Controller (présentation : Twig + Tailwind)
   └─> Service métier (ReservationService, AuditLogger, ...)
         ├─> Repository Doctrine ORM ──> PostgreSQL (données métier)
         └─> DocumentManager ODM     ──> MongoDB    (journal d'audit)
```

Rôle de chaque couche :

| Couche | Responsabilité | Sécurité |
|---|---|---|
| Contrôleur | Recevoir la requête, déléguer, rendre la vue | `access_control`, Voters, jeton CSRF |
| Formulaire / DTO | Transporter et valider les entrées | Contraintes Symfony Validator |
| Service métier | Appliquer les règles de gestion RG1 à RG9 | Vérification des droits métier, exceptions typées |
| Repository / ODM | Accéder aux données | Requêtes préparées, pas d'opérateur MongoDB issu de l'utilisateur |
| BDD | Stocker et garantir l'intégrité | Clés étrangères, `CHECK`, `EXCLUDE`, utilisateur aux droits minimaux |

RG2 est appliquée en défense en profondeur : `ReservationService` la contrôle pour afficher un message clair, et la contrainte `EXCLUDE` PostgreSQL la garantit en dernier recours.

Schéma complet (couches, conteneurs Docker, sécurité, éco-conception) : [docs/diagrammes/architecture.md](docs/diagrammes/architecture.md).

## 12. Livrables

- [ ] Code source versionné, avec ce README
- [x] Cahier des charges
- [x] Maquettes et schéma d'enchaînement des écrans
- [x] Diagramme de cas d'utilisation
- [x] Diagramme de séquence « Réserver un objet »
- [x] MCD, MPD, script de création de la BDD et fixtures du jeu d'essai
- [x] Procédure de sauvegarde et de restauration de la BDD
- [ ] Plan de tests et jeu d'essai de la fonctionnalité « Réserver un objet »
- [ ] Veille sécurité (OWASP Top 10, injections SQL et NoSQL)
- [ ] Dossier de projet (30 à 40 pages) et diaporama

### Maquettes et diagrammes

| Livrable | Source éditable | Export |
|---|---|---|
| Cahier des charges | [docs/cahier-des-charges.md](docs/cahier-des-charges.md) | |
| Diagramme de cas d'utilisation | [docs/diagrammes/cas-utilisation.puml](docs/diagrammes/cas-utilisation.puml) (PlantUML) | [SVG](docs/diagrammes/cas-utilisation.svg), [PNG](docs/diagrammes/cas-utilisation.png) |
| Maquettes (E1, E3, E4, E5, E6, E9) et composants | [Figma : Prêt'Outil - Maquettes](https://www.figma.com/design/eyw5N9ZI2sKFkVAalYHFlc) | [docs/maquettes/](docs/maquettes/) |
| Enchaînement des écrans | [FigJam](https://www.figma.com/board/4JSeLbE6WlRh5la3TAbomW), [Mermaid](docs/diagrammes/enchainement-ecrans.md) | [SVG](docs/diagrammes/enchainement-ecrans.svg) |
| MCD (Merise) | [docs/conception/mcd.mcd](docs/conception/mcd.mcd) (Mocodo), [note de conception](docs/conception/mcd.md) | [SVG](docs/conception/mcd.svg), [PNG](docs/conception/mcd.png) |
| MLD / MPD PostgreSQL et jeu d'essai | [docs/conception/mpd.sql](docs/conception/mpd.sql), [mpd_tests.sql](docs/conception/mpd_tests.sql), [note de conception](docs/conception/mpd.md) | [SVG](docs/conception/mpd.svg), [PNG](docs/conception/mpd.png) |
| Collection MongoDB `audit_log` | [docs/conception/audit_log.mongodb.js](docs/conception/audit_log.mongodb.js), [audit_log_tests.mongodb.js](docs/conception/audit_log_tests.mongodb.js) | |
| Dictionnaire de données (et RGPD) | [docs/conception/dictionnaire-donnees.md](docs/conception/dictionnaire-donnees.md) | |
| Conventions de nommage | [docs/conception/conventions-nommage.md](docs/conception/conventions-nommage.md) | |
| Requêtes du catalogue (vérification et plan d'exécution) | [docs/conception/requetes-catalogue.md](docs/conception/requetes-catalogue.md) | |
| Diagramme de séquence « Réserver un objet » | [PlantUML](docs/diagrammes/sequence-reserver.puml), [note](docs/diagrammes/sequence-reserver.md) | [SVG](docs/diagrammes/sequence-reserver.svg), [PNG](docs/diagrammes/sequence-reserver.png) |
| Schéma d'architecture | [PlantUML](docs/diagrammes/architecture.puml), [note](docs/diagrammes/architecture.md) | [SVG](docs/diagrammes/architecture.svg), [PNG](docs/diagrammes/architecture.png) |

Régénérer le diagramme de cas d'utilisation après modification :

```bash
plantuml -tsvg -tpng docs/diagrammes/cas-utilisation.puml
```

## 13. Planning

10 jours ouvrés, plus 2 jours de marge. La colonne « Dossier » se remplit chaque soir (15 min) pour éviter de tout reconstituer à la fin.

| Jour | Conception et développement | Dossier | Statut |
|---|---|---|---|
| J1 | Expression des besoins finalisée, diagramme de cas d'utilisation, maquettes Figma de 4 écrans (catalogue, réservation, mes réservations, retours), schéma d'enchaînement | Exports des maquettes et diagrammes | ✅ |
| J2 | MCD / MPD, diagramme de séquence « Réserver », schéma d'architecture. Initialisation Symfony, Docker (PostgreSQL, MongoDB, extension PHP `mongodb`), Tailwind | Justification des choix techniques ([notes J1-J2](docs/dossier/notes-j1-j2.md)) | ✅ |
| J3 | Entités Doctrine, migrations (dont `btree_gist` et `EXCLUDE`), fixtures, script de sauvegarde et de restauration. Inscription, connexion, rôles, `login_throttling` | Script SQL, règles de nommage | ✅ |
| J4 | Catalogue paginé et filtré (requête de disponibilité), CRUD des objets, Voters | Extraits des repositories ([notes J4](docs/dossier/notes-j4.md)) | ✅ |
| J5 | `ReservationService` (RG1 à RG6), exceptions métier, formulaire de réservation | Code métier, cas d'exception | 🟡 |
| J6 | Sortie, retour, annulation, mes réservations. Document `AuditLog` (ODM), `AuditLogger`, page du journal | Extraits du code NoSQL | ⬜ |
| J7 | Tests unitaires des RG, tests fonctionnels de sécurité (accès, CSRF, injection), exécution du jeu d'essai | Plan de tests, tableau entrée / attendu / obtenu, veille OWASP | ⬜ |
| J8 | Finitions Tailwind, accessibilité de base, corrections. **Gel du code le soir** | Captures d'écran | ⬜ |
| J9 | | Rédaction du dossier | ⬜ |
| J10 | | Fin du dossier et diaporama | ⬜ |
| J11-12 | Marge | Répétition orale chronométrée (30 min), entretien technique blanc | ⬜ |

Légende : ⬜ à faire · 🟡 en cours · ✅ terminé

## 14. Points de vigilance

1. **Extension PHP `mongodb`** : installée dans le Dockerfile à J2 (`install-php-extensions mongodb`) ✅
2. **Contrainte `EXCLUDE`** : non gérée nativement par Doctrine, elle est écrite en SQL dans une migration (avec l'extension `btree_gist`).
3. **Dossier au fil de l'eau** : captures, extraits de code et choix notés chaque soir.

## 15. Installation

L'environnement repose sur le modèle [`dunglas/symfony-docker`](https://github.com/dunglas/symfony-docker) : Symfony 7.4 LTS servi par FrankenPHP (PHP 8.5), PostgreSQL 16 pour les données métier et MongoDB 8.0 pour le journal d'audit, le tout dans Docker Compose.

### Prérequis

- Docker avec Docker Compose v2 (Docker Desktop ou OrbStack)
- Ports 80 et 443 libres sur la machine

PHP et Composer n'ont pas besoin d'être installés sur la machine : toutes les commandes passent par le conteneur `php`.

### Premier lancement

1. Cloner le dépôt :

   ```bash
   git clone https://github.com/IamArayel/MSP2-PretOutil.git
   cd MSP2-PretOutil
   ```

2. Créer le fichier `.env.local` (non versionné) avec le secret de l'application et les mots de passe des bases, générés au hasard :

   ```bash
   for v in APP_SECRET POSTGRES_PASSWORD POSTGRES_APP_PASSWORD MONGO_INITDB_ROOT_PASSWORD MONGODB_APP_PASSWORD; do
     echo "$v=$(openssl rand -hex 16)"
   done > .env.local
   ```

   Docker Compose refuse de démarrer sans ce fichier. Les mots de passe ne doivent contenir que des lettres et des chiffres : ils entrent dans des URL de connexion.

3. Construire les images et démarrer les conteneurs :

   ```bash
   docker compose build --pull
   docker compose up -d --wait
   ```

   Au premier démarrage, le conteneur `php` installe les dépendances Composer, attend que PostgreSQL soit prêt et applique les migrations. `--wait` attend aussi que MongoDB réponde. À la création des volumes, les scripts d'initialisation créent les comptes applicatifs (voir [Comptes des bases de données](#comptes-des-bases-de-données)).

   En développement, le service `tailwind` compile les styles en continu (voir [Styles (Tailwind CSS)](#styles-tailwind-css)). Au premier lancement, il télécharge le binaire Tailwind, ce qui retarde la fin de `--wait`.

4. Ouvrir <https://localhost> et accepter le certificat TLS auto-signé.

### Base de données et jeu d'essai

Créer le schéma avec les migrations, puis charger le jeu d'essai (environnement de développement uniquement) :

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php bin/console doctrine:fixtures:load --no-interaction --em=proprietaire
```

`--em=proprietaire` est obligatoire : le chargement vide les tables, ce que le compte de l'application n'a pas le droit de faire.

Le schéma se crée uniquement par les migrations : ne pas utiliser `doctrine:schema:create` ni `doctrine:schema:update` (voir [mpd.md, section 10](docs/conception/mpd.md#10-base-créée-par-les-migrations-doctrine-j3)). Le chargement des fixtures vide d'abord les tables.

Comptes de test, tous avec le mot de passe `PretOutil-Test-2026` (développement uniquement, jamais en production) :

| Email | Rôle | Particularité |
|---|---|---|
| `adherent@pretoutil.test` | Adhérent | Réservations dans chaque statut |
| `adherent.quota@pretoutil.test` | Adhérent | 3 réservations actives : une 4e doit être refusée (RG4) |
| `gestionnaire@pretoutil.test` | Gestionnaire | Une sortie à valider aujourd'hui |
| `admin@pretoutil.test` | Administrateur | Un retour en retard |
| `desactive@pretoutil.test` | Adhérent | Compte désactivé : connexion refusée (RG9) |

Le jeu d'essai contient aussi 6 catégories et 24 objets (dont un abîmé, un hors service et un retiré du catalogue). Les dates des réservations sont calculées à partir du jour du chargement.

### Authentification

La connexion se fait sur <https://localhost/connexion> (écran E1) avec un compte de test, ou avec un compte créé sur <https://localhost/inscription> (écran E2). La configuration est dans `config/packages/security.yaml`.

| Mécanisme | Mise en œuvre |
|---|---|
| Utilisateurs | Chargés depuis la table `utilisateur` par leur email ; mots de passe hachés (hasher `auto`) |
| Rôles | `role_hierarchy` : `ROLE_ADMIN` > `ROLE_MANAGER` > `ROLE_USER` |
| Accès | `access_control` : `/mes-reservations` (adhérent), `/gestion` (gestionnaire), `/admin` (administrateur) ; un visiteur est redirigé vers E1, un rôle insuffisant reçoit un 403 |
| Échecs | Message générique « Email ou mot de passe incorrect. », que l'email existe ou non |
| Blocage (US2) | `login_throttling` : 5 échecs par email et adresse IP, blocage de 15 minutes |
| Compte désactivé (RG9) | `App\Security\UtilisateurChecker`, appliqué après la vérification du mot de passe |
| CSRF | Jeton sur les formulaires de connexion et d'inscription et sur le lien de déconnexion |
| Inscription (US1) | DTO `InscriptionDto` validé (email valide et unique, mot de passe de 12 caractères minimum), compte créé par `InscriptionService` : email en minuscules, mot de passe haché, `ROLE_USER`, compte actif |

Les pages `/mes-reservations`, `/gestion` et `/admin` sont provisoires : elles seront remplacées par les écrans E5, E6 et E8.

Pour débloquer un compte pendant les tests, vider le compteur de tentatives :

```bash
docker compose exec php bin/console cache:pool:clear cache.rate_limiter
```

### Gestion du catalogue (écran E7)

<https://localhost/gestion/catalogue>, accessible au gestionnaire et à l'administrateur (compte de test `gestionnaire@pretoutil.test`).

| Action | Méthode | Règle |
|---|---|---|
| Lister tous les objets, actifs et retirés | GET, paginé par 20 | `OBJET_GERER` |
| Ajouter, modifier (catégorie, nom, description, caractéristiques, état) | Formulaire `ObjetType` sur `ObjetDto`, jeton CSRF | `OBJET_GERER` |
| Désactiver (retirer du catalogue, RG5) | POST, jeton CSRF propre à l'objet | `OBJET_GERER` |
| Supprimer définitivement | POST, jeton CSRF propre à l'objet, confirmation | `OBJET_SUPPRIMER` : objet jamais réservé (US6) |

Deux protections : `access_control` sur `/gestion` et `ObjetVoter` sur chaque action. Un adhérent reçoit un 403. Le bouton « Supprimer » n'apparaît que pour les objets jamais réservés, calculés en une seule requête pour la page ; si une réservation arrive entre-temps, la clé étrangère refuse la suppression et un message l'explique. Les actions passent par `ObjetService`, qui appelle `AuditLogger` (`OBJET_CREE`, `OBJET_MODIFIE`, `OBJET_DESACTIVE`).

### Comptes des bases de données

L'application ne se connecte jamais avec le propriétaire du schéma ni avec le compte root de MongoDB (droits minimaux).

| Base | Compte | Droits | Utilisé par |
|---|---|---|---|
| PostgreSQL | `pretoutil` (propriétaire) | Tous | Migrations et fixtures (connexion Doctrine `proprietaire`) |
| PostgreSQL | `pretoutil_app` | `SELECT`, `INSERT`, `UPDATE` sur les 4 tables ; `DELETE` sur `objet` seulement | Application (connexion Doctrine `default`) |
| MongoDB | `pretoutil` (root) | Tous | Administration (`mongosh`) |
| MongoDB | `pretoutil_app` | Rôle `auditAppendOnly` : `find` et `insert` sur `audit_log` seulement | Application (Doctrine ODM) |

Création, à la première création des volumes uniquement :

- PostgreSQL : `docker/postgres/initdb/01-role-applicatif.sh` crée le rôle `pretoutil_app` avec son mot de passe ; la migration `Version20261123140000` lui donne ses droits (repris de `mpd.sql`).
- MongoDB : `docs/conception/audit_log.mongodb.js` crée la collection `audit_log` (validateur, index) et le rôle `auditAppendOnly`, puis `docker/mongodb/initdb/02-utilisateur-app.js` crée l'utilisateur `pretoutil_app`.

Les mots de passe sont lus dans `.env.local`, que Docker Compose transmet aux conteneurs (`env_file`). Symfony construit `DATABASE_URL`, `DATABASE_OWNER_URL` et `MONGODB_URI` dans `.env` à partir de ces variables.

Pour appliquer ces comptes à des volumes créés avant J3-C1, recréer les bases (données de développement uniquement) :

```bash
docker compose down -v
docker compose up -d --wait
docker compose exec php bin/console doctrine:fixtures:load --no-interaction --em=proprietaire
```

Vérifier les droits :

```bash
docker compose exec php bin/console dbal:run-sql "SELECT current_user"
docker compose exec php bin/console dbal:run-sql "DELETE FROM reservation"
docker compose exec php bin/console app:mongodb:check
```

La première commande affiche `pretoutil_app`, la deuxième échoue avec `permission denied for table reservation`, la troisième lit `audit_log` et constate qu'une écriture ailleurs est refusée.

### Se connecter aux bases depuis la machine (pgAdmin, MongoDB Compass)

Les ports sont fixes et accessibles depuis cette machine uniquement (`127.0.0.1`, voir `compose.override.yaml`). Ils évitent 5432 et 27017, souvent déjà pris par un PostgreSQL ou un MongoDB installé localement : avec ces ports, l'outil risquerait de se connecter au mauvais serveur.

| | PostgreSQL (pgAdmin) | MongoDB (Compass) |
|---|---|---|
| Hôte | `localhost` | `localhost` |
| Port | `5433` | `27018` |
| Base | `pretoutil` | `pretoutil` |
| Compte d'administration | `pretoutil`, mot de passe `POSTGRES_PASSWORD` | `pretoutil`, mot de passe `MONGO_INITDB_ROOT_PASSWORD`, base d'authentification `admin` |
| Compte de l'application | `pretoutil_app`, mot de passe `POSTGRES_APP_PASSWORD` | `pretoutil_app`, mot de passe `MONGODB_APP_PASSWORD`, base d'authentification `pretoutil` |

Les mots de passe sont dans `.env.local`. Le compte de l'application permet de vérifier ses droits réels (un `DELETE` sur `reservation` est refusé).

Port déjà pris sur la machine : choisir un autre port au démarrage, par exemple `POSTGRES_PORT=5434 MONGODB_PORT=27019 docker compose up -d`.

### Sauvegarde et restauration

Deux scripts, lancés depuis la machine hôte avec les conteneurs démarrés. Ils lisent les identifiants dans les conteneurs (variables de `.env.local`) : aucun mot de passe n'est à saisir ni stocké dans les scripts.

```bash
scripts/sauvegarde.sh
scripts/restauration.sh sauvegardes/<AAAAMMJJ-HHMMSS>
```

| Fichier de la sauvegarde | Contenu | Outil |
|---|---|---|
| `postgresql.dump` | Schéma, données, contraintes et droits de `pretoutil_app` | `pg_dump --format=custom` |
| `mongodb.archive.gz` | Collection `audit_log` avec son validateur et ses index (dont le TTL) | `mongodump --archive --gzip` |
| `SHA256SUMS` | Empreintes des deux fichiers | `shasum -a 256` |

- **Sauvegarde** : dans `sauvegardes/<date UTC>/` (non versionné), fichiers lisibles par le seul utilisateur courant. Si une étape échoue, le dossier incomplet est supprimé.
- **Restauration** : vérifie les empreintes, demande confirmation (`--oui` pour l'éviter dans un script), puis remplace les données. PostgreSQL est restauré dans une seule transaction : en cas d'erreur, la base reste dans son état précédent.
- **Rôles et utilisateurs applicatifs** : non sauvegardés, ils sont recréés par les scripts de `docker/` à la création des volumes. Les sauvegardes ne contiennent donc aucune empreinte de mot de passe des comptes de base de données.
- **Restaurer sur des volumes neufs** : `docker compose down -v`, `docker compose up -d --wait`, puis `scripts/restauration.sh`.

Règles d'exploitation :

| Règle | Valeur |
|---|---|
| Fréquence | Une fois par jour, et avant chaque migration ou mise en production |
| Conservation | 30 jours glissants ; au-delà, les sauvegardes sont supprimées |
| Emplacement | Copie hors du serveur de l'application (autre machine ou stockage externe) |
| Protection (RGPD) | Les sauvegardes contiennent des données personnelles : stockage chiffré, accès réservé aux administrateurs |
| Contrôle | Une restauration d'essai sur des volumes neufs au moins une fois par mois |

Test du 2026-11-23, sur une pile Docker isolée : sauvegarde du jeu d'essai et de 3 documents `audit_log`, suppression des volumes, recréation, restauration. L'état après restauration est identique à l'état avant : nombre de lignes, empreinte MD5 des tables `utilisateur` et `reservation`, contraintes, index, droits de `pretoutil_app`, documents, validateur et index TTL d'`audit_log`. L'application lit les données restaurées avec le compte `pretoutil_app`. Cas d'erreur vérifiés : sauvegarde altérée (empreinte refusée), fichier manquant, confirmation refusée, restauration sur une base déjà remplie, base arrêtée pendant la sauvegarde.

### Après un `git pull`

Le dossier du projet, `vendor/` compris, est monté dans le conteneur `php`. Celui-ci n'installe les dépendances que si `vendor/` est vide : il ne le met pas à jour quand `composer.lock` change. Sans mise à jour, le conteneur s'arrête au démarrage avec une erreur `Class "..." not found`.

Si le pull modifie `composer.lock`, installer les nouvelles dépendances sans démarrer l'application, puis relancer :

```bash
docker compose run --rm --no-deps php composer install
docker compose up -d --wait
```

Si le pull ajoute une migration, l'appliquer ensuite avec `doctrine:migrations:migrate` (voir les commandes utiles).

### Vérifier l'installation

```bash
docker compose ps
docker compose exec php bin/console dbal:run-sql "SELECT 1"
docker compose exec php bin/console doctrine:migrations:status
docker compose exec php php -m | grep mongodb
docker compose exec php bin/console app:mongodb:check
```

`app:mongodb:check` lit la collection `audit_log` avec le compte applicatif, puis vérifie qu'une écriture dans une autre collection est refusée.

### Tests

PHPUnit est installé en dépendance de développement (`symfony/test-pack`). Les tests sont dans `tests/`, rangés comme le code de `src/`.

```bash
docker compose exec php bin/phpunit --testdox
```

| Tests | Ce qu'ils vérifient |
|---|---|
| `tests/Security/Voter/ObjetVoterTest.php` | `OBJET_GERER` : gestionnaire et administrateur autorisés, adhérent et visiteur refusés ; `OBJET_SUPPRIMER` : refusé pour un objet déjà réservé (US6) |
| `tests/Security/Voter/ReservationVoterTest.php` | `RESERVATION_VOIR` : propriétaire et gestionnaire autorisés, autre adhérent refusé (O4) ; `RESERVATION_ANNULER` : propriétaire seulement, réservation confirmée, jusqu'à la veille du début (RG6) |

Les tests des Voters sont des tests unitaires : ni base de données ni conteneur Symfony. La hiérarchie des rôles est reconstruite comme dans `security.yaml`, et la date du jour est fixée par une horloge de test (`MockClock`).

### Styles (Tailwind CSS)

Tailwind CSS 4 est intégré via `symfonycasts/tailwind-bundle` et AssetMapper : pas de Node ni de npm.

| Fichier | Rôle |
|---|---|
| `assets/styles/app.css` | Source : import de Tailwind et charte de la maquette dans le bloc `@theme` (couleur principale teal-700, neutres slate, couleurs des statuts) |
| `var/tailwind/app.built.css` | CSS compilé, non versionné, servi à la place de `app.css` |
| `templates/base.html.twig` | Gabarit commun, avec la barre de navigation (`templates/_partials/navbar.html.twig`) |

En développement, le service `tailwind` de `compose.override.yaml` lance `tailwind:build --watch` : le CSS est recompilé à chaque modification de `assets/` ou `templates/`. Le résultat est stocké dans le volume `tailwind_build`, partagé avec le conteneur `php` (son `var/` n'est pas lié à la machine hôte). Pour suivre la compilation :

```bash
docker compose logs -f tailwind
```

Les couleurs de la charte sont disponibles comme utilitaires : `bg-primary`, `text-primary`, `border-line`, `text-ink-muted`, `bg-statut-disponible-bg`, etc.

### Variables d'environnement

| Fichier | Versionné | Contenu |
|---|---|---|
| `.env` | Oui | Valeurs non secrètes : `APP_ENV`, noms des bases et des comptes (`POSTGRES_*`, `MONGODB_*`, lus aussi par Docker Compose), modèles de `DATABASE_URL`, `DATABASE_OWNER_URL` et `MONGODB_URI` sans mot de passe |
| `.env.local` | Non | Secrets : `APP_SECRET`, `POSTGRES_PASSWORD`, `POSTGRES_APP_PASSWORD`, `MONGO_INITDB_ROOT_PASSWORD`, `MONGODB_APP_PASSWORD` |

Aucun mot de passe n'est versionné. `compose.yaml` transmet `.env.local` aux trois conteneurs (`env_file`).

### Commandes utiles

| Action | Commande |
|---|---|
| Arrêter les conteneurs | `docker compose down` |
| Suivre les logs | `docker compose logs -f php` |
| Console Symfony | `docker compose exec php bin/console <commande>` |
| Ajouter une dépendance | `docker compose exec php composer require <paquet>` |
| Compiler les styles (minifiés) | `docker compose exec php bin/console tailwind:build --minify` |
| Installer les dépendances (conteneur `php` arrêté) | `docker compose run --rm --no-deps php composer install` |
| Débloquer les comptes après 5 échecs | `docker compose exec php bin/console cache:pool:clear cache.rate_limiter` |
| Lancer les tests | `docker compose exec php bin/phpunit --testdox` |
| Appliquer les migrations | `docker compose exec php bin/console doctrine:migrations:migrate` |
| Ouvrir un shell PostgreSQL | `docker compose exec database psql -U pretoutil pretoutil` |
| Ouvrir un shell MongoDB (root) | `docker compose exec mongodb sh -c 'mongosh -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin pretoutil'` |
| Recharger le jeu d'essai | `docker compose exec php bin/console doctrine:fixtures:load -n --em=proprietaire` |
| Sauvegarder les bases | `scripts/sauvegarde.sh` |
| Restaurer une sauvegarde | `scripts/restauration.sh sauvegardes/<AAAAMMJJ-HHMMSS>` |
| Tout supprimer, bases comprises | `docker compose down -v` |

## 16. Journal des décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-23 | Choix du sujet Prêt'Outil parmi 5 propositions (Prêt'Outil, CréneauClub, TicketDesk, MédiRappel, LudoSoirée) | Sujet le plus sûr pour couvrir toute la grille du CCP 2 ; règle métier forte (anti-chevauchement) pour le jeu d'essai ; usage NoSQL justifié par le journal d'audit |
| 2026-11-23 | Planning ramené de 8 semaines à 10 jours (+ 2 jours de marge) | Temps disponible avant l'épreuve |
| 2026-11-23 | MVP resserré : compte actif dès l'inscription, admin limité aux rôles et à la désactivation, RGPD décrit mais non automatisé | Tenir les 10 jours en gardant toutes les compétences évaluées |
| 2026-11-23 | Objectif O2 retiré, O5 reformulé (couverture des RG et du contrôle d'accès) | Pas de temps pour un test utilisateur ; objectif de couverture plus mesurable dans le délai |
| 2026-11-23 | Stack : Symfony 7, Twig, Tailwind (AssetMapper), PostgreSQL, MongoDB (Doctrine ODM), Docker | Rendu serveur plus rapide à produire ; `EXCLUDE` PostgreSQL pour RG2 ; ODM proche de l'ORM |
| 2026-11-23 | Rendu côté serveur (Twig) plutôt qu'une API + SPA | Gain de temps, l'épreuve évalue la conception et l'accès aux données, pas le front |
| 2026-11-23 | Cahier des charges séparé du README (`docs/cahier-des-charges.md`) | Le README reste la vue d'ensemble et l'historique ; le cahier des charges est le document de référence pour le dossier, avec inventaire des écrans et critères de recette |
| 2026-11-23 | Hiérarchie des rôles : Admin > Gestionnaire > Adhérent > Visiteur | Un gestionnaire peut aussi emprunter ; simplifie `access_control` avec `role_hierarchy` |
| 2026-11-23 | 6 écrans maquettés sur 9 (E1, E3, E4, E5, E6, E9) ; E2, E7, E8 non maquettés | Ils réutilisent les mêmes composants (formulaire, tableau) ; gain de temps sur J1 |
| 2026-11-23 | Maquettes construites avec des composants Figma (Bouton, Badge, Champ, Navbar, CarteObjet) et la palette Tailwind en variables | Correspondance directe avec les composants Twig et les classes Tailwind à J4-J8 |
| 2026-11-23 | Diagramme de cas d'utilisation en PlantUML, enchaînement des écrans en Mermaid | Sources textuelles versionnées dans Git, régénérables |
| 2026-11-23 | Projet réalisé en binôme (Anthony Dégeilh, Chloé Baïsse) | Organisation de la formation |
| 2026-11-23 | Environnement basé sur `dunglas/symfony-docker` (FrankenPHP), Symfony 7.4 LTS, PostgreSQL 16 | Modèle maintenu par la communauté Symfony, HTTPS local prêt à l'emploi ; version LTS maintenue jusqu'en 2028 |
| 2026-11-23 | `.gitignore` Visual Studio remplacé par un `.gitignore` Symfony ; `.env.dev` non versionné, `APP_SECRET` dans `.env.local` | L'ancien fichier ignorait `.env` et `bin/console` ; aucun secret dans le dépôt |
| 2026-11-23 | MongoDB conservé pour le journal d'audit, après avoir envisagé PostgreSQL seul | Couvre la partie NoSQL de la compétence « composants d'accès aux données SQL et NoSQL » ; `details` de forme libre selon l'action |
| 2026-11-23 | MongoDB 8.0 et `doctrine/mongodb-odm-bundle` 5.6 ; commande `app:mongodb:check` pour tester la connexion | Versions compatibles avec Symfony 7.4 et PHP 8.5 ; test reproductible sans document métier avant J6 |
| 2026-11-23 | Tailwind CSS 4 via `symfonycasts/tailwind-bundle` 1.0, charte dans le bloc `@theme` de `app.css` | Pas de Node ; noms sémantiques (`primary`, `line`, `statut-*`) pour changer la charte à un seul endroit |
| 2026-11-23 | Police Inter déclarée sans être téléchargée (repli sur la police système) | Éco-conception et RGPD : aucune requête vers un service de polices externe |
| 2026-11-23 | Suivi des tâches sur un tableau Trello partagé | Outil collaboratif adapté au travail en binôme et à une méthode agile légère (critère « outils collaboratifs choisis en fonction de la méthode de développement ») |
| 2026-11-23 | Tâches de J2 en tickets GitHub (#1 à #9) dans un GitHub Project, une branche et une PR par bloc | Tickets fermés automatiquement par les PR (`Closes #n`) : le suivi reste lié au code |
| 2026-11-23 | MCD en Merise avec Mocodo (source texte `docs/conception/mcd.mcd`) | Versionné dans Git, régénérable, et génère aussi le MLD et le SQL pour le MPD |
| 2026-11-23 | RESERVATION modélisée comme une entité, pas comme une association porteuse | Un adhérent peut réserver plusieurs fois le même objet : il faut un identifiant propre |
| 2026-11-23 | Pas d'entité PRÊT : sortie et retour portés par RESERVATION (`sortie_le`, `retour_le`, `etat_retour`) | Relation 1-1 avec la réservation ; évite une jointure et garde le cycle de vie au même endroit |
| 2026-11-23 | Rôles stockés dans `UTILISATEUR.roles`, pas d'entité RÔLE | 3 rôles fixes et hiérarchisés, convention Symfony Security |
| 2026-11-23 | Disponibilité d'un objet calculée, jamais stockée | Évite une donnée dérivée incohérente avec les réservations |
| 2026-11-23 | RG2 garantie par une contrainte `EXCLUDE USING gist` (bornes incluses) en plus du service | Défense en profondeur : même deux requêtes simultanées ne peuvent pas créer de chevauchement |
| 2026-11-23 | Cycle de vie de la réservation verrouillé par des `CHECK` (statut cohérent avec sortie et retour) | Une donnée incohérente est refusée par la base, quel que soit le code qui l'écrit |
| 2026-11-23 | RG1 « pas dans le passé », RG3 et RG4 laissées au service | Dépendent de la date du jour, d'un paramètre métier ou de plusieurs lignes : hors de portée d'un `CHECK` |
| 2026-11-23 | Valeurs contrôlées en `VARCHAR` + `CHECK` plutôt qu'en `ENUM` PostgreSQL | Mapping des enums PHP par Doctrine ; ajout de valeur par simple migration |
| 2026-11-23 | Rôle PostgreSQL `pretoutil_app` aux droits minimaux, `DELETE` seulement sur `objet` | Critère « intégrité, sécurité et confidentialité des données » |
| 2026-11-23 | Validateur `$jsonSchema` strict sur `audit_log`, rôle MongoDB en lecture et insertion seules | Journal fiable et non modifiable par l'application |
| 2026-11-23 | Index TTL d'un an sur `audit_log` | RGPD : durée de conservation des journaux appliquée automatiquement |
| 2026-11-23 | Fixtures sans `id` explicite | Écart du jeu d'essai : des `id` forcés désynchronisent les séquences d'identité |
| 2026-11-23 | Identifiants en français sans accent ; termes du framework en anglais | Vocabulaire métier lisible par tous, sans les pièges des accents dans les requêtes et les outils |
| 2026-11-23 | Contraintes nommées `pk_`, `fk_`, `uq_`, `ck_`, `ex_`, `idx_` dans les scripts ; noms Doctrine automatiques conservés | Messages d'erreur lisibles et rattachables à une règle ; pas de lutte contre les conventions de Doctrine |
| 2026-11-23 | Suppression d'un compte par anonymisation, aucune adresse IP stockée | RGPD : minimisation des données, historique des prêts conservé sans donnée personnelle |
| 2026-11-23 | Réservation : refus signalés par des exceptions métier, fragments `break` dans le diagramme de séquence | Le contrôleur traduit le refus en message sans connaître la règle ; un refus termine le scénario |
| 2026-11-23 | RG4 vérifiée sous verrou `SELECT ... FOR UPDATE` sur l'adhérent | Deux demandes simultanées du même adhérent ne peuvent pas dépasser le quota de 3 |
| 2026-11-23 | Journalisation d'une réservation après le `COMMIT`, sans bloquer si MongoDB est indisponible | On ne journalise que ce qui a eu lieu ; le journal ne doit pas empêcher le service rendu |
| 2026-11-23 | Schéma d'architecture unique : cinq couches logicielles dans le conteneur `php`, une base par conteneur | Montre sur une seule page le découpage logique, la répartition physique et la sécurité de chaque couche |
| 2026-11-23 | Seul le conteneur `php` exposé hors développement ; bases joignables par le réseau Docker uniquement | Réduit la surface d'attaque : aucune base accessible depuis l'extérieur |
| 2026-11-23 | Scripts de conception alignés sur PostgreSQL 16, jeu d'essai rejoué (21/21) | Même version que l'environnement Docker : ce qui est testé est ce qui tourne |
| 2026-11-23 | Installation vérifiée depuis un clone neuf, sous un autre nom de projet Docker | Prouve que la section Installation suffit ; les volumes de développement existants ne sont pas touchés |
| 2026-11-23 | Notes pour le dossier tenues par étape dans `docs/dossier/` | Preuves, visuels et réponses aux questions du jury prêts pour la rédaction de J9-J10 |
| 2026-11-23 | J3 en 8 tickets GitHub (#17 à #24) : modèle de données et sauvegarde pour Anthony, sécurité et comptes de base de données pour Chloé, relecture croisée ensemble | Chacun prolonge ce qu'il a conçu ou installé à J2 ; la relecture croisée prépare l'entretien individuel |
| 2026-11-23 | `symfony/security-bundle` installé avec l'entité `Utilisateur` (J3-A1), pare-feu configuré dans J3-B1 | L'entité a besoin de `UserInterface` ; Chloé peut commencer la sécurité dès que `Utilisateur` est poussée |
| 2026-11-23 | Entités Doctrine sans relations inverses (`ManyToOne` seulement) | Aucun écran n'en a besoin dans le MVP ; évite des chargements de collections inutiles |
| 2026-11-23 | Sortie et retour enregistrés par `Reservation::enregistrerSortie()` et `enregistrerRetour()`, sans setters séparés | Les champs liés sont toujours renseignés ensemble, comme l'exigent `ck_reservation_sortie` et `ck_reservation_retour` ; les règles restent dans le service |
| 2026-11-23 | Enums PHP `StatutReservation` et `EtatObjet` mappés sur des colonnes `VARCHAR(20)` | Valeurs identiques à la base ; `StatutReservation::estActive()` définit une seule fois ce qu'est une réservation active (RG2, RG4) |
| 2026-11-23 | Index Doctrine `IDX_...` sur `reservation.utilisateur_id` conservé à côté de `idx_reservation_utilisateur_statut` | Doctrine indexe chaque clé étrangère ; le supprimer créerait un écart à chaque génération de migration, pour un gain négligeable |
| 2026-11-23 | Contraintes `CHECK` et `EXCLUDE` dans une migration écrite à la main, séparée de la migration générée | La migration générée reste régénérable ; les contraintes métier sont relisibles en un seul endroit |
| 2026-11-23 | Écouteur Doctrine `IndexChevauchementListener` qui déclare l'index de la contrainte `EXCLUDE` | Sans lui, chaque génération de migration proposait de supprimer la contrainte RG2 ; schéma créé uniquement par les migrations |
| 2026-11-23 | Fixtures découpées par entité, dates des réservations relatives au jour du chargement | Une classe par entité reste lisible ; les écrans montrent toujours des réservations à venir, en cours et en retard |
| 2026-11-23 | Comptes de test sur le domaine réservé `.test`, mot de passe commun documenté dans le README | Aucun risque d'écrire à une vraie adresse ; mot de passe réservé au développement, chaque compte illustre un cas (quota, désactivé, rôles) |
| 2026-11-23 | Authentification par `form_login` et fournisseur d'entité sur `Utilisateur.email` | Mécanisme natif de Symfony : hachage, session, CSRF et `login_throttling` sans code d'authentification maison |
| 2026-11-23 | `UtilisateurChecker` en `checkPostAuth` plutôt qu'en `checkPreAuth` | Le message « compte désactivé » n'apparaît qu'avec le bon mot de passe : on ne révèle pas l'existence d'un compte à partir du seul email |
| 2026-11-23 | `login_throttling` : 5 essais par email et adresse IP sur 15 minutes | US2 ; le 6e essai est bloqué même avec le bon mot de passe |
| 2026-11-23 | Lien de déconnexion protégé par un jeton CSRF | Un site tiers ne peut pas déconnecter un utilisateur à son insu |
| 2026-11-23 | Pages provisoires `/mes-reservations`, `/gestion` et `/admin` (`EspaceController`) | Tester l'`access_control` des trois rôles dès J3, avant les écrans E5, E6 et E8 |
| 2026-11-23 | Inscription : formulaire Symfony sur un DTO (`InscriptionDto`), création dans `InscriptionService` | L'entité n'est jamais dans un état invalide ; aucune logique métier dans le contrôleur |
| 2026-11-23 | Contrainte `EmailDisponible` sur le DTO, et contrainte d'unicité en base interceptée par le service | `UniqueEntity` ne s'applique qu'aux entités ; la base reste la garantie finale si deux inscriptions arrivent en même temps |
| 2026-11-23 | Rôles d'un nouvel adhérent stockés vides (`[]`) | `getRoles()` ajoute toujours `ROLE_USER` ; même convention que les fixtures |
| 2026-11-23 | `AuditLogger::journaliser()` appelé dès J3, sans écriture jusqu'à J6 | Les services métier n'auront pas à être modifiés quand le journal MongoDB sera branché |
| 2026-11-23 | Jetons CSRF stockés en session, fichier `csrf.yaml` de la recette `symfony/form` supprimé | Les jetons sans état de la recette demandent un contrôleur JavaScript (Stimulus) absent du projet ; la session est plus simple à expliquer |
| 2026-11-23 | Thème de formulaire commun `templates/form/theme.html.twig` | Champs, aides et erreurs aux couleurs de la maquette pour tous les formulaires (E2, puis E4 et E7) |
| 2026-11-23 | Deux connexions Doctrine : `default` (`pretoutil_app`) pour l'application, `proprietaire` pour les migrations et les fixtures | Droits minimaux pour le code applicatif ; les migrations restent automatiques au démarrage du conteneur |
| 2026-11-23 | Rôle `pretoutil_app` créé par un script d'initialisation Docker, droits posés par une migration | Le mot de passe reste hors du dépôt ; les `GRANT` sont versionnés avec le schéma qu'ils protègent |
| 2026-11-23 | `audit_log.mongodb.js` monté tel quel comme script d'initialisation MongoDB | Une seule source pour le validateur, les index et le rôle `auditAppendOnly` |
| 2026-11-23 | Mots de passe dans `.env.local`, transmis aux conteneurs par `env_file` | Docker Compose ne lit pas `.env.local` pour ses variables ; aucun mot de passe dans `.env` ni `compose.yaml` |
| 2026-11-23 | `app:mongodb:check` vérifie un refus d'écriture au lieu d'écrire un document de test | Le compte applicatif ne peut écrire que dans `audit_log`, en ajout seul : la commande démontre les droits minimaux |
| 2026-11-23 | Sauvegarde par `pg_dump` au format custom et `mongodump` en archive compressée, avec empreintes SHA-256 | Formats natifs restaurables par les outils standard ; une sauvegarde altérée est détectée avant de remplacer les données |
| 2026-11-23 | Rôles et utilisateurs applicatifs exclus des sauvegardes | Ils sont recréés par les scripts d'initialisation de `docker/` ; aucune empreinte de mot de passe dans les fichiers de sauvegarde |
| 2026-11-23 | Sauvegardes hors dépôt, fichiers en lecture pour le seul utilisateur, conservation 30 jours | Elles contiennent des données personnelles (RGPD) |
| 2026-11-23 | J4 en 5 tickets GitHub (#33 à #37) : catalogue (requêtes et écrans E3, E4) pour Anthony, Voters et gestion du catalogue (E7) pour Chloé, relecture croisée ensemble | Chacun prolonge son domaine de J3 (données, sécurité) ; la relecture croisée prépare l'entretien individuel |
| 2026-11-23 | Voters faits avant l'écran E7 et testés unitairement | E7 s'appuie sur `ObjetVoter` pour autoriser la suppression ; critère O5 « contrôle d'accès testé » |
| 2026-11-23 | Ports fixes 5433 (PostgreSQL) et 27018 (MongoDB), limités à `127.0.0.1` | Connexion stable depuis pgAdmin et Compass ; 5432 et 27017 sont souvent pris par des serveurs installés localement ; bases non exposées au réseau local |
| 2026-11-24 | Filtre de disponibilité du catalogue en `NOT EXISTS` avec bornes incluses, objets hors service exclus dès qu'une période est demandée | Même règle que la contrainte `EXCLUDE` (RG2) et RG5 : le catalogue n'annonce disponible que ce qui est réservable |
| 2026-11-24 | Index partiel `idx_reservation_dates_actives` sur les dates des réservations actives | `EXPLAIN` sur 200 000 réservations : lecture complète de la table sans lui (25 ms), 7 ms avec ; éco-conception « requêtes indexées » |
| 2026-11-24 | Repositories qui renvoient un objet `Page` plutôt que le `Paginator` de Doctrine | Les contrôleurs et les vues ne dépendent pas de l'ORM (séparation des couches) ; `Page` sert aussi aux écrans E7 et E9 |
| 2026-11-24 | Catalogue sur `/catalogue` (E3) et détail sur `/catalogue/{id}` (E4), page d'accueil conservée | L'accueil présente l'association et mène au catalogue ; les URL du catalogue restent stables |
| 2026-11-24 | `CatalogueService` entre les contrôleurs et les repositories | Les contrôleurs ne font que lire la requête et rendre la vue ; le calcul du statut du jour est à un seul endroit |
| 2026-11-24 | Badge « statut du jour » calculé (hors service, sorti, réservé, disponible), jamais stocké, en une requête par page | Donnée dérivée des réservations ; un retour en retard s'affiche « Sorti » ; pas de requête par objet |
| 2026-11-24 | Filtres du catalogue en GET sans jeton CSRF ; filtres invalides : erreurs affichées, code 422, catalogue complet | Une recherche ne modifie rien et son URL se partage ; un filtre incohérent n'est jamais ignoré silencieusement |
| 2026-11-24 | Contrôle d'accès métier par Voters (`ObjetVoter`, `ReservationVoter`), attributs en constantes | Les règles qui dépendent de l'objet (propriétaire, objet déjà réservé, date) ne s'expriment pas par chemin dans `access_control` ; un seul endroit par règle, testable |
| 2026-11-24 | Rôle requis vérifié par `AccessDecisionManager` dans les Voters, pas par `in_array` sur les rôles | Respecte `role_hierarchy` : un administrateur a les droits d'un gestionnaire sans les répéter |
| 2026-11-24 | `RESERVATION_ANNULER` limité aux réservations confirmées | Cycle de vie : une réservation en cours, terminée ou annulée ne peut plus être annulée |
| 2026-11-24 | Date du jour fournie par l'horloge Symfony (`ClockInterface`), comparée en jours calendaires | RG6 testable à la journée près ; comparer `AAAA-MM-JJ` évite un décalage d'un jour lié aux fuseaux |
| 2026-11-24 | PHPUnit via `symfony/test-pack` dès J4 | Critère O5 (contrôle d'accès testé) ; même outillage pour les tests fonctionnels de J7 |
| 2026-11-24 | Écran E7 sous `/gestion/catalogue`, protégé par `access_control` et par `ObjetVoter` sur chaque action | Défense en profondeur : une erreur de configuration d'un côté ne suffit pas à ouvrir la gestion du catalogue |
| 2026-11-24 | Formulaire d'objet sur un DTO (`ObjetDto`), comme l'inscription | L'entité n'est jamais dans un état invalide ; contraintes du dictionnaire de données au même endroit |
| 2026-11-24 | Longueur maximale de 2000 caractères pour la description et 5000 pour les caractéristiques | Colonnes `TEXT` sans limite en base : une borne côté application évite les saisies abusives |
| 2026-11-24 | Désactivation et suppression en POST avec un jeton CSRF par action et par objet | Un lien ou un formulaire tiers ne peut pas retirer un objet ; un jeton ne sert qu'à l'action prévue |
| 2026-11-24 | Objets déjà réservés d'une page calculés en une requête (`objetsDejaReserves`) | Évite une requête par ligne pour afficher le bouton « Supprimer » (éco-conception) ; la suppression reste contrôlée par le Voter |
| 2026-11-24 | Suppression non journalisée, pas de réactivation d'un objet retiré | Hors périmètre du MVP : l'énumération des actions de `audit_log` ne prévoit ni `OBJET_SUPPRIME` ni réactivation ; améliorations possibles |
| 2026-11-24 | Parcours complet de fin de journée fait par requêtes HTTP, rôle par rôle, sur les fixtures | Reproductible et rapide ; le rendu visuel reste vérifié à la main, car le certificat local bloque les navigateurs automatisés |
| 2026-11-24 | Remarques de relecture du bloc B (journalisation de la suppression, objet désactivé avec réservations à venir, réactivation) reportées à J5 et J6 | Elles touchent `AuditLogger` et `ReservationService`, réalisés ces jours-là ; voir les notes J4, section 6 |
| 2026-11-24 | J5 en 4 tickets GitHub (#44 à #47) : `ReservationService` pour Anthony, tests du service et formulaire de réservation pour Chloé, relecture croisée ensemble | Le service suit le diagramme de séquence conçu par Anthony ; tester le code de l'autre oblige chacun à le comprendre, ce qui prépare l'entretien individuel |
| 2026-11-24 | Signature de `reserver()` et exceptions métier poussées en premier | Le formulaire de réservation (#46) peut avancer en parallèle du service |
| 2026-11-24 | Règle du retour en retard tranchée ensemble au début de J5 (#44) | Elle s'applique à la fois au service et au filtre du catalogue |
