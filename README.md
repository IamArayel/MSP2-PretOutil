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

## 12. Livrables

- [ ] Code source versionné, avec ce README
- [x] Cahier des charges
- [x] Maquettes et schéma d'enchaînement des écrans
- [x] Diagramme de cas d'utilisation
- [x] Diagramme de séquence « Réserver un objet »
- [ ] MCD, MPD, script de création de la BDD et fixtures du jeu d'essai
- [ ] Procédure de sauvegarde et de restauration de la BDD
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
| Diagramme de séquence « Réserver un objet » | [PlantUML](docs/diagrammes/sequence-reserver.puml), [note](docs/diagrammes/sequence-reserver.md) | [SVG](docs/diagrammes/sequence-reserver.svg), [PNG](docs/diagrammes/sequence-reserver.png) |

Régénérer le diagramme de cas d'utilisation après modification :

```bash
plantuml -tsvg -tpng docs/diagrammes/cas-utilisation.puml
```

## 13. Planning

10 jours ouvrés, plus 2 jours de marge. La colonne « Dossier » se remplit chaque soir (15 min) pour éviter de tout reconstituer à la fin.

| Jour | Conception et développement | Dossier | Statut |
|---|---|---|---|
| J1 | Expression des besoins finalisée, diagramme de cas d'utilisation, maquettes Figma de 4 écrans (catalogue, réservation, mes réservations, retours), schéma d'enchaînement | Exports des maquettes et diagrammes | ✅ |
| J2 | MCD / MPD, diagramme de séquence « Réserver », schéma d'architecture. Initialisation Symfony, Docker (PostgreSQL, MongoDB, extension PHP `mongodb`), Tailwind | Justification des choix techniques | 🟡 |
| J3 | Entités Doctrine, migrations (dont `btree_gist` et `EXCLUDE`), fixtures, script de sauvegarde et de restauration. Inscription, connexion, rôles, `login_throttling` | Script SQL, règles de nommage | ⬜ |
| J4 | Catalogue paginé et filtré (requête de disponibilité), CRUD des objets, Voters | Extraits des repositories | ⬜ |
| J5 | `ReservationService` (RG1 à RG6), exceptions métier, formulaire de réservation | Code métier, cas d'exception | ⬜ |
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

2. Créer le fichier `.env.local` (non versionné) avec le secret de l'application :

   ```bash
   echo "APP_SECRET=$(openssl rand -hex 16)" > .env.local
   ```

3. Construire les images et démarrer les conteneurs :

   ```bash
   docker compose build --pull
   docker compose up -d --wait
   ```

   Au premier démarrage, le conteneur `php` installe les dépendances Composer et attend que PostgreSQL soit prêt. `--wait` attend aussi que MongoDB réponde.

4. Compiler les styles Tailwind (le binaire Tailwind est téléchargé au premier lancement dans `var/tailwind/`) :

   ```bash
   docker compose exec php bin/console tailwind:build
   ```

5. Ouvrir <https://localhost> et accepter le certificat TLS auto-signé.

### Vérifier l'installation

```bash
docker compose ps
docker compose exec php bin/console dbal:run-sql "SELECT 1"
docker compose exec php bin/console doctrine:migrations:status
docker compose exec php php -m | grep mongodb
docker compose exec php bin/console app:mongodb:check
```

`app:mongodb:check` écrit un document dans la collection `connexion_test`, le relit puis le supprime.

### Styles (Tailwind CSS)

Tailwind CSS 4 est intégré via `symfonycasts/tailwind-bundle` et AssetMapper : pas de Node ni de npm.

| Fichier | Rôle |
|---|---|
| `assets/styles/app.css` | Source : import de Tailwind et charte de la maquette dans le bloc `@theme` (couleur principale teal-700, neutres slate, couleurs des statuts) |
| `var/tailwind/app.built.css` | CSS compilé, non versionné, servi à la place de `app.css` |
| `templates/base.html.twig` | Gabarit commun, avec la barre de navigation (`templates/_partials/navbar.html.twig`) |

Pendant le développement, lancer la compilation en continu dans un terminal dédié :

```bash
docker compose exec php bin/console tailwind:build --watch
```

Les couleurs de la charte sont disponibles comme utilitaires : `bg-primary`, `text-primary`, `border-line`, `text-ink-muted`, `bg-statut-disponible-bg`, etc.

### Variables d'environnement

| Fichier | Versionné | Contenu |
|---|---|---|
| `.env` | Oui | Valeurs par défaut de développement : `APP_ENV`, `DATABASE_URL`, `MONGODB_URI`, `MONGODB_DB`, `POSTGRES_*` et `MONGODB_*` (lues aussi par Docker Compose) |
| `.env.local` | Non | Secrets et surcharges locales (`APP_SECRET`, etc.) |

Dans Docker, `compose.yaml` construit `DATABASE_URL` à partir des variables `POSTGRES_*` (service `database`) et `MONGODB_URI` à partir des variables `MONGODB_*` (service `mongodb`).

### Commandes utiles

| Action | Commande |
|---|---|
| Arrêter les conteneurs | `docker compose down` |
| Suivre les logs | `docker compose logs -f php` |
| Console Symfony | `docker compose exec php bin/console <commande>` |
| Ajouter une dépendance | `docker compose exec php composer require <paquet>` |
| Compiler les styles (minifiés) | `docker compose exec php bin/console tailwind:build --minify` |
| Appliquer les migrations | `docker compose exec php bin/console doctrine:migrations:migrate` |
| Ouvrir un shell PostgreSQL | `docker compose exec database psql -U pretoutil pretoutil` |
| Ouvrir un shell MongoDB | `docker compose exec mongodb mongosh -u pretoutil -p '!ChangeMe!' pretoutil --authenticationDatabase admin` |
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
