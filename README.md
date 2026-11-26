# MSP2-PretOutil

[![CI](https://github.com/IamArayel/MSP2-PretOutil/actions/workflows/ci.yml/badge.svg)](https://github.com/IamArayel/MSP2-PretOutil/actions/workflows/ci.yml)

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

| Compétence                                                     | Mise en œuvre dans Prêt'Outil                                                               |
| -------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Analyser les besoins et maquetter une application              | Expression des besoins, user stories, maquettes Figma, schéma d'enchaînement des écrans     |
| Définir l'architecture logicielle d'une application            | Architecture en couches Symfony, stratégie de sécurité par couche, besoins d'éco-conception |
| Concevoir et mettre en place une base de données relationnelle | MCD / MPD Merise, PostgreSQL, contraintes d'intégrité, jeu d'essai restaurable              |
| Développer des composants d'accès aux données SQL et NoSQL     | Repositories Doctrine ORM (PostgreSQL) et journal d'audit Doctrine ODM (MongoDB)            |

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

| #   | Objectif                             | Indicateur de mesure                                                                                                  |
| --- | ------------------------------------ | --------------------------------------------------------------------------------------------------------------------- |
| O1  | Supprimer les réservations en double | 0 chevauchement possible, garanti par une contrainte en BDD et vérifié par les tests                                  |
| O3  | Assurer la traçabilité               | 100 % des actions sensibles (réservation, sortie, retour, changement de rôle) sont journalisées                       |
| O4  | Protéger les données personnelles    | Seuls l'admin et l'adhérent concerné voient les coordonnées de cet adhérent                                           |
| O5  | Garantir la qualité du code          | 100 % des règles RG1 à RG6 couvertes par au moins un test unitaire ; contrôle d'accès testé sur chaque route protégée |

> O2 (« réserver en moins de 2 minutes ») a été retiré faute de temps pour un test utilisateur (voir [Journal des décisions](#16-journal-des-décisions)).

### Objectif projet

Livrer le MVP fonctionnel, le dossier de projet et le diaporama en **10 jours ouvrés** (plus 2 jours de marge).

## 5. Acteurs

| Rôle                          | Description                                                      |
| ----------------------------- | ---------------------------------------------------------------- |
| Visiteur                      | Personne non connectée : consulte le catalogue et crée un compte |
| Adhérent (`ROLE_USER`)        | Réserve et suit ses emprunts                                     |
| Gestionnaire (`ROLE_MANAGER`) | Bénévole qui gère le catalogue, les sorties et les retours       |
| Administrateur (`ROLE_ADMIN`) | Gère les comptes et les rôles, consulte le journal d'audit       |

## 6. User stories du MVP

| ID   | En tant que...       | je veux...                                                        | afin de...                                  | Critères d'acceptation                                                                     |
| ---- | -------------------- | ----------------------------------------------------------------- | ------------------------------------------- | ------------------------------------------------------------------------------------------ |
| US1  | Visiteur             | créer un compte                                                   | pouvoir réserver                            | Email unique, mot de passe d'au moins 12 caractères, compte actif dès la création          |
| US2  | Utilisateur          | me connecter et me déconnecter                                    | accéder à mon espace                        | Message d'erreur générique en cas d'échec, blocage temporaire après 5 échecs               |
| US3  | Visiteur ou adhérent | consulter et filtrer le catalogue                                 | trouver un objet                            | Filtres par catégorie et par disponibilité sur une période, pagination de 20 objets        |
| US4  | Adhérent             | réserver un objet sur une période                                 | l'emprunter                                 | Respect des règles RG1 à RG5, confirmation affichée                                        |
| US5  | Adhérent             | voir et annuler mes réservations                                  | gérer mes emprunts                          | Annulation possible jusqu'à la veille du début (RG6)                                       |
| US6  | Gestionnaire         | ajouter, modifier ou retirer un objet                             | tenir le catalogue à jour                   | Un objet déjà réservé n'est jamais supprimé physiquement : il est désactivé                |
| US7  | Gestionnaire         | valider la sortie d'un objet                                      | savoir ce qui est dehors                    | La réservation passe au statut « En cours »                                                |
| US8  | Gestionnaire         | enregistrer un retour et l'état de l'objet                        | suivre l'usure du matériel                  | État bon, abîmé ou hors service (RG7)                                                      |
| US9  | Administrateur       | changer le rôle d'un compte ou le désactiver                      | contrôler les accès                         | Action journalisée                                                                         |
| US10 | Administrateur       | consulter le journal d'audit                                      | tracer les actions                          | Liste paginée, filtre par type d'action                                                    |
| US11 | Visiteur ou adhérent | savoir si un objet est disponible sur mes dates avant de réserver | éviter un refus après l'envoi du formulaire | Réponse sans rechargement de la page (API REST interne) ; écran utilisable sans JavaScript |

## 7. Règles de gestion

| ID  | Règle                                                                                                   |
| --- | ------------------------------------------------------------------------------------------------------- |
| RG1 | La date de fin est postérieure ou égale à la date de début, et la date de début n'est pas dans le passé |
| RG2 | Un objet ne peut pas avoir deux réservations actives dont les périodes se chevauchent                   |
| RG3 | La durée d'une réservation est de 14 jours maximum                                                      |
| RG4 | Un adhérent a 3 réservations actives au maximum                                                         |
| RG5 | Un objet hors service ou désactivé n'est pas réservable                                                 |
| RG6 | L'annulation est possible jusqu'à la veille de la date de début                                         |
| RG7 | Un retour avec l'état « hors service » rend l'objet indisponible                                        |
| RG8 | Toute action sensible est enregistrée dans le journal d'audit (MongoDB)                                 |
| RG9 | Un compte désactivé ne peut plus se connecter                                                           |

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
- Application mobile native
- Gestion de plusieurs associations
- Plusieurs exemplaires d'un même objet (dans le MVP : 1 objet = 1 article physique)
- Validation des comptes par l'administrateur
- Export et suppression RGPD automatisés
- Mesures de performance et d'accessibilité chiffrées

## 10. Stack technique

| Besoin            | Choix                                                                                  | Justification                                                                                       |
| ----------------- | -------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| Framework         | Symfony 7 + Twig                                                                       | Rendu côté serveur, sans SPA ; une API REST interne en lecture seule pour les disponibilités (US11) |
| API               | Contrôleur Symfony qui renvoie du JSON, limitation de débit par `symfony/rate-limiter` | Un seul point d'accès en lecture : un framework d'API (API Platform) serait disproportionné         |
| Style             | Tailwind CSS via `symfonycasts/tailwind-bundle` + AssetMapper                          | Pas de Node ni de webpack                                                                           |
| BDD relationnelle | PostgreSQL + Doctrine ORM + Doctrine Migrations                                        | Contrainte `EXCLUDE` pour garantir RG2                                                              |
| BDD NoSQL         | MongoDB + `doctrine/mongodb-odm-bundle`                                                | Journal d'audit : volumineux, sans jointure, en ajout seul                                          |
| Sécurité          | Symfony Security (hasher, `login_throttling`, Voters, CSRF)                            | Fonctionnalités natives, à configurer et justifier                                                  |
| Validation        | Symfony Validator                                                                      | Contrôle de toutes les entrées                                                                      |
| Tests             | PHPUnit (`KernelTestCase`, `WebTestCase`)                                              | Tests unitaires et tests de sécurité                                                                |
| Environnement     | Docker Compose (PHP, PostgreSQL, MongoDB)                                              | Environnement reproductible, base de test restaurable                                               |
| Versionnement     | Git + GitHub                                                                           | Historique, commits au format Conventional Commits                                                  |

## 11. Architecture

```text
Navigateur
 ├─ HTML  ──> Controller (présentation : Twig + Tailwind) ─┐
 └─ fetch ──> Controller d'API (JSON, lecture seule, US11) ┤
                                                           └─> Service métier (ReservationService, CatalogueService, AuditLogger, ...)
                                                                 ├─> Repository Doctrine ORM ──> PostgreSQL (données métier)
                                                                 └─> DocumentManager ODM     ──> MongoDB    (journal d'audit)
```

Rôle de chaque couche :

| Couche           | Responsabilité                               | Sécurité                                                             |
| ---------------- | -------------------------------------------- | -------------------------------------------------------------------- |
| Contrôleur       | Recevoir la requête, déléguer, rendre la vue | `access_control`, Voters, jeton CSRF                                 |
| Formulaire / DTO | Transporter et valider les entrées           | Contraintes Symfony Validator                                        |
| Service métier   | Appliquer les règles de gestion RG1 à RG9    | Vérification des droits métier, exceptions typées                    |
| Repository / ODM | Accéder aux données                          | Requêtes préparées, pas d'opérateur MongoDB issu de l'utilisateur    |
| BDD              | Stocker et garantir l'intégrité              | Clés étrangères, `CHECK`, `EXCLUDE`, utilisateur aux droits minimaux |

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
- [x] Plan de tests et jeu d'essai de la fonctionnalité « Réserver un objet »
- [x] Veille sécurité (OWASP Top 10, injections SQL et NoSQL)
- [ ] Dossier de projet (30 à 40 pages) et diaporama

### Maquettes et diagrammes

| Livrable                                                                        | Source éditable                                                                                                                                        | Export                                                                                     |
| ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------ |
| Cahier des charges                                                              | [docs/cahier-des-charges.md](docs/cahier-des-charges.md)                                                                                               |                                                                                            |
| Diagramme de cas d'utilisation                                                  | [docs/diagrammes/cas-utilisation.puml](docs/diagrammes/cas-utilisation.puml) (PlantUML)                                                                | [SVG](docs/diagrammes/cas-utilisation.svg), [PNG](docs/diagrammes/cas-utilisation.png)     |
| Maquettes (E1, E3, E4, E5, E6, E9) et composants                                | [Figma : Prêt'Outil - Maquettes](https://www.figma.com/design/eyw5N9ZI2sKFkVAalYHFlc)                                                                  | [docs/maquettes/](docs/maquettes/)                                                         |
| Enchaînement des écrans                                                         | [FigJam](https://www.figma.com/board/4JSeLbE6WlRh5la3TAbomW), [Mermaid](docs/diagrammes/enchainement-ecrans.md)                                        | [SVG](docs/diagrammes/enchainement-ecrans.svg)                                             |
| MCD (Merise)                                                                    | [docs/conception/mcd.mcd](docs/conception/mcd.mcd) (Mocodo), [note de conception](docs/conception/mcd.md)                                              | [SVG](docs/conception/mcd.svg), [PNG](docs/conception/mcd.png)                             |
| MLD / MPD PostgreSQL et jeu d'essai                                             | [docs/conception/mpd.sql](docs/conception/mpd.sql), [mpd_tests.sql](docs/conception/mpd_tests.sql), [note de conception](docs/conception/mpd.md)       | [SVG](docs/conception/mpd.svg), [PNG](docs/conception/mpd.png)                             |
| Collection MongoDB `audit_log`                                                  | [docs/conception/audit_log.mongodb.js](docs/conception/audit_log.mongodb.js), [audit_log_tests.mongodb.js](docs/conception/audit_log_tests.mongodb.js) |                                                                                            |
| Dictionnaire de données (et RGPD)                                               | [docs/conception/dictionnaire-donnees.md](docs/conception/dictionnaire-donnees.md)                                                                     |                                                                                            |
| Conventions de nommage                                                          | [docs/conception/conventions-nommage.md](docs/conception/conventions-nommage.md)                                                                       |                                                                                            |
| Requêtes du catalogue (vérification et plan d'exécution)                        | [docs/conception/requetes-catalogue.md](docs/conception/requetes-catalogue.md)                                                                         |                                                                                            |
| ReservationService : règles, exceptions et vérification                         | [docs/conception/reservation-service.md](docs/conception/reservation-service.md)                                                                       |                                                                                            |
| API REST des disponibilités (point d'accès, réponses, erreurs, sécurité, tests) | [docs/conception/api.md](docs/conception/api.md)                                                                                                       |                                                                                            |
| Diagramme de séquence « Réserver un objet »                                     | [PlantUML](docs/diagrammes/sequence-reserver.puml), [note](docs/diagrammes/sequence-reserver.md)                                                       | [SVG](docs/diagrammes/sequence-reserver.svg), [PNG](docs/diagrammes/sequence-reserver.png) |
| Schéma d'architecture                                                           | [PlantUML](docs/diagrammes/architecture.puml), [note](docs/diagrammes/architecture.md)                                                                 | [SVG](docs/diagrammes/architecture.svg), [PNG](docs/diagrammes/architecture.png)           |

Régénérer le diagramme de cas d'utilisation après modification :

```bash
plantuml -tsvg -tpng docs/diagrammes/cas-utilisation.puml
```

## 13. Planning

10 jours ouvrés, plus 2 jours de marge. La colonne « Dossier » se remplit chaque soir (15 min) pour éviter de tout reconstituer à la fin.

| Jour   | Conception et développement                                                                                                                                              | Dossier                                                                         | Statut |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------- | ------ |
| J1     | Expression des besoins finalisée, diagramme de cas d'utilisation, maquettes Figma de 4 écrans (catalogue, réservation, mes réservations, retours), schéma d'enchaînement | Exports des maquettes et diagrammes                                             | ✅     |
| J2     | MCD / MPD, diagramme de séquence « Réserver », schéma d'architecture. Initialisation Symfony, Docker (PostgreSQL, MongoDB, extension PHP `mongodb`), Tailwind            | Justification des choix techniques ([notes J1-J2](docs/dossier/notes-j1-j2.md)) | ✅     |
| J3     | Entités Doctrine, migrations (dont `btree_gist` et `EXCLUDE`), fixtures, script de sauvegarde et de restauration. Inscription, connexion, rôles, `login_throttling`      | Script SQL, règles de nommage                                                   | ✅     |
| J4     | Catalogue paginé et filtré (requête de disponibilité), CRUD des objets, Voters                                                                                           | Extraits des repositories ([notes J4](docs/dossier/notes-j4.md))                | ✅     |
| J5     | `ReservationService` (RG1 à RG6), exceptions métier, formulaire de réservation                                                                                           | Code métier, cas d'exception ([notes J5](docs/dossier/notes-j5.md))             | ✅     |
| J6     | Sortie, retour, annulation, mes réservations. API REST des disponibilités appelée par E4 (US11). Document `AuditLog` (ODM), `AuditLogger`, page du journal               | Extraits du code NoSQL ([notes J6](docs/dossier/notes-j6.md))                   | ✅     |
| J7     | Tests unitaires des RG, tests fonctionnels de sécurité (accès, CSRF, injection), exécution du jeu d'essai                                                                | Plan de tests, jeu d'essai, veille OWASP ([notes J7](docs/dossier/notes-j7.md)) | ✅     |
| J8     | Finitions Tailwind, accessibilité de base, corrections. **Gel du code le soir**                                                                                          | Captures d'écran ([notes J8](docs/dossier/notes-j8.md), [captures](docs/dossier/captures.md)) | ✅     |
| Style  | Charte graphique tirée du logo, logo et favicon, composants de style communs à tous les écrans. Code rouvert une journée, **nouveau gel le soir (`v1.1.0`)** | Captures refaites ([notes Style](docs/dossier/notes-style.md), [captures](docs/dossier/captures.md)) | ✅     |
| J9     |                                                                                                                                                                          | Rédaction du dossier : un dossier par candidat, tronc commun et réalisations personnelles (#88 à #92) | 🟡     |
| J10    |                                                                                                                                                                          | Fin du dossier et diaporama                                                     | ⬜     |
| J12    | Marge (J11 prise par le style)                                                                                                                                                                    | Répétition orale chronométrée (30 min), entretien technique blanc               | ⬜     |

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

| Email                           | Rôle           | Particularité                                           |
| ------------------------------- | -------------- | ------------------------------------------------------- |
| `adherent@pretoutil.test`       | Adhérent       | Réservations dans chaque statut                         |
| `adherent.quota@pretoutil.test` | Adhérent       | 3 réservations actives : une 4e doit être refusée (RG4) |
| `gestionnaire@pretoutil.test`   | Gestionnaire   | Une sortie à valider aujourd'hui                        |
| `admin@pretoutil.test`          | Administrateur | Un retour en retard                                     |
| `desactive@pretoutil.test`      | Adhérent       | Compte désactivé : connexion refusée (RG9)              |

Le jeu d'essai contient aussi 6 catégories et 24 objets (dont un abîmé, un hors service et un retiré du catalogue). Les dates des réservations sont calculées à partir du jour du chargement.

### Authentification

La connexion se fait sur <https://localhost/connexion> (écran E1) avec un compte de test, ou avec un compte créé sur <https://localhost/inscription> (écran E2). La configuration est dans `config/packages/security.yaml`.

| Mécanisme              | Mise en œuvre                                                                                                                                                                                          |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Utilisateurs           | Chargés depuis la table `utilisateur` par leur email ; mots de passe hachés (hasher `auto`)                                                                                                            |
| Rôles                  | `role_hierarchy` : `ROLE_ADMIN` > `ROLE_MANAGER` > `ROLE_USER`                                                                                                                                         |
| Accès                  | `access_control` : `/mes-reservations` (adhérent), `/gestion` (gestionnaire), `/admin` (administrateur) ; un visiteur est redirigé vers E1 avec le message « Connectez-vous pour accéder à cette page… » (`App\Security\ConnexionRequiseEntryPoint`), puis ramené à la page demandée une fois connecté ; un rôle insuffisant reçoit la page 403 |
| Échecs                 | Message générique « Email ou mot de passe incorrect. », que l'email existe ou non                                                                                                                      |
| Blocage (US2)          | `login_throttling` : 5 échecs par email et adresse IP, blocage de 15 minutes                                                                                                                           |
| Compte désactivé (RG9) | `App\Security\UtilisateurChecker`, appliqué après la vérification du mot de passe                                                                                                                      |
| CSRF                   | Jeton sur les formulaires de connexion et d'inscription et sur le lien de déconnexion                                                                                                                  |
| Inscription (US1)      | DTO `InscriptionDto` validé (email valide et unique, mot de passe de 12 caractères minimum), compte créé par `InscriptionService` : email en minuscules, mot de passe haché, `ROLE_USER`, compte actif |

La page `/admin` est provisoire : elle sera remplacée par l'écran E8. `/mes-reservations` est l'écran E5 et `/gestion/sorties-retours` l'écran E6.

#### Pages d'erreur

Pages de la charte, dans `templates/bundles/TwigBundle/Exception/` (#101). Symfony les affiche quand le mode débogage est désactivé, comme en production ; en développement, il montre sa page de débogage, et les pages se prévisualisent sur `https://localhost/_error/{code}`.

| Code | Gabarit | Cas |
|---|---|---|
| 404 | `error404.html.twig` | Adresse inconnue, objet inexistant ou retiré du catalogue |
| 403 | `error403.html.twig` | Personne connectée sans les droits (un visiteur est redirigé vers la connexion) |
| 405 | `error405.html.twig` | Action appelée par un lien au lieu de son bouton |
| 429 | `error429.html.twig` | Trop de requêtes |
| 500 | `error500.html.twig` | Erreur du serveur : page autonome, sans barre de navigation, pour s'afficher même si la base ne répond pas |
| Autres | `error.html.twig` | Page générique |

Mise en page commune : `_page_erreur.html.twig` ; illustrations : `_illustration.html.twig`. Aucun détail technique n'est affiché : il reste dans les logs du serveur. L'API garde ses erreurs en JSON (`ApiExceptionSubscriber`).

`assets/styles/app.css` déclare `@source "../../templates"` : la détection automatique de Tailwind ignore les dossiers nommés `bundles`, et les classes des pages d'erreur ne seraient pas générées.

Pour débloquer un compte pendant les tests, vider le compteur de tentatives :

```bash
docker compose exec php bin/console cache:pool:clear cache.rate_limiter
```

### Réserver un objet (écran E4)

Sur la fiche d'un objet (<https://localhost/catalogue/{id}>), un adhérent connecté choisit ses dates dans le panneau « Réserver cet objet ». Le formulaire est envoyé en POST à `/catalogue/{id}/reserver` (`ROLE_USER`, jeton CSRF).

| Étape     | Qui                                    | Rôle                                                                                                                                                    |
| --------- | -------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Saisie    | `ReservationType` sur `ReservationDto` | Dates présentes, fin après début, début pas dans le passé (RG1) : erreur sous le champ                                                                  |
| Règles    | `ReservationService::reserver()`       | RG1 à RG5, sous transaction ; chaque refus lève une `ReservationRefuseeException`                                                                       |
| Affichage | `CatalogueController::reserver()`      | Succès : redirection 303 vers « Mes réservations » avec « Réservation confirmée » ; refus : fiche réaffichée (422) avec le message et les dates saisies |

Le contrôleur ne connaît aucune règle : il affiche le message de l'exception. Un visiteur est invité à se connecter ; un objet hors service affiche un message à la place du formulaire. Pour essayer chaque refus : `adherent.quota@pretoutil.test` (quota atteint), « Tente 4 places » (période déjà réservée), « Glacière électrique » (retour en retard), une période de plus de 14 jours.

### Journal d'audit (écran E9)

Chaque action sensible écrit un document dans la collection MongoDB `audit_log` (RG8), via `AuditLogger`, après le `COMMIT` PostgreSQL. L'administrateur le consulte sur <https://localhost/admin/journal> (`access_control` sur `/admin` et `#[IsGranted('ROLE_ADMIN')]`), filtré par type d'action, 20 entrées par page, du plus récent au plus ancien.

| Élément                                                           | Rôle                                                                                                                                                                      |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `App\Document\AuditLog` (+ `ActeurAudit`, `CibleAudit` embarqués) | Document ODM calqué sur le validateur `$jsonSchema` ; `details` de type `hash`, toujours écrit comme un objet BSON (`{}` même vide)                                       |
| `AuditLogger`                                                     | Écrit avec le compte `pretoutil_app` (rôle `auditAppendOnly` : `find` et `insert` seulement). MongoDB indisponible : erreur tracée dans les logs, action métier conservée |
| `ConnexionEchoueeListener`                                        | `CONNEXION_ECHOUEE` à chaque échec : email saisi, acteur sans identifiant, motif « échec » ou « blocage » ; ni IP ni indication sur l'existence du compte                 |
| `AuditLogRepository::lister()`                                    | Filtre reçu sous forme d'`ActionAudit` (`tryFrom`) : aucun opérateur MongoDB ne peut venir de la requête (injection NoSQL)                                                |
| `JournalAuditController`                                          | Noms des acteurs lus dans PostgreSQL en une requête par page ; email recopié si le compte n'existe plus, « Système » pour une action anonyme                              |

Actions journalisées : `COMPTE_CREE`, `CONNEXION_ECHOUEE`, `OBJET_CREE`, `OBJET_MODIFIE`, `OBJET_DESACTIVE`, `OBJET_SUPPRIME`, `RESERVATION_CREEE`, `RESERVATION_ANNULEE`, `SORTIE_VALIDEE`, `RETOUR_ENREGISTRE`, et depuis l'écran E8 : `COMPTE_DESACTIVE`, `COMPTE_REACTIVE`, `ROLE_MODIFIE`.

`docs/conception/audit_log.mongodb.js` est rejouable : sur une base existante, il met à jour le validateur (`collMod`) sans toucher aux documents. À relancer quand la liste des actions change (ajout de `OBJET_SUPPRIME` à J6, de `COMPTE_REACTIVE` à J8, de `OBJET_REACTIVE` avec #111), sur `pretoutil` puis sur la base de test `pretoutil_test` :

```bash
docker compose exec -T mongodb sh -c 'mongosh --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin pretoutil' < docs/conception/audit_log.mongodb.js
```

### Sorties et retours (écran E6)

<https://localhost/gestion/sorties-retours>, réservé au gestionnaire et à l'administrateur (`access_control` sur `/gestion` et `#[IsGranted('ROLE_MANAGER')]`).

| Liste                | Contenu                                                                   | Action                                                                                                                |
| -------------------- | ------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| À sortir aujourd'hui | Réservations confirmées dont la période a commencé                        | « Valider la sortie » : `ReservationService::validerSortie()`, confirmée → en cours (US7)                             |
| Retours attendus     | Réservations en cours, retours en retard en premier (badge « En retard ») | « Enregistrer le retour » avec l'état constaté : `ReservationService::enregistrerRetour()`, en cours → terminée (US8) |

- **Refus de sortie** (`SortieImpossibleException`) : réservation plus confirmée, période pas commencée, objet hors service, ou objet pas encore rendu par l'emprunteur précédent.
- **Refus de retour** (`RetourImpossibleException`) : réservation pas en cours.
- **RG7** : l'objet prend l'état constaté au retour. « Hors service » le rend non réservable ; ses réservations confirmées sont **signalées** au gestionnaire (nom, email, dates), pas annulées.
- Chaque transition s'exécute dans une transaction, avec la réservation verrouillée (`SELECT ... FOR UPDATE`) : deux gestionnaires ne peuvent pas valider la même sortie. Journalisation `SORTIE_VALIDEE` et `RETOUR_ENREGISTRE` après le `COMMIT`.
- Actions en POST, jeton CSRF propre à l'action et à la réservation.

Requête des retours : avec `ORDER BY date_fin` seul, PostgreSQL lit l'index partiel `idx_reservation_en_cours` dans l'ordre, sans tri en mémoire (`EXPLAIN` : `Index Scan using idx_reservation_en_cours`, mesuré avec le parcours séquentiel désactivé ; sur les 11 lignes du jeu d'essai, il préfère lire la table entière).

### Gestion du catalogue (écran E7)

<https://localhost/gestion/catalogue>, accessible au gestionnaire et à l'administrateur (compte de test `gestionnaire@pretoutil.test`).

| Action                                                                  | Méthode                                           | Règle                                          |
| ----------------------------------------------------------------------- | ------------------------------------------------- | ---------------------------------------------- |
| Lister tous les objets, actifs et retirés                               | GET, paginé par 20                                | `OBJET_GERER`                                  |
| Ajouter, modifier (catégorie, nom, description, caractéristiques, état) | Formulaire `ObjetType` sur `ObjetDto`, jeton CSRF | `OBJET_GERER`                                  |
| Désactiver (retirer du catalogue, RG5)                                  | POST, jeton CSRF propre à l'objet                 | `OBJET_GERER`                                  |
| Réactiver (remettre au catalogue, #111)                                 | POST, jeton CSRF propre à l'objet                 | `OBJET_GERER`                                  |
| Supprimer définitivement                                                | POST, jeton CSRF propre à l'objet, confirmation   | `OBJET_SUPPRIMER` : objet jamais réservé (US6) |

Deux protections : `access_control` sur `/gestion` et `ObjetVoter` sur chaque action. Un adhérent reçoit un 403. Le bouton « Supprimer » n'apparaît que pour les objets jamais réservés, calculés en une seule requête pour la page ; si une réservation arrive entre-temps, la clé étrangère refuse la suppression et un message l'explique. Les actions passent par `ObjetService`, qui appelle `AuditLogger` (`OBJET_CREE`, `OBJET_MODIFIE`, `OBJET_DESACTIVE`, `OBJET_REACTIVE`).

### Gestion des comptes (écran E8)

<https://localhost/admin/comptes>, accessible à l'administrateur seulement (compte de test `admin@pretoutil.test`) ; `/admin` y renvoie.

| Action | Méthode | Journal |
|---|---|---|
| Lister les comptes (nom, email, rôle, actif, dernière activité) | GET, paginé par 20, comptes actifs d'abord | |
| Changer le rôle : adhérent, gestionnaire ou administrateur | POST, jeton CSRF propre au compte | `ROLE_MODIFIE` (`ancien_role`, `nouveau_role`) |
| Désactiver le compte (RG9) | POST, jeton CSRF propre au compte, confirmation | `COMPTE_DESACTIVE` |
| Réactiver le compte | POST, jeton CSRF propre au compte | `COMPTE_REACTIVE` |

Règles (`CompteService`) :

- un administrateur n'agit pas sur son propre compte : l'association garde toujours un administrateur actif ;
- un compte désactivé perd sa session à sa requête suivante, et ne peut plus se reconnecter (`Utilisateur::isEqualTo()`, `UtilisateurChecker`) ;
- un changement de rôle ferme aussi la session du compte : les nouveaux droits s'appliquent à la reconnexion ;
- une action sans effet (même rôle, compte déjà désactivé) n'écrit rien.

Deux protections, comme les autres écrans : `access_control` sur `/admin` et `IsGranted('ROLE_ADMIN')` sur le contrôleur.

### Comptes des bases de données

L'application ne se connecte jamais avec le propriétaire du schéma ni avec le compte root de MongoDB (droits minimaux).

| Base       | Compte                     | Droits                                                                         | Utilisé par                                                |
| ---------- | -------------------------- | ------------------------------------------------------------------------------ | ---------------------------------------------------------- |
| PostgreSQL | `pretoutil` (propriétaire) | Tous                                                                           | Migrations et fixtures (connexion Doctrine `proprietaire`) |
| PostgreSQL | `pretoutil_app`            | `SELECT`, `INSERT`, `UPDATE` sur les 4 tables ; `DELETE` sur `objet` seulement | Application (connexion Doctrine `default`)                 |
| MongoDB    | `pretoutil` (root)         | Tous                                                                           | Administration (`mongosh`)                                 |
| MongoDB    | `pretoutil_app`            | Rôle `auditAppendOnly` : `find` et `insert` sur `audit_log` seulement          | Application (Doctrine ODM)                                 |

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

|                         | PostgreSQL (pgAdmin)                                  | MongoDB (Compass)                                                                         |
| ----------------------- | ----------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| Hôte                    | `localhost`                                           | `localhost`                                                                               |
| Port                    | `5433`                                                | `27018`                                                                                   |
| Base                    | `pretoutil`                                           | `pretoutil`                                                                               |
| Compte d'administration | `pretoutil`, mot de passe `POSTGRES_PASSWORD`         | `pretoutil`, mot de passe `MONGO_INITDB_ROOT_PASSWORD`, base d'authentification `admin`   |
| Compte de l'application | `pretoutil_app`, mot de passe `POSTGRES_APP_PASSWORD` | `pretoutil_app`, mot de passe `MONGODB_APP_PASSWORD`, base d'authentification `pretoutil` |

Les mots de passe sont dans `.env.local`. Le compte de l'application permet de vérifier ses droits réels (un `DELETE` sur `reservation` est refusé).

Port déjà pris sur la machine : choisir un autre port au démarrage, par exemple `POSTGRES_PORT=5434 MONGODB_PORT=27019 docker compose up -d`.

### Sauvegarde et restauration

Deux scripts, lancés depuis la machine hôte avec les conteneurs démarrés. Ils lisent les identifiants dans les conteneurs (variables de `.env.local`) : aucun mot de passe n'est à saisir ni stocké dans les scripts.

```bash
scripts/sauvegarde.sh
scripts/restauration.sh sauvegardes/<AAAAMMJJ-HHMMSS>
```

| Fichier de la sauvegarde | Contenu                                                               | Outil                        |
| ------------------------ | --------------------------------------------------------------------- | ---------------------------- |
| `postgresql.dump`        | Schéma, données, contraintes et droits de `pretoutil_app`             | `pg_dump --format=custom`    |
| `mongodb.archive.gz`     | Collection `audit_log` avec son validateur et ses index (dont le TTL) | `mongodump --archive --gzip` |
| `SHA256SUMS`             | Empreintes des deux fichiers                                          | `shasum -a 256`              |

- **Sauvegarde** : dans `sauvegardes/<date UTC>/` (non versionné), fichiers lisibles par le seul utilisateur courant. Si une étape échoue, le dossier incomplet est supprimé.
- **Restauration** : vérifie les empreintes, demande confirmation (`--oui` pour l'éviter dans un script), puis remplace les données. PostgreSQL est restauré dans une seule transaction : en cas d'erreur, la base reste dans son état précédent.
- **Rôles et utilisateurs applicatifs** : non sauvegardés, ils sont recréés par les scripts de `docker/` à la création des volumes. Les sauvegardes ne contiennent donc aucune empreinte de mot de passe des comptes de base de données.
- **Restaurer sur des volumes neufs** : `docker compose down -v`, `docker compose up -d --wait`, puis `scripts/restauration.sh`.

Règles d'exploitation :

| Règle             | Valeur                                                                                                     |
| ----------------- | ---------------------------------------------------------------------------------------------------------- |
| Fréquence         | Une fois par jour, et avant chaque migration ou mise en production                                         |
| Conservation      | 30 jours glissants ; au-delà, les sauvegardes sont supprimées                                              |
| Emplacement       | Copie hors du serveur de l'application (autre machine ou stockage externe)                                 |
| Protection (RGPD) | Les sauvegardes contiennent des données personnelles : stockage chiffré, accès réservé aux administrateurs |
| Contrôle          | Une restauration d'essai sur des volumes neufs au moins une fois par mois                                  |

Test du 2026-11-23, sur une pile Docker isolée : sauvegarde du jeu d'essai et de 3 documents `audit_log`, suppression des volumes, recréation, restauration. L'état après restauration est identique à l'état avant : nombre de lignes, empreinte MD5 des tables `utilisateur` et `reservation`, contraintes, index, droits de `pretoutil_app`, documents, validateur et index TTL d'`audit_log`. L'application lit les données restaurées avec le compte `pretoutil_app`. Cas d'erreur vérifiés : sauvegarde altérée (empreinte refusée), fichier manquant, confirmation refusée, restauration sur une base déjà remplie, base arrêtée pendant la sauvegarde.

### Après un `git pull`

Le dossier du projet, `vendor/` compris, est monté dans le conteneur `php`. Celui-ci n'installe les dépendances que si `vendor/` est vide : il ne le met pas à jour quand `composer.lock` change. Sans mise à jour, le conteneur s'arrête au démarrage avec une erreur `Class "..." not found`.

Si le pull modifie `composer.lock`, installer les nouvelles dépendances sans démarrer l'application, puis relancer :

```bash
docker compose run --rm --no-deps php composer install
docker compose up -d --wait
```

Si le pull modifie `compose*.yaml` ou `frankenphp/` (configuration de Caddy), recréer le conteneur : `docker compose up -d --wait`.

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

Avertissement de certificat sur `https://localhost` : le certificat est signé par une autorité locale créée par Caddy. Pour que les navigateurs, y compris automatisés, l'acceptent, l'ajouter une fois au trousseau de macOS (mot de passe de session demandé) :

```bash
docker compose cp php:/data/caddy/pki/authorities/local/root.crt /tmp/caddy-root.crt
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain /tmp/caddy-root.crt
```

### API REST des disponibilités

`GET /api/objets/{id}/disponibilites`, avec `debut` et `fin` facultatifs (`AAAA-MM-JJ`) : lecture seule, publique, 30 appels par minute et par adresse IP. L'écran E4 l'appelle quand l'adhérent saisit ses dates. Documentation complète : [docs/conception/api.md](docs/conception/api.md).

```bash
curl -k "https://localhost/api/objets/1/disponibilites?debut=2026-12-02&fin=2026-12-04"
```

### Intégration continue

Workflow GitHub Actions [`.github/workflows/ci.yml`](.github/workflows/ci.yml), lancé à chaque pull request, à chaque push sur `main` et à la demande (onglet Actions). Il reproduit l'environnement local sur une machine de GitHub, puis lance les mêmes commandes que ce README :

| Étape | Ce qu'elle vérifie |
|---|---|
| Secrets éphémères | `.env.local` généré pour l'exécution (valeurs aléatoires) : aucun vrai mot de passe dans la CI ni dans le dépôt |
| Image et pile Docker | Image de développement construite (couches mises en cache), PHP, PostgreSQL et MongoDB démarrés ; comptes des bases et journal d'audit de test créés par les scripts d'initialisation, migrations appliquées |
| `composer validate` | `composer.json` et `composer.lock` cohérents |
| `composer audit`, `importmap:audit` | Aucune faille connue dans les dépendances PHP et JavaScript |
| `lint:twig`, `lint:yaml`, `lint:container` | Gabarits, configuration et conteneur de services valides |
| `doctrine:schema:validate --skip-sync` | Mapping des entités valide (la synchronisation n'est pas comparée : contraintes `CHECK` et `EXCLUDE` écrites dans les migrations) |
| PHPUnit | Suite complète sur les bases de test, comme en local |

En cas d'échec, l'étape fautive est marquée en rouge dans la PR et les journaux des conteneurs sont affichés. Un nouveau push sur la même branche annule l'exécution précédente.

### Déploiement (production)

L'application est en ligne sur <https://pretoutil.thecorelab.cloud>, sur un VPS Hostinger avec Docker et [Dokploy](https://dokploy.com) (#115). Dokploy récupère le dépôt sur GitHub, construit l'image de production et démarre la pile décrite dans [`deploy/dokploy/compose.yaml`](deploy/dokploy/compose.yaml) ; son proxy Traefik reçoit le trafic sur les ports 80 et 443 et gère le certificat HTTPS (Let's Encrypt).

```text
Internet ──HTTPS──> Traefik (Dokploy, certificat Let's Encrypt)
                       └─HTTP──> php : FrankenPHP, image frankenphp_prod, port 80 interne
                                  ├─> database : PostgreSQL 16 (aucun port publié)
                                  └─> mongodb  : MongoDB 8.0  (aucun port publié)
```

| Point | Choix | Raison |
|---|---|---|
| Emplacement du compose | `deploy/dokploy/` | Dokploy écrit ses variables dans un `.env` à côté du compose : à la racine, il écraserait le `.env` de Symfony |
| Secrets | Onglet Environment de Dokploy ; dossier `deploy/` exclu de l'image (`.dockerignore`) et son `.env` ignoré par Git | Aucun mot de passe dans le dépôt ni dans l'image |
| URL des bases | Passées complètes par le compose | L'image fige celles du `.env` à la construction (`composer dump-env prod`), sans mot de passe |
| HTTPS | Terminé par Traefik ; Caddy sert en HTTP interne (`SERVER_NAME=:80`) ; Symfony fait confiance aux réseaux privés (`TRUSTED_PROXIES=private_ranges`, `when@prod` dans `framework.yaml`) | Symfony sait que la requête d'origine était en HTTPS : cookies `secure`, HSTS, liens absolus |
| Ports | Aucun port publié par le compose ; seul Traefik écoute sur 80 et 443 | Bases inaccessibles depuis Internet |
| Migrations | Appliquées au démarrage du conteneur `php` par le compte propriétaire (`frankenphp/docker-entrypoint.sh`) | Schéma à jour à chaque déploiement, sans action manuelle |
| Données | Volumes nommés (`database_data`, `mongodb_data`, `uploads_objets`) | Conservés d'un déploiement à l'autre ; sauvegardables par Dokploy |
| Utilisateur | L'image tourne en `www-data` (non root) ; `var/` et `public/uploads/` lui appartiennent dans l'image | Un volume neuf reprend ce propriétaire : l'envoi de photos depuis E7 fonctionne |

**Mise en place dans Dokploy** (une seule fois) :

1. Projet, puis *Create Service* > *Compose*, type *Docker Compose*.
2. *Provider* GitHub, dépôt `IamArayel/MSP2-PretOutil`, branche `main`, *Compose Path* `./deploy/dokploy/compose.yaml`.
3. Onglet *Environment* : les 6 variables listées en tête du compose, générées par `openssl rand -hex 16` (et `DOMAINE=pretoutil.thecorelab.cloud`).
4. Onglet *Domains* : `pretoutil.thecorelab.cloud`, service `php`, port `80`, HTTPS activé, certificat Let's Encrypt.
5. *Deploy*.

**Données de démonstration** (une seule fois, sur une base vide) : ajouter `DEMO_MOT_DE_PASSE` dans l'onglet *Environment* (au moins 12 caractères, différent du mot de passe de développement), redéployer, puis lancer dans le terminal du conteneur `php` (onglet *Docker* ou *Terminal* de Dokploy) :

```bash
bin/console app:demo:initialiser
```

La commande charge le même jeu d'essai que les fixtures ([`src/Demo/JeuDEssai.php`](src/Demo/JeuDEssai.php)) : 6 catégories, 5 comptes, 24 objets dont 14 avec photo, 11 réservations. Les comptes (`admin@pretoutil.test`, `gestionnaire@pretoutil.test`, etc.) ont le mot de passe secret de `DEMO_MOT_DE_PASSE`, jamais celui du dépôt. Elle refuse d'écrire si un compte existe déjà : impossible de mélanger la démonstration à de vraies données. Les fixtures, elles, ne sont pas dans l'image de production (dépendance de développement) et videraient la base.

**Mise à jour** : fusionner dans `main`, puis *Deploy* dans Dokploy (ou déploiement automatique sur push, onglet *General*). **Retour arrière** : redéployer un commit ou un tag précédent. Les volumes ne sont pas touchés.

**DNS** : enregistrement A `pretoutil` vers l'adresse IP du VPS, dans la zone DNS du domaine chez Hostinger.

### Tests

PHPUnit est installé en dépendance de développement (`symfony/test-pack`). Les tests sont dans `tests/`, rangés comme le code de `src/`.

Les tests d'intégration utilisent une base à part, `pretoutil_test` (suffixe `_test` ajouté par `doctrine.yaml` en environnement `test`). À créer une fois, puis à migrer après chaque nouvelle migration :

```bash
docker compose exec -e APP_ENV=test php bin/console doctrine:database:create --connection=proprietaire --if-not-exists
docker compose exec -e APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
```

Elle a les mêmes comptes que la base de développement : créée et migrée par le propriétaire, utilisée par les tests avec `pretoutil_app`. Elle reste vide : chaque test d'intégration s'exécute dans une transaction annulée à la fin. Elle disparaît avec `docker compose down -v`.

Le journal d'audit des tests est dans une base MongoDB à part, `pretoutil_test` (`.env.test`), avec le même validateur et le même compte `pretoutil_app`. Elle est créée avec les volumes ; sur un volume plus ancien, la créer une fois :

```bash
docker compose exec mongodb sh -c 'mongosh --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin "$MONGO_INITDB_DATABASE" /docker-entrypoint-initdb.d/03-base-de-test.js'
```

Le compte de l'application ne peut rien supprimer : les documents écrits par les tests restent dans `pretoutil_test`, retrouvés par un marqueur unique, et l'index TTL les efface au bout d'un an.

```bash
docker compose exec php bin/phpunit --testdox
```

| Tests                                                     | Ce qu'ils vérifient                                                                                                                                                                                                                                                                                                             |
| --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `tests/Security/Voter/ObjetVoterTest.php`                 | `OBJET_GERER` : gestionnaire et administrateur autorisés, adhérent et visiteur refusés ; `OBJET_SUPPRIMER` : refusé pour un objet déjà réservé (US6)                                                                                                                                                                            |
| `tests/Service/ReservationServiceTest.php`                | Règles RG1 à RG5 de `ReservationService` et annulation : une méthode par branche du diagramme de séquence, cas limites (14 et 15 jours, aujourd'hui, hier, 3e et 4e réservation) ; base simulée                                                                                                                                 |
| `tests/Service/AuditLoggerTest.php`                       | Document construit depuis l'action ; MongoDB indisponible : erreur tracée, aucune exception vers le service métier ; connexion échouée sans identifiant                                                                                                                                                                         |
| `tests/Integration/AuditLogIntegrationTest.php`           | Sur MongoDB `pretoutil_test` : document écrit puis relu (types BSON), `details` vide écrit comme objet, 3 documents invalides refusés par le validateur (code 121), suppression et modification refusées au compte applicatif (code 13), filtre et tri du journal, `CONNEXION_ECHOUEE` après un vrai échec de connexion         |
| `tests/Integration/ReservationServiceIntegrationTest.php` | Sur `pretoutil_test` : contrainte `EXCLUDE` (SQLSTATE 23P01) traduite en `PeriodeIndisponibleException`, réservation annulée qui ne bloque ni la période ni le quota                                                                                                                                                            |
| `tests/Security/Voter/ReservationVoterTest.php`           | `RESERVATION_VOIR` : propriétaire et gestionnaire autorisés, autre adhérent refusé (O4) ; `RESERVATION_ANNULER` : propriétaire seulement, réservation confirmée, jusqu'à la veille du début (RG6)                                                                                                                               |
| `tests/Controller/Api/DisponibiliteControllerTest.php`    | API des disponibilités : réponses JSON, absence de donnée personnelle, mêmes règles que la réservation, erreurs 400, 404 et 405, limite de débit (429)                                                                                                                                                                          |
| `tests/Service/CompteServiceTest.php` | Écran E8 sans base : rôle changé et journalisé avec l'ancien et le nouveau rôle, désactivation et réactivation journalisées, action sans effet sans écriture, refus sur son propre compte |
| `tests/Controller/GestionComptesControllerTest.php` | Écran E8 par HTTP : actions depuis la liste, refus sur soi-même, jeton faux, rôle inconnu, session fermée après une désactivation ou un changement de rôle, détail lisible sur E9 |
| `tests/Controller/AccessibiliteTest.php` | Accessibilité de E1 à E9, états d'erreur compris : langue, titre de page, titres sans saut, étiquette de chaque champ, aide et erreurs reliées au champ, tableaux, lien d'évitement, page en cours dans la navigation, nom accessible qui contient le texte visible, contour des listes de choix, tableaux défilants dans leur cadre |
| `tests/Controller/ControleAccesTest.php`                  | Contrôle d'accès des 27 routes pour les 4 profils (O5), liste des routes complète, actions refusées en GET, déconnexion avec jeton, compte désactivé (RG9), réservation d'un autre adhérent (IDOR), objet retiré ou inconnu                                                                                                     |
| `tests/Controller/SecuriteTest.php`                       | Tests de sécurité (#66) : CSRF des 10 formulaires en POST (jeton absent ou faux refusé, vrai jeton accepté), injection SQL (filtres du catalogue, paramètres d'URL, saisies de l'inscription), injection NoSQL (filtre de E9), XSS (E3, E4, E7, E9, API), blocage après 5 échecs de connexion, mot de passe stocké en empreinte |
| `tests/Controller/PagesErreurTest.php` | Pages d'erreur sans le mode débogage (404, 403, 405, 429, 500, générique) : code conservé, titres, structure accessible, aucun détail technique ; API toujours en JSON ; redirection vers la connexion avec un message, puis retour à la page demandée |
| `tests/Controller/EntetesSecuriteTest.php`                | En-têtes de sécurité HTTP sur les pages et l'API (CSP, HSTS en HTTPS, `X-Frame-Options`, `nosniff`, `Referrer-Policy`), nonce sur chaque script et différent à chaque requête, aucun JavaScript en ligne dans les gabarits                                                                                                      |

Veille sécurité (OWASP Top 10:2025, chaque risque avec sa mesure et sa preuve, audit des dépendances) : [docs/securite/veille-owasp.md](docs/securite/veille-owasp.md).

Accessibilité de base (grille RGAA par écran, contrastes de la charte, vérifications manuelles) : [docs/tests/accessibilite.md](docs/tests/accessibilite.md).

Plan de tests (objectifs, niveaux, matrice du contrôle d'accès, critères de réussite) : [docs/tests/plan-de-tests.md](docs/tests/plan-de-tests.md).

Jeu d'essai de « Réserver un objet » (20 cas : entrée, attendu, obtenu) : [docs/tests/jeu-essai-reserver.md](docs/tests/jeu-essai-reserver.md). Il se rejoue par HTTP sur les fixtures, en environnement de développement :

```bash
bash docs/tests/jeu_essai_reserver.sh
```

Correspondance entre chaque règle de gestion et ses tests : [docs/tests/correspondance-rg-tests.md](docs/tests/correspondance-rg-tests.md).

Les tests des Voters sont des tests unitaires : ni base de données ni conteneur Symfony. La hiérarchie des rôles est reconstruite comme dans `security.yaml`, et la date du jour est fixée par une horloge de test (`MockClock`).

### Styles (Tailwind CSS)

Tailwind CSS 4 est intégré via `symfonycasts/tailwind-bundle` et AssetMapper : pas de Node ni de npm.

| Fichier                      | Rôle                                                                                                                                          |
| ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `assets/styles/app.css`      | Source : import de Tailwind, police Nunito et charte tirée du logo dans le bloc `@theme` (bleu pétrole, encre, orange, vert d'eau, rouille, neutres slate, couleurs des statuts) |
| `var/tailwind/app.built.css` | CSS compilé, non versionné, servi à la place de `app.css`                                                                                     |
| `templates/base.html.twig`   | Gabarit commun : favicons, barre de navigation (`templates/_partials/navbar.html.twig`) et pied de page (`templates/_partials/pied_de_page.html.twig`) |
| `assets/images/`             | Logo complet (`logo.svg`), icône seule (`logo-icone.svg`), favicons PNG ; SVG sans feuille de styles interne, couleurs en attributs |
| `fixtures/photos-objets/`    | Photos sources du jeu d'essai, passées par `StockagePhotoObjet` au chargement des fixtures comme une photo envoyée depuis E7 |
| `public/uploads/objets/`     | Photos des objets (#106), non versionnées : WebP 960 × 720 (4:3) à nom aléatoire, enregistré dans `objet.image` ; servies par le serveur web, volume `photos_objets` en production |
| `assets/fonts/`              | Police Nunito variable (WOFF2, caractères latins, 39 Ko) et sa licence OFL ; aucun appel à un service de polices tiers |

En développement, le service `tailwind` de `compose.override.yaml` lance `tailwind:build --watch` : le CSS est recompilé à chaque modification de `assets/` ou `templates/`. Le résultat est stocké dans le volume `tailwind_build`, partagé avec le conteneur `php` (son `var/` n'est pas lié à la machine hôte). Pour suivre la compilation :

```bash
docker compose logs -f tailwind
```

Les couleurs de la charte sont disponibles comme utilitaires : `bg-primary`, `text-primary`, `bg-accent`, `text-danger`, `border-line`, `text-ink-muted`, `bg-statut-disponible-bg`, etc. L'orange (`accent`) et le vert d'eau (`secondaire`) du logo n'ont pas le contraste d'un texte : ils servent de fond, avec un texte `ink`, ou de décor. Contrastes : [docs/tests/accessibilite.md](docs/tests/accessibilite.md), section 3.

Composants communs à tous les écrans (#95), définis une seule fois. Les gabarits n'utilisent que ces composants et les couleurs nommées de la charte, jamais une couleur Tailwind brute (`AccessibiliteTest` le vérifie).

| Composant | Où | Usage |
|---|---|---|
| Boutons : `bouton`, `bouton-ligne` (tableaux) + `bouton-primaire`, `bouton-accent`, `bouton-secondaire`, `bouton-danger` ; `lien` | `app.css` | Relief au survol, enfoncement au clic, état désactivé (`disabled`, `aria-disabled`) |
| `badge` + `badge-disponible`, `-reserve`, `-sorti`, `-hors-service`, `-neutre`, `-accent` | `app.css` | Statuts, rôles, objet retiré |
| `champ`, `champ-erreur`, `liste-choix` | `app.css`, thème `templates/form/theme.html.twig` | Champs de formulaire : contour 4,77:1, halo au focus |
| `cadre-tableau`, `tableau`, `tableau-entete`, `cellule-entete`, `cellule`, `cellule-vide` | `app.css` | Tableaux défilants sur mobile, ligne survolée teintée |
| `panneau`, `carte`, `grille-cartes` | `app.css` | Blocs de contenu ; cartes qui se soulèvent et apparaissent l'une après l'autre |
| `lien-navigation`, `logo-anime` | `app.css` | Soulignement orange de la page en cours, icône du logo animée au survol |
| Message (flash, refus, information) | `templates/_partials/message.html.twig` | Icône, couleur, `role="status"` ou `role="alert"`, apparition en glissant |
| En-tête de page | `templates/_partials/entete_page.html.twig` | Titre `h1`, sous-titre, actions |
| État vide | `templates/_partials/etat_vide.html.twig` | Illustration, message, lien pour continuer |
| Carte d'objet | `templates/_partials/carte_objet.html.twig` | Catalogue (E3) |
| Pagination | `templates/_partials/pagination.html.twig` | E3, E7, E8, E9 |

Page de référence, en développement seulement : <https://localhost/dev/composants> (route `env: 'dev'`, absente en test et en production).

Animations courtes et jouées une seule fois (apparition des pages et des cartes, messages, survol des boutons et des cartes). Avec le réglage système « Réduire les animations », elles deviennent instantanées (`prefers-reduced-motion`, WCAG 2.3.3).

Les classes doivent être écrites en entier dans les gabarits : Tailwind ne génère que les classes qu'il y lit. Pour choisir une variante selon une valeur, passer par une table (`{disponible: 'badge-disponible', …}`), jamais par un nom construit (`'badge-' ~ statut`).

### Variables d'environnement

| Fichier      | Versionné | Contenu                                                                                                                                                                                                       |
| ------------ | --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `.env`       | Oui       | Valeurs non secrètes : `APP_ENV`, noms des bases et des comptes (`POSTGRES_*`, `MONGODB_*`, lus aussi par Docker Compose), modèles de `DATABASE_URL`, `DATABASE_OWNER_URL` et `MONGODB_URI` sans mot de passe |
| `.env.local` | Non       | Secrets : `APP_SECRET`, `POSTGRES_PASSWORD`, `POSTGRES_APP_PASSWORD`, `MONGO_INITDB_ROOT_PASSWORD`, `MONGODB_APP_PASSWORD`                                                                                    |

Aucun mot de passe n'est versionné. `compose.yaml` transmet `.env.local` aux trois conteneurs (`env_file`).

### Commandes utiles

| Action                                                                                                                   | Commande                                                                                                                                                 |
| ------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Arrêter les conteneurs                                                                                                   | `docker compose down`                                                                                                                                    |
| Suivre les logs                                                                                                          | `docker compose logs -f php`                                                                                                                             |
| Console Symfony                                                                                                          | `docker compose exec php bin/console <commande>`                                                                                                         |
| Vider le cache (gabarit Twig modifié mais pas pris en compte : FrankenPHP garde l'application en mémoire en mode worker) | `docker compose exec php bin/console cache:clear`                                                                                                        |
| Ajouter une dépendance                                                                                                   | `docker compose exec php composer require <paquet>`                                                                                                      |
| Compiler les styles (minifiés)                                                                                           | `docker compose exec php bin/console tailwind:build --minify`                                                                                            |
| Installer les dépendances (conteneur `php` arrêté)                                                                       | `docker compose run --rm --no-deps php composer install`                                                                                                 |
| Débloquer les comptes après 5 échecs                                                                                     | `docker compose exec php bin/console cache:pool:clear cache.rate_limiter`                                                                                |
| Lancer les tests                                                                                                         | `docker compose exec php bin/phpunit --testdox`                                                                                                          |
| Rechercher les failles connues des dépendances                                                                           | `docker compose exec php composer audit` puis `docker compose exec php bin/console importmap:audit`                                                      |
| Appliquer les migrations                                                                                                 | `docker compose exec php bin/console doctrine:migrations:migrate`                                                                                        |
| Ouvrir un shell PostgreSQL                                                                                               | `docker compose exec database psql -U pretoutil pretoutil`                                                                                               |
| Ouvrir un shell MongoDB (root)                                                                                           | `docker compose exec mongodb sh -c 'mongosh -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin pretoutil'` |
| Recharger le jeu d'essai                                                                                                 | `docker compose exec php bin/console doctrine:fixtures:load -n --em=proprietaire`                                                                        |
| Sauvegarder les bases                                                                                                    | `scripts/sauvegarde.sh`                                                                                                                                  |
| Restaurer une sauvegarde                                                                                                 | `scripts/restauration.sh sauvegardes/<AAAAMMJJ-HHMMSS>`                                                                                                  |
| Tout supprimer, bases comprises                                                                                          | `docker compose down -v`                                                                                                                                 |

## 16. Journal des décisions

| Date       | Décision                                                                                                                                                                                                                                   | Raison                                                                                                                                                                                                                                                                                |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-11-23 | Choix du sujet Prêt'Outil parmi 5 propositions (Prêt'Outil, CréneauClub, TicketDesk, MédiRappel, LudoSoirée)                                                                                                                               | Sujet le plus sûr pour couvrir toute la grille du CCP 2 ; règle métier forte (anti-chevauchement) pour le jeu d'essai ; usage NoSQL justifié par le journal d'audit                                                                                                                   |
| 2026-11-23 | Planning ramené de 8 semaines à 10 jours (+ 2 jours de marge)                                                                                                                                                                              | Temps disponible avant l'épreuve                                                                                                                                                                                                                                                      |
| 2026-11-23 | MVP resserré : compte actif dès l'inscription, admin limité aux rôles et à la désactivation, RGPD décrit mais non automatisé                                                                                                               | Tenir les 10 jours en gardant toutes les compétences évaluées                                                                                                                                                                                                                         |
| 2026-11-23 | Objectif O2 retiré, O5 reformulé (couverture des RG et du contrôle d'accès)                                                                                                                                                                | Pas de temps pour un test utilisateur ; objectif de couverture plus mesurable dans le délai                                                                                                                                                                                           |
| 2026-11-23 | Stack : Symfony 7, Twig, Tailwind (AssetMapper), PostgreSQL, MongoDB (Doctrine ODM), Docker                                                                                                                                                | Rendu serveur plus rapide à produire ; `EXCLUDE` PostgreSQL pour RG2 ; ODM proche de l'ORM                                                                                                                                                                                            |
| 2026-11-23 | Rendu côté serveur (Twig) plutôt qu'une API + SPA                                                                                                                                                                                          | Gain de temps, l'épreuve évalue la conception et l'accès aux données, pas le front                                                                                                                                                                                                    |
| 2026-11-23 | Cahier des charges séparé du README (`docs/cahier-des-charges.md`)                                                                                                                                                                         | Le README reste la vue d'ensemble et l'historique ; le cahier des charges est le document de référence pour le dossier, avec inventaire des écrans et critères de recette                                                                                                             |
| 2026-11-23 | Hiérarchie des rôles : Admin > Gestionnaire > Adhérent > Visiteur                                                                                                                                                                          | Un gestionnaire peut aussi emprunter ; simplifie `access_control` avec `role_hierarchy`                                                                                                                                                                                               |
| 2026-11-23 | 6 écrans maquettés sur 9 (E1, E3, E4, E5, E6, E9) ; E2, E7, E8 non maquettés                                                                                                                                                               | Ils réutilisent les mêmes composants (formulaire, tableau) ; gain de temps sur J1                                                                                                                                                                                                     |
| 2026-11-23 | Maquettes construites avec des composants Figma (Bouton, Badge, Champ, Navbar, CarteObjet) et la palette Tailwind en variables                                                                                                             | Correspondance directe avec les composants Twig et les classes Tailwind à J4-J8                                                                                                                                                                                                       |
| 2026-11-23 | Diagramme de cas d'utilisation en PlantUML, enchaînement des écrans en Mermaid                                                                                                                                                             | Sources textuelles versionnées dans Git, régénérables                                                                                                                                                                                                                                 |
| 2026-11-23 | Projet réalisé en binôme (Anthony Dégeilh, Chloé Baïsse)                                                                                                                                                                                   | Organisation de la formation                                                                                                                                                                                                                                                          |
| 2026-11-23 | Environnement basé sur `dunglas/symfony-docker` (FrankenPHP), Symfony 7.4 LTS, PostgreSQL 16                                                                                                                                               | Modèle maintenu par la communauté Symfony, HTTPS local prêt à l'emploi ; version LTS maintenue jusqu'en 2028                                                                                                                                                                          |
| 2026-11-23 | `.gitignore` Visual Studio remplacé par un `.gitignore` Symfony ; `.env.dev` non versionné, `APP_SECRET` dans `.env.local`                                                                                                                 | L'ancien fichier ignorait `.env` et `bin/console` ; aucun secret dans le dépôt                                                                                                                                                                                                        |
| 2026-11-23 | MongoDB conservé pour le journal d'audit, après avoir envisagé PostgreSQL seul                                                                                                                                                             | Couvre la partie NoSQL de la compétence « composants d'accès aux données SQL et NoSQL » ; `details` de forme libre selon l'action                                                                                                                                                     |
| 2026-11-23 | MongoDB 8.0 et `doctrine/mongodb-odm-bundle` 5.6 ; commande `app:mongodb:check` pour tester la connexion                                                                                                                                   | Versions compatibles avec Symfony 7.4 et PHP 8.5 ; test reproductible sans document métier avant J6                                                                                                                                                                                   |
| 2026-11-23 | Tailwind CSS 4 via `symfonycasts/tailwind-bundle` 1.0, charte dans le bloc `@theme` de `app.css`                                                                                                                                           | Pas de Node ; noms sémantiques (`primary`, `line`, `statut-*`) pour changer la charte à un seul endroit                                                                                                                                                                               |
| 2026-11-23 | Police Inter déclarée sans être téléchargée (repli sur la police système)                                                                                                                                                                  | Éco-conception et RGPD : aucune requête vers un service de polices externe                                                                                                                                                                                                            |
| 2026-11-23 | Suivi des tâches sur un tableau Trello partagé                                                                                                                                                                                             | Outil collaboratif adapté au travail en binôme et à une méthode agile légère (critère « outils collaboratifs choisis en fonction de la méthode de développement »)                                                                                                                    |
| 2026-11-23 | Tâches de J2 en tickets GitHub (#1 à #9) dans un GitHub Project, une branche et une PR par bloc                                                                                                                                            | Tickets fermés automatiquement par les PR (`Closes #n`) : le suivi reste lié au code                                                                                                                                                                                                  |
| 2026-11-23 | MCD en Merise avec Mocodo (source texte `docs/conception/mcd.mcd`)                                                                                                                                                                         | Versionné dans Git, régénérable, et génère aussi le MLD et le SQL pour le MPD                                                                                                                                                                                                         |
| 2026-11-23 | RESERVATION modélisée comme une entité, pas comme une association porteuse                                                                                                                                                                 | Un adhérent peut réserver plusieurs fois le même objet : il faut un identifiant propre                                                                                                                                                                                                |
| 2026-11-23 | Pas d'entité PRÊT : sortie et retour portés par RESERVATION (`sortie_le`, `retour_le`, `etat_retour`)                                                                                                                                      | Relation 1-1 avec la réservation ; évite une jointure et garde le cycle de vie au même endroit                                                                                                                                                                                        |
| 2026-11-23 | Rôles stockés dans `UTILISATEUR.roles`, pas d'entité RÔLE                                                                                                                                                                                  | 3 rôles fixes et hiérarchisés, convention Symfony Security                                                                                                                                                                                                                            |
| 2026-11-23 | Disponibilité d'un objet calculée, jamais stockée                                                                                                                                                                                          | Évite une donnée dérivée incohérente avec les réservations                                                                                                                                                                                                                            |
| 2026-11-23 | RG2 garantie par une contrainte `EXCLUDE USING gist` (bornes incluses) en plus du service                                                                                                                                                  | Défense en profondeur : même deux requêtes simultanées ne peuvent pas créer de chevauchement                                                                                                                                                                                          |
| 2026-11-23 | Cycle de vie de la réservation verrouillé par des `CHECK` (statut cohérent avec sortie et retour)                                                                                                                                          | Une donnée incohérente est refusée par la base, quel que soit le code qui l'écrit                                                                                                                                                                                                     |
| 2026-11-23 | RG1 « pas dans le passé », RG3 et RG4 laissées au service                                                                                                                                                                                  | Dépendent de la date du jour, d'un paramètre métier ou de plusieurs lignes : hors de portée d'un `CHECK`                                                                                                                                                                              |
| 2026-11-23 | Valeurs contrôlées en `VARCHAR` + `CHECK` plutôt qu'en `ENUM` PostgreSQL                                                                                                                                                                   | Mapping des enums PHP par Doctrine ; ajout de valeur par simple migration                                                                                                                                                                                                             |
| 2026-11-23 | Rôle PostgreSQL `pretoutil_app` aux droits minimaux, `DELETE` seulement sur `objet`                                                                                                                                                        | Critère « intégrité, sécurité et confidentialité des données »                                                                                                                                                                                                                        |
| 2026-11-23 | Validateur `$jsonSchema` strict sur `audit_log`, rôle MongoDB en lecture et insertion seules                                                                                                                                               | Journal fiable et non modifiable par l'application                                                                                                                                                                                                                                    |
| 2026-11-23 | Index TTL d'un an sur `audit_log`                                                                                                                                                                                                          | RGPD : durée de conservation des journaux appliquée automatiquement                                                                                                                                                                                                                   |
| 2026-11-23 | Fixtures sans `id` explicite                                                                                                                                                                                                               | Écart du jeu d'essai : des `id` forcés désynchronisent les séquences d'identité                                                                                                                                                                                                       |
| 2026-11-23 | Identifiants en français sans accent ; termes du framework en anglais                                                                                                                                                                      | Vocabulaire métier lisible par tous, sans les pièges des accents dans les requêtes et les outils                                                                                                                                                                                      |
| 2026-11-23 | Contraintes nommées `pk_`, `fk_`, `uq_`, `ck_`, `ex_`, `idx_` dans les scripts ; noms Doctrine automatiques conservés                                                                                                                      | Messages d'erreur lisibles et rattachables à une règle ; pas de lutte contre les conventions de Doctrine                                                                                                                                                                              |
| 2026-11-23 | Suppression d'un compte par anonymisation, aucune adresse IP stockée                                                                                                                                                                       | RGPD : minimisation des données, historique des prêts conservé sans donnée personnelle                                                                                                                                                                                                |
| 2026-11-23 | Réservation : refus signalés par des exceptions métier, fragments `break` dans le diagramme de séquence                                                                                                                                    | Le contrôleur traduit le refus en message sans connaître la règle ; un refus termine le scénario                                                                                                                                                                                      |
| 2026-11-23 | RG4 vérifiée sous verrou `SELECT ... FOR UPDATE` sur l'adhérent                                                                                                                                                                            | Deux demandes simultanées du même adhérent ne peuvent pas dépasser le quota de 3                                                                                                                                                                                                      |
| 2026-11-23 | Journalisation d'une réservation après le `COMMIT`, sans bloquer si MongoDB est indisponible                                                                                                                                               | On ne journalise que ce qui a eu lieu ; le journal ne doit pas empêcher le service rendu                                                                                                                                                                                              |
| 2026-11-23 | Schéma d'architecture unique : cinq couches logicielles dans le conteneur `php`, une base par conteneur                                                                                                                                    | Montre sur une seule page le découpage logique, la répartition physique et la sécurité de chaque couche                                                                                                                                                                               |
| 2026-11-23 | Seul le conteneur `php` exposé hors développement ; bases joignables par le réseau Docker uniquement                                                                                                                                       | Réduit la surface d'attaque : aucune base accessible depuis l'extérieur                                                                                                                                                                                                               |
| 2026-11-23 | Scripts de conception alignés sur PostgreSQL 16, jeu d'essai rejoué (21/21)                                                                                                                                                                | Même version que l'environnement Docker : ce qui est testé est ce qui tourne                                                                                                                                                                                                          |
| 2026-11-23 | Installation vérifiée depuis un clone neuf, sous un autre nom de projet Docker                                                                                                                                                             | Prouve que la section Installation suffit ; les volumes de développement existants ne sont pas touchés                                                                                                                                                                                |
| 2026-11-23 | Notes pour le dossier tenues par étape dans `docs/dossier/`                                                                                                                                                                                | Preuves, visuels et réponses aux questions du jury prêts pour la rédaction de J9-J10                                                                                                                                                                                                  |
| 2026-11-23 | J3 en 8 tickets GitHub (#17 à #24) : modèle de données et sauvegarde pour Anthony, sécurité et comptes de base de données pour Chloé, relecture croisée ensemble                                                                           | Chacun prolonge ce qu'il a conçu ou installé à J2 ; la relecture croisée prépare l'entretien individuel                                                                                                                                                                               |
| 2026-11-23 | `symfony/security-bundle` installé avec l'entité `Utilisateur` (J3-A1), pare-feu configuré dans J3-B1                                                                                                                                      | L'entité a besoin de `UserInterface` ; Chloé peut commencer la sécurité dès que `Utilisateur` est poussée                                                                                                                                                                             |
| 2026-11-23 | Entités Doctrine sans relations inverses (`ManyToOne` seulement)                                                                                                                                                                           | Aucun écran n'en a besoin dans le MVP ; évite des chargements de collections inutiles                                                                                                                                                                                                 |
| 2026-11-23 | Sortie et retour enregistrés par `Reservation::enregistrerSortie()` et `enregistrerRetour()`, sans setters séparés                                                                                                                         | Les champs liés sont toujours renseignés ensemble, comme l'exigent `ck_reservation_sortie` et `ck_reservation_retour` ; les règles restent dans le service                                                                                                                            |
| 2026-11-23 | Enums PHP `StatutReservation` et `EtatObjet` mappés sur des colonnes `VARCHAR(20)`                                                                                                                                                         | Valeurs identiques à la base ; `StatutReservation::estActive()` définit une seule fois ce qu'est une réservation active (RG2, RG4)                                                                                                                                                    |
| 2026-11-23 | Index Doctrine `IDX_...` sur `reservation.utilisateur_id` conservé à côté de `idx_reservation_utilisateur_statut`                                                                                                                          | Doctrine indexe chaque clé étrangère ; le supprimer créerait un écart à chaque génération de migration, pour un gain négligeable                                                                                                                                                      |
| 2026-11-23 | Contraintes `CHECK` et `EXCLUDE` dans une migration écrite à la main, séparée de la migration générée                                                                                                                                      | La migration générée reste régénérable ; les contraintes métier sont relisibles en un seul endroit                                                                                                                                                                                    |
| 2026-11-23 | Écouteur Doctrine `IndexChevauchementListener` qui déclare l'index de la contrainte `EXCLUDE`                                                                                                                                              | Sans lui, chaque génération de migration proposait de supprimer la contrainte RG2 ; schéma créé uniquement par les migrations                                                                                                                                                         |
| 2026-11-23 | Fixtures découpées par entité, dates des réservations relatives au jour du chargement                                                                                                                                                      | Une classe par entité reste lisible ; les écrans montrent toujours des réservations à venir, en cours et en retard                                                                                                                                                                    |
| 2026-11-23 | Comptes de test sur le domaine réservé `.test`, mot de passe commun documenté dans le README                                                                                                                                               | Aucun risque d'écrire à une vraie adresse ; mot de passe réservé au développement, chaque compte illustre un cas (quota, désactivé, rôles)                                                                                                                                            |
| 2026-11-23 | Authentification par `form_login` et fournisseur d'entité sur `Utilisateur.email`                                                                                                                                                          | Mécanisme natif de Symfony : hachage, session, CSRF et `login_throttling` sans code d'authentification maison                                                                                                                                                                         |
| 2026-11-23 | `UtilisateurChecker` en `checkPostAuth` plutôt qu'en `checkPreAuth`                                                                                                                                                                        | Le message « compte désactivé » n'apparaît qu'avec le bon mot de passe : on ne révèle pas l'existence d'un compte à partir du seul email                                                                                                                                              |
| 2026-11-23 | `login_throttling` : 5 essais par email et adresse IP sur 15 minutes                                                                                                                                                                       | US2 ; le 6e essai est bloqué même avec le bon mot de passe                                                                                                                                                                                                                            |
| 2026-11-23 | Lien de déconnexion protégé par un jeton CSRF                                                                                                                                                                                              | Un site tiers ne peut pas déconnecter un utilisateur à son insu                                                                                                                                                                                                                       |
| 2026-11-23 | Pages provisoires `/mes-reservations`, `/gestion` et `/admin` (`EspaceController`)                                                                                                                                                         | Tester l'`access_control` des trois rôles dès J3, avant les écrans E5, E6 et E8                                                                                                                                                                                                       |
| 2026-11-23 | Inscription : formulaire Symfony sur un DTO (`InscriptionDto`), création dans `InscriptionService`                                                                                                                                         | L'entité n'est jamais dans un état invalide ; aucune logique métier dans le contrôleur                                                                                                                                                                                                |
| 2026-11-23 | Contrainte `EmailDisponible` sur le DTO, et contrainte d'unicité en base interceptée par le service                                                                                                                                        | `UniqueEntity` ne s'applique qu'aux entités ; la base reste la garantie finale si deux inscriptions arrivent en même temps                                                                                                                                                            |
| 2026-11-23 | Rôles d'un nouvel adhérent stockés vides (`[]`)                                                                                                                                                                                            | `getRoles()` ajoute toujours `ROLE_USER` ; même convention que les fixtures                                                                                                                                                                                                           |
| 2026-11-23 | `AuditLogger::journaliser()` appelé dès J3, sans écriture jusqu'à J6                                                                                                                                                                       | Les services métier n'auront pas à être modifiés quand le journal MongoDB sera branché                                                                                                                                                                                                |
| 2026-11-23 | Jetons CSRF stockés en session, fichier `csrf.yaml` de la recette `symfony/form` supprimé                                                                                                                                                  | Les jetons sans état de la recette demandent un contrôleur JavaScript (Stimulus) absent du projet ; la session est plus simple à expliquer                                                                                                                                            |
| 2026-11-23 | Thème de formulaire commun `templates/form/theme.html.twig`                                                                                                                                                                                | Champs, aides et erreurs aux couleurs de la maquette pour tous les formulaires (E2, puis E4 et E7)                                                                                                                                                                                    |
| 2026-11-23 | Deux connexions Doctrine : `default` (`pretoutil_app`) pour l'application, `proprietaire` pour les migrations et les fixtures                                                                                                              | Droits minimaux pour le code applicatif ; les migrations restent automatiques au démarrage du conteneur                                                                                                                                                                               |
| 2026-11-23 | Rôle `pretoutil_app` créé par un script d'initialisation Docker, droits posés par une migration                                                                                                                                            | Le mot de passe reste hors du dépôt ; les `GRANT` sont versionnés avec le schéma qu'ils protègent                                                                                                                                                                                     |
| 2026-11-23 | `audit_log.mongodb.js` monté tel quel comme script d'initialisation MongoDB                                                                                                                                                                | Une seule source pour le validateur, les index et le rôle `auditAppendOnly`                                                                                                                                                                                                           |
| 2026-11-23 | Mots de passe dans `.env.local`, transmis aux conteneurs par `env_file`                                                                                                                                                                    | Docker Compose ne lit pas `.env.local` pour ses variables ; aucun mot de passe dans `.env` ni `compose.yaml`                                                                                                                                                                          |
| 2026-11-23 | `app:mongodb:check` vérifie un refus d'écriture au lieu d'écrire un document de test                                                                                                                                                       | Le compte applicatif ne peut écrire que dans `audit_log`, en ajout seul : la commande démontre les droits minimaux                                                                                                                                                                    |
| 2026-11-23 | Sauvegarde par `pg_dump` au format custom et `mongodump` en archive compressée, avec empreintes SHA-256                                                                                                                                    | Formats natifs restaurables par les outils standard ; une sauvegarde altérée est détectée avant de remplacer les données                                                                                                                                                              |
| 2026-11-23 | Rôles et utilisateurs applicatifs exclus des sauvegardes                                                                                                                                                                                   | Ils sont recréés par les scripts d'initialisation de `docker/` ; aucune empreinte de mot de passe dans les fichiers de sauvegarde                                                                                                                                                     |
| 2026-11-23 | Sauvegardes hors dépôt, fichiers en lecture pour le seul utilisateur, conservation 30 jours                                                                                                                                                | Elles contiennent des données personnelles (RGPD)                                                                                                                                                                                                                                     |
| 2026-11-23 | J4 en 5 tickets GitHub (#33 à #37) : catalogue (requêtes et écrans E3, E4) pour Anthony, Voters et gestion du catalogue (E7) pour Chloé, relecture croisée ensemble                                                                        | Chacun prolonge son domaine de J3 (données, sécurité) ; la relecture croisée prépare l'entretien individuel                                                                                                                                                                           |
| 2026-11-23 | Voters faits avant l'écran E7 et testés unitairement                                                                                                                                                                                       | E7 s'appuie sur `ObjetVoter` pour autoriser la suppression ; critère O5 « contrôle d'accès testé »                                                                                                                                                                                    |
| 2026-11-23 | Ports fixes 5433 (PostgreSQL) et 27018 (MongoDB), limités à `127.0.0.1`                                                                                                                                                                    | Connexion stable depuis pgAdmin et Compass ; 5432 et 27017 sont souvent pris par des serveurs installés localement ; bases non exposées au réseau local                                                                                                                               |
| 2026-11-24 | Filtre de disponibilité du catalogue en `NOT EXISTS` avec bornes incluses, objets hors service exclus dès qu'une période est demandée                                                                                                      | Même règle que la contrainte `EXCLUDE` (RG2) et RG5 : le catalogue n'annonce disponible que ce qui est réservable                                                                                                                                                                     |
| 2026-11-24 | Index partiel `idx_reservation_dates_actives` sur les dates des réservations actives                                                                                                                                                       | `EXPLAIN` sur 200 000 réservations : lecture complète de la table sans lui (25 ms), 7 ms avec ; éco-conception « requêtes indexées »                                                                                                                                                  |
| 2026-11-24 | Repositories qui renvoient un objet `Page` plutôt que le `Paginator` de Doctrine                                                                                                                                                           | Les contrôleurs et les vues ne dépendent pas de l'ORM (séparation des couches) ; `Page` sert aussi aux écrans E7 et E9                                                                                                                                                                |
| 2026-11-24 | Catalogue sur `/catalogue` (E3) et détail sur `/catalogue/{id}` (E4), page d'accueil conservée                                                                                                                                             | L'accueil présente l'association et mène au catalogue ; les URL du catalogue restent stables                                                                                                                                                                                          |
| 2026-11-24 | `CatalogueService` entre les contrôleurs et les repositories                                                                                                                                                                               | Les contrôleurs ne font que lire la requête et rendre la vue ; le calcul du statut du jour est à un seul endroit                                                                                                                                                                      |
| 2026-11-24 | Badge « statut du jour » calculé (hors service, sorti, réservé, disponible), jamais stocké, en une requête par page                                                                                                                        | Donnée dérivée des réservations ; un retour en retard s'affiche « Sorti » ; pas de requête par objet                                                                                                                                                                                  |
| 2026-11-24 | Filtres du catalogue en GET sans jeton CSRF ; filtres invalides : erreurs affichées, code 422, catalogue complet                                                                                                                           | Une recherche ne modifie rien et son URL se partage ; un filtre incohérent n'est jamais ignoré silencieusement                                                                                                                                                                        |
| 2026-11-24 | Contrôle d'accès métier par Voters (`ObjetVoter`, `ReservationVoter`), attributs en constantes                                                                                                                                             | Les règles qui dépendent de l'objet (propriétaire, objet déjà réservé, date) ne s'expriment pas par chemin dans `access_control` ; un seul endroit par règle, testable                                                                                                                |
| 2026-11-24 | Rôle requis vérifié par `AccessDecisionManager` dans les Voters, pas par `in_array` sur les rôles                                                                                                                                          | Respecte `role_hierarchy` : un administrateur a les droits d'un gestionnaire sans les répéter                                                                                                                                                                                         |
| 2026-11-24 | `RESERVATION_ANNULER` limité aux réservations confirmées                                                                                                                                                                                   | Cycle de vie : une réservation en cours, terminée ou annulée ne peut plus être annulée                                                                                                                                                                                                |
| 2026-11-24 | Date du jour fournie par l'horloge Symfony (`ClockInterface`), comparée en jours calendaires                                                                                                                                               | RG6 testable à la journée près ; comparer `AAAA-MM-JJ` évite un décalage d'un jour lié aux fuseaux                                                                                                                                                                                    |
| 2026-11-24 | PHPUnit via `symfony/test-pack` dès J4                                                                                                                                                                                                     | Critère O5 (contrôle d'accès testé) ; même outillage pour les tests fonctionnels de J7                                                                                                                                                                                                |
| 2026-11-24 | Écran E7 sous `/gestion/catalogue`, protégé par `access_control` et par `ObjetVoter` sur chaque action                                                                                                                                     | Défense en profondeur : une erreur de configuration d'un côté ne suffit pas à ouvrir la gestion du catalogue                                                                                                                                                                          |
| 2026-11-24 | Formulaire d'objet sur un DTO (`ObjetDto`), comme l'inscription                                                                                                                                                                            | L'entité n'est jamais dans un état invalide ; contraintes du dictionnaire de données au même endroit                                                                                                                                                                                  |
| 2026-11-24 | Longueur maximale de 2000 caractères pour la description et 5000 pour les caractéristiques                                                                                                                                                 | Colonnes `TEXT` sans limite en base : une borne côté application évite les saisies abusives                                                                                                                                                                                           |
| 2026-11-24 | Désactivation et suppression en POST avec un jeton CSRF par action et par objet                                                                                                                                                            | Un lien ou un formulaire tiers ne peut pas retirer un objet ; un jeton ne sert qu'à l'action prévue                                                                                                                                                                                   |
| 2026-11-24 | Objets déjà réservés d'une page calculés en une requête (`objetsDejaReserves`)                                                                                                                                                             | Évite une requête par ligne pour afficher le bouton « Supprimer » (éco-conception) ; la suppression reste contrôlée par le Voter                                                                                                                                                      |
| 2026-11-24 | Suppression non journalisée, pas de réactivation d'un objet retiré                                                                                                                                                                         | Hors périmètre du MVP : l'énumération des actions de `audit_log` ne prévoit ni `OBJET_SUPPRIME` ni réactivation ; améliorations possibles                                                                                                                                             |
| 2026-11-24 | Parcours complet de fin de journée fait par requêtes HTTP, rôle par rôle, sur les fixtures                                                                                                                                                 | Reproductible et rapide ; le rendu visuel reste vérifié à la main, car le certificat local bloque les navigateurs automatisés                                                                                                                                                         |
| 2026-11-24 | Remarques de relecture du bloc B (journalisation de la suppression, objet désactivé avec réservations à venir, réactivation) reportées à J5 et J6                                                                                          | Elles touchent `AuditLogger` et `ReservationService`, réalisés ces jours-là ; voir les notes J4, section 6                                                                                                                                                                            |
| 2026-11-24 | J5 en 4 tickets GitHub (#44 à #47) : `ReservationService` pour Anthony, tests du service et formulaire de réservation pour Chloé, relecture croisée ensemble                                                                               | Le service suit le diagramme de séquence conçu par Anthony ; tester le code de l'autre oblige chacun à le comprendre, ce qui prépare l'entretien individuel                                                                                                                           |
| 2026-11-24 | Signature de `reserver()` et exceptions métier poussées en premier                                                                                                                                                                         | Le formulaire de réservation (#46) peut avancer en parallèle du service                                                                                                                                                                                                               |
| 2026-11-24 | Règle du retour en retard tranchée ensemble au début de J5 (#44)                                                                                                                                                                           | Elle s'applique à la fois au service et au filtre du catalogue                                                                                                                                                                                                                        |
| 2026-11-24 | Ajout d'une API REST interne en lecture seule (disponibilités d'un objet, US11), appelée par l'écran E4                                                                                                                                    | Consigne de l'épreuve signalée par le binôme ; le REAC cite « utiliser un service distant (API REST) ». API interne plutôt qu'externe : elle sert le métier existant sans dépendre d'un tiers. Complète la décision du 2026-11-23 (rendu côté serveur, pas de SPA), qui reste valable |
| 2026-11-24 | API construite avec un contrôleur Symfony et `JsonResponse`, sans API Platform                                                                                                                                                             | Un seul point d'accès en lecture : réutiliser les services existants suffit, sans nouvelle dépendance lourde                                                                                                                                                                          |
| 2026-11-24 | API placée à J6, avec l'écran E5                                                                                                                                                                                                           | Ne pas charger J5, déjà dense (service de réservation et formulaire)                                                                                                                                                                                                                  |
| 2026-11-24 | Un objet dont le retour est en retard n'est pas réservable, et le catalogue ne l'annonce disponible sur aucune période                                                                                                                     | Décision du binôme : l'objet n'est physiquement pas là ; même règle dans `ReservationService` et dans le filtre du catalogue                                                                                                                                                          |
| 2026-11-24 | Une exception métier par règle, sous une classe mère `ReservationRefuseeException` portant le message affiché                                                                                                                              | Le contrôleur affiche le refus sans connaître la règle ; les tests distinguent chaque règle                                                                                                                                                                                           |
| 2026-11-24 | Transaction ouverte sur la connexion plutôt que `wrapInTransaction()`                                                                                                                                                                      | `wrapInTransaction()` ferme l'EntityManager à toute exception, y compris un refus métier, ce qui empêcherait d'afficher l'écran E4                                                                                                                                                    |
| 2026-11-24 | Index partiel `idx_reservation_en_cours` et sous-requête non corrélée pour les retours en retard                                                                                                                                           | Mesures `EXPLAIN` : 16 ms avec un `OR`, 83 ms avec un second `NOT EXISTS`, 4,6 ms stable avec l'index ; il servira aussi à l'écran E6                                                                                                                                                 |
| 2026-11-24 | `VACUUM ANALYZE` avant et après le script de mesure `EXPLAIN`                                                                                                                                                                              | Ses insertions annulées laissaient 1,4 million de lignes mortes qui faussaient les plans                                                                                                                                                                                              |
| 2026-11-24 | Base de test `pretoutil_test` créée par les migrations, avec les mêmes comptes qu'en développement                                                                                                                                         | Les tests d'intégration vérifient le vrai comportement de PostgreSQL (contrainte `EXCLUDE`, droits de `pretoutil_app`)                                                                                                                                                                |
| 2026-11-24 | Tests d'intégration dans une transaction annulée, sans bundle supplémentaire                                                                                                                                                               | Base toujours vide entre deux tests ; le service ouvre sa transaction en point de sauvegarde (`SAVEPOINT`) de celle du test                                                                                                                                                           |
| 2026-11-24 | Conflit concurrent simulé par un repository qui ne voit pas le chevauchement                                                                                                                                                               | Seule façon reproductible d'atteindre la contrainte `EXCLUDE` (23P01) sans deux processus en parallèle                                                                                                                                                                                |
| 2026-11-24 | Tableau RG → tests et vérification par erreurs volontaires ([correspondance-rg-tests.md](docs/tests/correspondance-rg-tests.md))                                                                                                           | Critère O5 démontrable : chaque règle a un test qui échoue quand on la casse                                                                                                                                                                                                          |
| 2026-11-24 | Réservation en POST sur `/catalogue/{id}/reserver` ; en cas de refus, la fiche est réaffichée (422) au lieu d'une redirection                                                                                                              | Le message et les dates saisies restent à l'écran ; 303 vers E5 seulement après un succès, pour éviter un double envoi                                                                                                                                                                |
| 2026-11-24 | Le contrôleur attrape `ReservationRefuseeException` (classe parente) et affiche son message                                                                                                                                                | Aucune règle dans le contrôleur ; une nouvelle règle n'exige aucune modification de l'écran                                                                                                                                                                                           |
| 2026-11-24 | RG1 vérifiée aussi par le formulaire (`ReservationDto`), en plus du service                                                                                                                                                                | Erreur affichée sous le bon champ ; le service reste la garantie pour tous les appels                                                                                                                                                                                                 |
| 2026-11-24 | Base `pretoutil_test` à créer une fois par poste, et à migrer en environnement de test après chaque nouvelle migration                                                                                                                     | Les tests d'intégration échouent sans elle ; procédure du README vérifiée sur un second poste                                                                                                                                                                                         |
| 2026-11-24 | Parcours de réservation de fin de journée fait par requêtes HTTP (17 contrôles), fixtures rechargées ensuite                                                                                                                               | Chaque refus vérifié avec son message et son code ; le rendu visuel reste vérifié à la main                                                                                                                                                                                           |
| 2026-11-24 | J6 en 5 tickets GitHub (#49, #54 à #57) : écran E5 et annulation, API des disponibilités pour Anthony ; sorties et retours (E6), journal d'audit MongoDB et écran E9 pour Chloé ; relecture croisée ensemble                               | Anthony prolonge E4 et la réservation ; Chloé prolonge la gestion (E7) et MongoDB qu'elle a installé ; la relecture croisée couvre la partie NoSQL pour les deux                                                                                                                      |
| 2026-11-24 | Remarques de relecture de J4 intégrées à J6 : action `OBJET_SUPPRIME` (#56), réservations à venir signalées lors d'un retour hors service (#55)                                                                                            | Elles dépendaient de l'`AuditLogger` réel et des retours, réalisés à J6 ; la réactivation d'un objet reste hors MVP                                                                                                                                                                   |
| 2026-11-24 | US9 (gestion des comptes, écran E8, « Should ») non planifiée à J6                                                                                                                                                                         | Les « Must » passent d'abord ; à placer en J8 ou dans la marge si le temps le permet                                                                                                                                                                                                  |
| 2026-11-24 | Écran E5 dans un contrôleur dédié (`MesReservationsController`), page provisoire retirée de `EspaceController`                                                                                                                             | Une responsabilité par contrôleur ; évite un conflit avec le remplacement de `/gestion` par l'écran E6 (#55)                                                                                                                                                                          |
| 2026-11-24 | Bouton « Annuler » affiché selon `ReservationVoter::ANNULER`, action protégée par le même Voter et un jeton CSRF propre à chaque réservation                                                                                               | La règle RG6 n'est écrite qu'à un endroit ; un jeton ne peut pas servir à annuler une autre réservation                                                                                                                                                                               |
| 2026-11-24 | Liste E5 non paginée                                                                                                                                                                                                                       | Un adhérent a au plus 3 réservations actives et peu d'historique dans le MVP ; une requête avec jointure suffit (3 requêtes par page)                                                                                                                                                 |
| 2026-11-24 | Écran E6 sous `/gestion/sorties-retours` (`SortiesRetoursController`), page provisoire `/gestion` retirée                                                                                                                                  | Une responsabilité par contrôleur, comme E5 ; l'`access_control` de `/gestion` s'applique toujours                                                                                                                                                                                    |
| 2026-11-24 | Sortie et retour dans une transaction, réservation relue sous verrou (`refresh` en `PESSIMISTIC_WRITE`)                                                                                                                                    | Deux gestionnaires qui cliquent en même temps : le second voit le statut déjà modifié et reçoit un refus clair                                                                                                                                                                        |
| 2026-11-24 | Sortie refusée si l'objet est encore sorti par l'emprunteur précédent ou hors service                                                                                                                                                      | Remettre un objet absent ou inutilisable n'a pas de sens ; complète le contrôle « retour en retard » de la réservation                                                                                                                                                                |
| 2026-11-24 | L'objet prend l'état constaté au retour, y compris « bon » après « abîmé »                                                                                                                                                                 | Le gestionnaire constate l'état réel ; un objet réparé redevient « bon »                                                                                                                                                                                                              |
| 2026-11-24 | RG7 : réservations confirmées d'un objet devenu hors service signalées, pas annulées                                                                                                                                                       | Le gestionnaire prévient l'adhérent et décide (réparation rapide, autre objet) ; une annulation automatique serait irréversible                                                                                                                                                       |
| 2026-11-24 | Tri des retours sur `date_fin` seule                                                                                                                                                                                                       | Permet à PostgreSQL d'utiliser l'ordre de l'index partiel `idx_reservation_en_cours`                                                                                                                                                                                                  |
| 2026-11-24 | Disponibilité calculée par `ReservationService::verifierDisponibilite()`, qui réutilise les vérifications de `reserver()` hors quota                                                                                                       | Une seule écriture des règles ; l'API informe, la réservation revérifie tout sous verrou ; le quota dépend de l'adhérent et l'API est publique                                                                                                                                        |
| 2026-11-24 | Erreurs de l'API au format JSON par un écouteur d'exceptions limité aux chemins `/api/`, messages génériques sauf erreurs de saisie                                                                                                        | Même format pour 400, 404, 405, 429 et 500 ; rien du fonctionnement interne n'est révélé                                                                                                                                                                                              |
| 2026-11-24 | Appel de l'API depuis E4 en amélioration progressive, zone `aria-live` toujours présente, message inséré avec `textContent`                                                                                                                | Le formulaire marche sans JavaScript ; les lecteurs d'écran annoncent la réponse ; aucun risque d'injection HTML                                                                                                                                                                      |
| 2026-11-24 | US11 servie aux adhérents sur E4 ; les visiteurs vérifient une période avec le filtre du catalogue                                                                                                                                         | Un visiteur n'a pas de formulaire de réservation sur E4 ; le filtre de E3 applique déjà les mêmes règles                                                                                                                                                                              |
| 2026-11-24 | Compteurs de la limitation de débit dans un cache sur disque vidé au début de chaque test                                                                                                                                                  | Symfony vide les services en mémoire entre deux requêtes d'un test : le test du 429 ne pouvait pas passer avec un cache en mémoire                                                                                                                                                    |
| 2026-11-24 | Document ODM `AuditLog` en lecture seule (`readOnly`), `acteur` et `cible` en documents embarqués, `details` en `hash`                                                                                                                     | Calqué sur le validateur `$jsonSchema` ; le type `hash` écrit toujours un objet BSON, que le validateur exige même vide                                                                                                                                                               |
| 2026-11-24 | `AuditLogger` n'interrompt jamais l'action métier : erreur MongoDB tracée dans les logs                                                                                                                                                    | L'action est déjà validée dans PostgreSQL ; le journal ne doit pas rendre l'application indisponible                                                                                                                                                                                  |
| 2026-11-24 | `CONNEXION_ECHOUEE` sans identifiant, motif réduit à « échec » ou « blocage », sans adresse IP                                                                                                                                             | Le journal ne révèle pas quels emails ont un compte ; minimisation des données (RGPD)                                                                                                                                                                                                 |
| 2026-11-24 | `audit_log.mongodb.js` rejouable (`collMod` si la collection existe)                                                                                                                                                                       | Une seule source pour le validateur ; ajouter une action (`OBJET_SUPPRIME`) ne demande pas de script de migration séparé                                                                                                                                                              |
| 2026-11-24 | Base MongoDB de test `pretoutil_test`, créée par le même script, mêmes droits                                                                                                                                                              | Les tests vérifient le vrai validateur et les vrais droits ; documents de test non supprimables, retrouvés par marqueur                                                                                                                                                               |
| 2026-11-24 | Filtre de E9 converti en `ActionAudit` avant la requête                                                                                                                                                                                    | Protection contre l'injection NoSQL : `?action[$ne]=x` est refusé (404), une valeur inconnue est ignorée                                                                                                                                                                              |
| 2026-11-24 | La suppression physique d'un objet est journalisée (`OBJET_SUPPRIME`)                                                                                                                                                                      | Remplace la décision « suppression non journalisée » prise à J4 : RG8 couvre toute action sensible ; validateur MongoDB mis à jour par `collMod`                                                                                                                                      |
| 2026-11-24 | Parcours de fin de J6 sur tout le cycle de vie, avec contrôle sur E9 d'un document par action (21 contrôles)                                                                                                                               | Prouve RG8 de bout en bout : chaque action sensible produit exactement une entrée du journal                                                                                                                                                                                          |
| 2026-11-24 | J7 en 5 tickets GitHub (#64 à #68) : plan de tests, contrôle d'accès de chaque route et jeu d'essai « Réserver un objet » pour Anthony ; tests de sécurité (CSRF, injections, XSS) et veille OWASP pour Chloé ; relecture croisée ensemble | RG1 à RG6 déjà couvertes à J5 et J6 : J7 complète O5 par les routes et démontre les protections par des tests ; Anthony rejoue la réservation qu'il a écrite, Chloé teste l'injection NoSQL sur E9 qu'elle a réalisé                                                                  |
| 2026-11-24 | Contrôle d'accès testé par une matrice routes × profils, qui échoue si une route n'y figure pas                                                                                                                                            | Une route ajoutée plus tard ne peut pas échapper au critère O5                                                                                                                                                                                                                        |
| 2026-11-24 | Requêtes en POST de la matrice d'accès envoyées sans jeton CSRF                                                                                                                                                                            | Le test vérifie qui passe le contrôle d'accès, sans exécuter l'action ; le CSRF a ses propres tests (#66)                                                                                                                                                                             |
| 2026-11-24 | Compteurs de limitation (API et tentatives de connexion) remis à zéro au début des tests qui les utilisent                                                                                                                                 | Stockés sur disque en test, ils faisaient échouer le lancement suivant ou le test suivant                                                                                                                                                                                             |
| 2026-11-24 | Jeu d'essai de la réservation rejoué par un script HTTP versionné, avec un verdict par cas (code HTTP et document du journal)                                                                                                              | Résultats reproductibles par le jury ou le binôme ; 20 cas conformes le 2026-11-24                                                                                                                                                                                                    |
| 2026-11-24 | Accès simultanés du jeu d'essai renvoyés aux tests automatisés                                                                                                                                                                             | Par HTTP, on ne peut pas forcer deux transactions à se croiser ; les tests le simulent (contrainte `EXCLUDE`, verrou du quota)                                                                                                                                                        |
| 2026-11-24 | Chaque test de refus CSRF a un témoin : le même envoi avec le vrai jeton aboutit                                                                                                                                                           | Prouve que le refus vient du jeton, et non d'une donnée invalide ou d'un droit manquant                                                                                                                                                                                               |
| 2026-11-24 | Tests de sécurité validés en retirant chaque protection une à une (8 essais, code restauré ensuite)                                                                                                                                        | Critère de #66 : chaque test doit échouer si sa protection disparaît                                                                                                                                                                                                                  |
| 2026-11-24 | Veille de sécurité menée sur l'OWASP Top 10:2025 (édition en vigueur), avec une preuve par risque                                                                                                                                          | Relie chaque risque à une mesure du code et à un test ; écarts (en-têtes HTTP, hub Mercure inutilisé) proposés en relecture                                                                                                                                                           |
| 2026-11-24 | En-têtes de sécurité posés par Symfony (`EntetesSecuriteSubscriber`) plutôt que par Caddy                                                                                                                                                  | La CSP repose sur un nonce tiré à chaque requête, que seule l'application connaît ; les en-têtes sont ainsi testés avec les autres tests fonctionnels                                                                                                                                 |
| 2026-11-24 | Hub Mercure gardé en développement seulement (`frankenphp/dev.d/`)                                                                                                                                                                         | Le rechargement à chaud de FrankenPHP en dépend ; l'application ne l'utilise pas, la production n'expose donc plus de hub                                                                                                                                                             |
| 2026-11-24 | Confirmations des actions irréversibles en `data-confirmation` au lieu de `onsubmit`                                                                                                                                                       | La CSP n'exécute aucun JavaScript écrit dans le HTML                                                                                                                                                                                                                                  |
| 2026-11-24 | US9 (gestion des comptes, écran E8) placée au matin de J8, avant les finitions et le gel du code | Active les actions `COMPTE_DESACTIVE` et `ROLE_MODIFIE` du journal, prévues mais jamais écrites ; l'occasion d'ajouter `IsGranted('ROLE_ADMIN')` sur `/admin` |
| 2026-11-24 | Clôture de J7 : suite complète relancée sur une base de test recréée (257 tests, 794 assertions), audits des dépendances sans faille | Preuve que la suite ne dépend pas d'un état laissé par un lancement précédent |
| 2026-11-24 | J8 en 5 tickets GitHub (#76 à #80) : écran E8 (US9) le matin et accessibilité des écrans adhérent pour Anthony ; accessibilité et finitions des écrans de gestion, vérification dans le navigateur et captures pour Chloé ; relecture croisée et gel du code ensemble | E8 passe avant les finitions pour être relu et testé avant le gel ; chacun audite les écrans qu'il connaît le moins bien, Chloé fait la vérification visuelle que le certificat local empêche d'automatiser |
| 2026-11-24 | Accessibilité de base jugée sur une grille issue des critères essentiels du RGAA 4.1, consignée par écran | Objectif mesurable dans la journée ; un audit RGAA complet est hors de portée du MVP |
| 2026-11-24 | Gel du code marqué par le tag `v1.0.0` sur `main` | Version présentée au jury identifiable ; J9 et J10 ne touchent plus qu'à la documentation |
| 2026-11-24 | Réactivation d'un compte journalisée par une nouvelle action `COMPTE_REACTIVE`, validateur mis à jour par `collMod` | La tracer sous `COMPTE_DESACTIVE` rendrait le journal trompeur ; même démarche que `OBJET_SUPPRIME` à J6 |
| 2026-11-24 | Un administrateur n'agit pas sur son propre compte (ni désactivation, ni changement de rôle) | Seul un administrateur utilise E8 : l'association garde donc toujours un administrateur actif, sans compter les administrateurs |
| 2026-11-24 | Session fermée à la requête suivante quand le compte est désactivé ou change de rôle (`Utilisateur::isEqualTo()`) | Sans cela, un compte désactivé gardait l'accès jusqu'à la fin de sa session ; un rôle retiré cessait aussi d'agir tout de suite |
| 2026-11-24 | `/admin` renvoie vers E8, avec `IsGranted('ROLE_ADMIN')` en plus de `access_control` | Remplace l'emplacement provisoire de J3 ; remarque de relecture de J7 |
| 2026-11-24 | Couleur de charte `champ` (`slate-500`) pour le contour des champs, `line` gardée pour séparer les blocs | Le contour en `slate-200` (1,23:1) ne permettait pas de repérer un champ ; 3:1 exigé pour un élément d'interface (RGAA 3.3) |
| 2026-11-24 | Aide et erreurs reliées aux champs dans le thème de formulaire | Le thème remplace le `form_row` de Symfony, qui reliait l'aide : le lien s'était perdu sans qu'on le voie |
| 2026-11-24 | Critères d'accessibilité vérifiables testés automatiquement (`AccessibiliteTest`), clavier, lecteur d'écran et zoom vérifiés à la main | Une régression de structure est détectée à chaque lancement ; le ressenti d'un utilisateur ne peut pas être automatisé |
| 2026-11-24 | Composants des écrans de gestion (tableau, en-têtes, boutons, listes, pagination) définis une seule fois, dans la feuille de styles et une inclusion Twig | Les quatre écrans E6 à E9 gardent la même présentation, celle des maquettes E6 et E9 |
| 2026-11-24 | Tableaux larges défilant dans un cadre atteignable au clavier (`role="region"`, `tabindex="0"`) | Lisibles sur mobile sans faire défiler la page ; utilisables sans souris |
| 2026-11-24 | Autorité locale de Caddy ajoutée au trousseau de macOS pour les vérifications dans le navigateur | Les navigateurs automatisés refusent un certificat non reconnu ; la console, la CSP et les parcours ont ainsi été vérifiés dans un vrai navigateur (#79) |
| 2026-11-24 | `twig/twig` mis à jour de 3.30.0 en 3.31.1 avant le gel du code | 9 failles publiées les 23 et 24 novembre, relevées par `composer audit` pendant les captures (#79) ; mise à jour corrective, seul `composer.lock` change |
| 2026-11-24 | Dernière activité d'un compte enregistrée aussi à la connexion réussie (`ConnexionReussieListener`) | Conforme au dictionnaire de données : le délai de conservation RGPD part de la dernière connexion ou réservation, pas de la seule réservation |
| 2026-11-24 | Clôture de J8 : volumes des bases supprimés puis environnement relancé, suite lancée 3 fois (363 tests, 1 466 assertions à chaque fois), jeu d'essai rejoué (20 cas conformes), audits sans faille | Preuve que la version gelée s'installe et se vérifie depuis zéro ; le volume du certificat local est gardé pour ne pas avoir à refaire confiance à une nouvelle autorité |
| 2026-11-24 | Une seule assertion pour le filtre du journal dans `AuditLogIntegrationTest` | La base MongoDB de test est en ajout seul : une assertion par entrée faisait varier le nombre d'assertions d'un lancement à l'autre (écart relevé à J7) |
| 2026-11-24 | Un dossier de projet par candidat : tronc commun rédigé à deux (contexte, besoins, conception, architecture, sécurité, tests, gestion de projet), puis les réalisations de chacun | Le jury interroge chaque candidat seul ; chaque dossier montre ce que le candidat a fait et ce qu'il présente du travail de l'autre |
| 2026-11-24 | Dossiers rédigés en traitement de texte, hors du dépôt ; plan commun et répartition des réalisations versionnés (`docs/dossier/plan-dossier.md`), PDF finaux ajoutés au dépôt à J10 | Mise en page et pagination plus simples à maîtriser pour un document de 30 à 40 pages ; le plan reste relu par PR comme le reste |
| 2026-11-24 | J9 en 5 tickets GitHub (#88 à #92) : plan du dossier et tronc commun (contexte, besoins, conception) puis réalisations personnelles pour Anthony ; tronc commun (architecture, sécurité, tests, gestion de projet) puis réalisations personnelles pour Chloé ; relecture croisée des dossiers ensemble | Le plan commun passe en premier : il fixe le nombre de pages par partie et la couverture des 4 compétences par candidat, avant que chacun rédige |
| 2026-11-25 | Code rouvert une journée pour une charte graphique tirée du logo (couleurs, police, logo, favicon, composants de style), prise sur la marge ; nouveau gel marqué par le tag `v1.1.0`, `v1.0.0` conservé | Une identité visuelle soignée est attendue d'une application présentée au jury ; le style passe avant la mise en page des dossiers pour que leurs captures montrent la version finale |
| 2026-11-25 | Couleurs du logo classées par contraste calculé : bleu pétrole `#1b6e91` (5,7:1) en couleur principale, encre `#0f3b4a` (12:1) pour le texte, rouille `#c04822` (5,0:1) pour le danger ; orange `#f4a120` (2,1:1) et vert d'eau `#24948e` (3,7:1) réservés aux fonds et au décor | Garder le niveau d'accessibilité atteint à J8 : aucun texte sous 4,5:1 ; les noms sémantiques de `@theme` sont gardés, aucun gabarit ne change de classe |
| 2026-11-25 | Police servie depuis le dépôt par AssetMapper, sans Google Fonts | La CSP n'autorise que `'self'` ; aucune adresse IP de visiteur transmise à un tiers (RGPD) |
| 2026-11-25 | Style en 3 tickets GitHub (#94 à #96) : charte, logo et écrans adhérent pour Anthony le matin ; composants communs et écrans de gestion pour Chloé ensuite ; relecture croisée, captures refaites et tag ensemble le soir | La charte passe en premier car les composants en dépendent ; chacun garde les écrans qu'il a construits |
| 2026-11-25 | Logo et icône seule tirés du SVG fourni : fond blanc retiré, feuille de styles interne remplacée par des attributs `fill`, slogan rendu visible comme sur la version WEBP | Une feuille de styles interne pourrait être bloquée par une CSP ; l'icône seule sert au favicon, à l'en-tête et au pied de page |
| 2026-11-25 | `symfony/asset` ajouté | Fonction Twig `asset()` pour référencer logo, favicons et police par AssetMapper, avec un nom versionné qui permet une mise en cache longue |
| 2026-11-25 | Icône de l'en-tête décorative (`alt` vide), logo de l'accueil avec une alternative qui reprend son texte ; « Prêt' » et « Outil » écrits en bleu pétrole et en rouille | Le nom n'est pas lu deux fois par un lecteur d'écran (RGAA 1.2, 1.3) ; les deux couleurs du logo retenues dépassent 4,5:1 |
| 2026-11-25 | Photo des objets : colonne `objet.image` facultative (nom d'un fichier de `assets/images/objets/`), renseignée par les fixtures ; sans photo, vignette avec l'icône du logo | Un objet ajouté par un gestionnaire reste affichable sans photo ; l'envoi de photos depuis E7 (taille, type, stockage) est renvoyé à une évolution |
| 2026-11-25 | Contrainte `ck_objet_image` : nom simple en `.webp` seulement | Le nom est concaténé au chemin du dossier des photos : la base refuse tout chemin (`../`), même si une future saisie oubliait de le contrôler |
| 2026-11-25 | Photos recadrées en 4:3 et converties en WebP de 960 px (14 photos, moins de 1 Mo en tout, contre 75 Mo de sources) ; chargées à l'approche dans le catalogue, en priorité sur la fiche | Éco-conception et temps d'affichage |
| 2026-11-25 | Maquette Figma alignée sur la charte du logo : variables de couleur mises à jour (`primary`, `ink`) et ajoutées (`accent`, `accent-hover`, `accent-soft`, `secondaire`, `danger`), police Nunito, composant `Logo` dans la barre de navigation, zone photo 4:3 dans `CarteObjet`, photos sur E3 et E4 | Le dossier montre la même charte en maquette et dans l'application ; les noms des variables reprennent ceux du bloc `@theme` |
| 2026-11-25 | Composants de style définis une seule fois (utilitaires `@utility` de Tailwind et inclusions Twig), page de référence en développement seulement | Tous les écrans ont la même présentation ; la page sert de figure au dossier sans être exposée en production |
| 2026-11-25 | Animations courtes, jouées une seule fois, désactivées par `prefers-reduced-motion` | Interface plus vivante sans gêner : aucun mouvement en boucle (WCAG 2.2.2), mouvements supprimés à la demande (WCAG 2.3.3) |
| 2026-11-25 | Couleurs Tailwind brutes interdites dans les gabarits, vérifié par un test | Chaque couleur affichée vient de la charte, dont les contrastes sont calculés |
| 2026-11-25 | Vérification après le style faite dans Google Chrome sans interface, piloté par son protocole de débogage : contraste de chaque texte affiché, largeur des pages à 375 et 640 px, console, avec chaque profil ; scénario gardé hors du dépôt | Mesures sur la page réellement affichée, rejouables, au lieu d'un contrôle à l'œil ; pas d'outil de test de navigateur ajouté au MVP |
| 2026-11-25 | Composant `cadre-tableau` en `relative` | Les libellés masqués des listes de choix de E6 et E8 élargissaient la page sur mobile et au zoom à 200 % ; défaut présent depuis J8, trouvé par la mesure de la largeur |
| 2026-11-25 | Captures refaites sous les mêmes noms, plus l'accueil, le logo et la page des composants ; captures de terminal sans écran de l'application gardées de J8 ; photos des captures réduites à 256 couleurs (21 Mo de captures ramenés à 15 Mo) | Les liens des notes restent valables ; le style ne change pas une sortie de terminal |
| 2026-11-25 | Flèche des listes de choix dessinée par la charte (#100) : chevron encre à 0,75rem du bord, place réservée à droite du texte ; flèche native rendue en contraste forcé | La flèche native, collée au bord, mordait le contour arrondi ; une seule règle (`fleche-liste`) sert aux champs de formulaire et aux listes des tableaux et filtres |
| 2026-11-25 | Gel du style marqué par le tag `v1.1.0` sur `main`, `v1.0.0` conservé | Version présentée au jury identifiable ; l'état du premier gel reste consultable |
| 2026-11-25 | Pages d'erreur de la charte (404, 403, 405, 429, 500, générique), la 500 sans barre de navigation | Message clair et liens pour continuer au lieu de la page brute ; la 500 s'affiche même si la base de données ne répond pas |
| 2026-11-25 | Point d'entrée du pare-feu qui ajoute un message avant la redirection vers la connexion | Le visiteur sait pourquoi il arrive sur E1 ; il retrouve ensuite la page demandée |
| 2026-11-25 | `@source "../../templates"` dans `app.css` | Tailwind ignorait `templates/bundles/` : les classes des pages d'erreur n'étaient pas générées |
| 2026-11-25 | Pages légales ouvertes à tous (#106) : conditions générales d'utilisation, avec les mentions légales, et politique de confidentialité, avec une section sur les cookies ; liens dans le pied de page | Information obligatoire des utilisateurs (LCEN, RGPD article 13) ; la politique reprend le dictionnaire de données, sans nouvelle règle |
| 2026-11-25 | Case « J'accepte les CGU » obligatoire à l'inscription, non enregistrée en base | Pas de compte sans acceptation ; garder la date d'acceptation demanderait une colonne et une migration, renvoyées à une version ultérieure |
| 2026-11-25 | Envoi de la photo d'un objet depuis E7 (#106) : ajout, remplacement, retrait ; ancienne photo effacée après l'enregistrement, nouvelle effacée si l'enregistrement échoue, photo effacée avec l'objet | Un gestionnaire qui ajoute un objet doit pouvoir le montrer ; aucun objet ne pointe vers une photo absente, aucun fichier orphelin |
| 2026-11-25 | Photo réencodée par GD en WebP 960 × 720, recadrée au centre en 4:3, redressée selon l'orientation EXIF ; extensions `gd` et `exif` ajoutées à l'image Docker | Aucun octet du fichier envoyé n'est gardé : contenu caché et métadonnées (position GPS) effacés ; mêmes dimensions que les photos du jeu d'essai |
| 2026-11-25 | Photo validée sur son contenu (`Assert\Image` avec `symfony/mime`) : JPEG, PNG ou WebP, 8 Mo, 640 × 480 au moins, 40 millions de pixels au plus ; nom aléatoire de 32 caractères | Le type est lu dans le fichier, pas dans son extension ; plafond de pixels contre une image piégée qui saturerait la mémoire ; aucun nom fourni par l'utilisateur sur le disque, `ck_objet_image` inchangée |
| 2026-11-25 | Toutes les photos dans `public/uploads/objets/`, y compris celles du jeu d'essai, copiées par les fixtures depuis `fixtures/photos-objets/` | AssetMapper compile `assets/` : on ne peut pas y écrire ; un seul dossier et une seule règle d'affichage |
| 2026-11-25 | Bandeau d'information sur les cookies, sans demande de consentement ; le choix « Compris » est gardé dans le navigateur (`localStorage`), sans cookie | Le seul cookie est celui de session, strictement nécessaire, donc dispensé de consentement (CNIL) ; un bouton « Refuser » sans effet induirait en erreur. Un test vérifie qu'aucun cookie n'est déposé pour consulter le site |
| 2026-11-25 | Barre de recherche par nom sur E3 et E7 (#109), dans le formulaire GET des filtres (`?q=`) ; sur E7, elle trouve aussi les objets retirés du catalogue | Retrouver un objet sans parcourir les catégories ; l'URL d'une recherche se partage et la recherche se combine aux filtres |
| 2026-11-25 | Recherche insensible à la casse et aux accents : extension PostgreSQL `unaccent` (migration) et fonction DQL `UNACCENT` ; `%`, `_` et `\` saisis échappés | En français, « echelle » doit trouver « Échelle » ; un caractère saisi ne sert jamais de joker |
| 2026-11-25 | Recherche à la volée dès 3 lettres, 300 ms après la dernière frappe (`assets/recherche.js`) : la page est redemandée et seules les zones de résultats sont remplacées ; bouton « Rechercher » conservé, nombre de résultats annoncé (`aria-live`) | Moins de 3 lettres donnerait trop de résultats ; sans JavaScript, au clavier ou avec un lecteur d'écran, le bouton suffit. Le serveur rend le même gabarit : aucune API ni règle d'affichage en double |
| 2026-11-25 | Réactivation d'un objet retiré depuis E7 (#111) : bouton « Réactiver » à la place de « Désactiver », POST avec jeton CSRF propre à l'objet, action d'audit `OBJET_REACTIVE` ; lève la décision du 2026-11-24 qui la laissait hors MVP | Un objet retiré le temps d'une réparation devait être recréé, en perdant son historique ; la désactivation n'annule aucune réservation, la réactivation ne crée donc aucun conflit |
| 2026-11-25 | Pas de confirmation avant de réactiver ; validateur MongoDB mis à jour (`audit_log.mongodb.js` rejoué sur les deux bases) | L'action s'annule en un clic avec « Désactiver » : la confirmation reste réservée à la suppression, irréversible. Sans la nouvelle action dans le validateur, MongoDB refuserait l'entrée du journal |
| 2026-11-26 | Intégration continue par GitHub Actions qui démarre la même pile Docker qu'en local, avec des secrets générés à chaque exécution | Les tests tournent dans le même environnement que sur nos postes (PostgreSQL, MongoDB, comptes aux droits minimaux) ; aucun secret à stocker dans GitHub pour la CI |
| 2026-11-26 | Déploiement par Dokploy derrière Traefik, compose de production dans `deploy/dokploy/` | Dokploy déjà installé sur le VPS (reverse proxy, HTTPS, déploiement depuis GitHub) ; sous-dossier pour que le `.env` écrit par Dokploy n'écrase pas celui de Symfony |
| 2026-11-26 | Symfony fait confiance aux réseaux privés pour les en-têtes `X-Forwarded-*`, en production seulement | Traefik termine le HTTPS : sans cette confiance, Symfony croirait la requête en HTTP (pas de HSTS, liens en `http`) |
| 2026-11-26 | Jeu d'essai rangé dans `src/Demo/` (données et chargement), utilisé par les fixtures et par la commande `app:demo:initialiser` | Une seule source pour le développement et la démonstration en ligne ; mot de passe secret en production ; refus d'écrire dans une base qui contient des comptes |
| 2026-11-26 | Dossier des photos rendu à `www-data` dans l'image de production, volume `uploads_objets` | L'image tourne en non-root : sans ce propriétaire, l'envoi d'une photo échouait (Permission denied), découvert en testant la démonstration |
