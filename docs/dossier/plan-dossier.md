# Plan du dossier de projet

Ticket : [#88 J9-A1 Plan du dossier et tronc commun](https://github.com/IamArayel/MSP2-PretOutil/issues/88)

Plan commun aux deux dossiers (Anthony et Chloé), répartition des réalisations et couverture des compétences du CCP 2. Les chiffres et extraits de code cités dans les dossiers sont ceux du tag `v1.4.0` ; le gel de `v1.0.0` (fin de J8) et les ajouts suivants sont mentionnés là où ils comptent.

## 1. Attendus retenus

| Attendu | Source | Décision |
|---|---|---|
| Plan d'un projet réalisé pendant la formation : compétences mises en œuvre, expression des besoins (objectifs et limites), environnement technique, réalisations | Référentiel d'évaluation CDA V04, page 7 | Plan ci-dessous, qui détaille les réalisations par compétence |
| Compétences du CCP 2 obligatoirement mises en œuvre : analyser les besoins et maquetter, définir l'architecture logicielle, concevoir et mettre en place une base relationnelle, développer des composants d'accès aux données SQL et NoSQL | Référentiel, page 5 | Une partie par compétence (parties 4 à 7) |
| Longueur : 30 à 40 pages hors page de garde, sommaire et annexes ; annexes : 20 pages au plus | Consigne de la MSP (README, section 1) | Cible : 36 pages. Le référentiel de l'épreuve finale du titre fixe 40 à 60 pages et 40 pages d'annexes : non retenu pour la MSP |
| Résumé, partie en anglais | Référentiel, pages 5 à 7 | Non demandés pour le dossier. L'anglais (niveau B1) est évalué par la documentation technique comprise, pas par une partie rédigée |
| Critères à rendre visibles | Référentiel, pages 10 et 11 | Enchaînement des maquettes formalisé par un schéma ; rôle de chaque couche selon la stratégie de sécurité ; besoins d'éco-conception ; règles de nommage ; intégrité, sécurité et confidentialité des données ; base de test restaurable avec jeu d'essai complet ; cas d'exception ; entrées contrôlées ; tests unitaires et de sécurité de chaque composant |

## 2. Sommaire et pages cibles

Parties 1 à 3 et 8 à 10 : tronc commun, même texte dans les deux dossiers. Parties 4 à 7 : tronc commun pour la conception, réalisations personnelles pour le code.

| # | Partie | Pages | Compétence | Sources |
|---|---|---|---|---|
| | Page de garde, sommaire | hors compte | | Template MSP2 |
| 1 | Compétences mises en œuvre | 1 | Toutes | README section 1 ; section 4 ci-dessous |
| 2 | Expression des besoins : contexte, problèmes, objectifs SMART, acteurs, user stories, règles de gestion, exigences non fonctionnelles, limites (hors périmètre) | 4 | Analyser les besoins | README sections 2 à 9 ; [cahier-des-charges.md](../cahier-des-charges.md) |
| 3 | Environnement technique et gestion de projet : stack et justifications, conteneurs Docker, outils, planning en 10 jours, tickets, branches, PR, relecture croisée, journal des décisions | 3 | Transverse (gestion de projet) | README sections 10, 13, 15 et 16 ; [notes-j1-j2.md](notes-j1-j2.md) section 3 |
| 4 | Analyse et maquettes : diagramme de cas d'utilisation, maquettes, enchaînement des écrans, charte et accessibilité | 4 | Analyser les besoins et maquetter | [cas-utilisation.png](../diagrammes/cas-utilisation.png), [maquettes/](../maquettes/), [enchainement-ecrans.svg](../diagrammes/enchainement-ecrans.svg), [accessibilite.md](../tests/accessibilite.md) |
| 5 | Architecture logicielle : couches Symfony, schéma, diagramme de séquence « Réserver un objet », sécurité par couche, éco-conception | 4 | Définir l'architecture | [architecture.md](../diagrammes/architecture.md), [sequence-reserver.md](../diagrammes/sequence-reserver.md), README section 11 |
| 6 | Base relationnelle : MCD, MPD, dictionnaire et conventions de nommage, migrations (`btree_gist`, `EXCLUDE`, `CHECK`), fixtures, comptes aux droits minimaux, sauvegarde et restauration, RGPD | 6 | Concevoir et mettre en place une base relationnelle | [conception/](../conception/) ; [notes-j3.md](notes-j3.md) ; captures `code-migration-exclude`, `terminal-comptes-restreints-*` |
| 7 | Accès aux données SQL et NoSQL : réalisations personnelles (voir section 3), extraits commentés et preuves | 7 | Développer des composants d'accès aux données | [requetes-catalogue.md](../conception/requetes-catalogue.md), [reservation-service.md](../conception/reservation-service.md), [api.md](../conception/api.md), [audit_log.mongodb.js](../conception/audit_log.mongodb.js) ; [notes-j4.md](notes-j4.md) à [notes-j8.md](notes-j8.md) |
| 8 | Sécurité : stratégie par couche, veille OWASP Top 10:2025, vulnérabilités trouvées et corrigées, en-têtes HTTP, RGPD | 3 | Transverse (critères de sécurité des 4 compétences) | [veille-owasp.md](../securite/veille-owasp.md), [notes-j7.md](notes-j7.md), captures `http-entetes-catalogue`, `terminal-audits` |
| 9 | Tests : plan de tests, matrice de contrôle d'accès, jeu d'essai « Réserver un objet » (entrée, attendu, obtenu, écarts), protections retirées pour prouver les tests | 3 | Critère « tests unitaires et de sécurité associés à chaque composant » | [plan-de-tests.md](../tests/plan-de-tests.md), [jeu-essai-reserver.md](../tests/jeu-essai-reserver.md), [correspondance-rg-tests.md](../tests/correspondance-rg-tests.md), captures `terminal-phpunit-*`, `terminal-jeu-essai` |
| 10 | Synthèse et conclusion : satisfactions, difficultés, suites | 1 | | Notes J1 à J8 (sections « Démarche qualité ») |
| | **Total** | **36** | | |
| | Annexes (20 pages au plus) : maquettes complètes, dictionnaire de données, script de création de la base, code complet des composants les plus significatifs | hors compte | | `docs/conception/mpd.sql`, `docs/maquettes/`, `src/` |

## 3. Répartition des réalisations

D'après les tickets, les PR et l'auteur des commits. Quand le ticket et les commits divergent, les commits font foi.

| Jour | Ticket | Réalisation | Auteur |
|---|---|---|---|
| J1 | (pas de ticket) | Cahier des charges, cas d'utilisation, maquettes Figma, enchaînement des écrans | Anthony et Chloé (versés au dépôt par Anthony) |
| J2 | [#1](https://github.com/IamArayel/MSP2-PretOutil/issues/1), [#2](https://github.com/IamArayel/MSP2-PretOutil/issues/2), [#3](https://github.com/IamArayel/MSP2-PretOutil/issues/3) | MCD, MLD / MPD et contrainte anti-chevauchement, dictionnaire de données et conventions de nommage | Anthony |
| J2 | [#4](https://github.com/IamArayel/MSP2-PretOutil/issues/4), [#5](https://github.com/IamArayel/MSP2-PretOutil/issues/5), [#6](https://github.com/IamArayel/MSP2-PretOutil/issues/6) | Initialisation Symfony, Docker et PostgreSQL ; MongoDB et Doctrine ODM ; Tailwind | Chloé |
| J2 | [#7](https://github.com/IamArayel/MSP2-PretOutil/issues/7), [#8](https://github.com/IamArayel/MSP2-PretOutil/issues/8) | Diagramme de séquence « Réserver un objet », schéma d'architecture | Anthony et Chloé |
| J3 | [#17](https://github.com/IamArayel/MSP2-PretOutil/issues/17), [#18](https://github.com/IamArayel/MSP2-PretOutil/issues/18), [#19](https://github.com/IamArayel/MSP2-PretOutil/issues/19) | Entités et enums, migrations avec `CHECK` et `EXCLUDE`, fixtures | Anthony |
| J3 | [#20](https://github.com/IamArayel/MSP2-PretOutil/issues/20), [#21](https://github.com/IamArayel/MSP2-PretOutil/issues/21) | Authentification, rôles, `login_throttling` ; inscription (US1) | Chloé |
| J3 | [#22](https://github.com/IamArayel/MSP2-PretOutil/issues/22) | Comptes de base de données aux droits minimaux | Chloé |
| J3 | [#23](https://github.com/IamArayel/MSP2-PretOutil/issues/23) | Sauvegarde et restauration des bases | Anthony |
| J4 | [#33](https://github.com/IamArayel/MSP2-PretOutil/issues/33), [#34](https://github.com/IamArayel/MSP2-PretOutil/issues/34) | Requêtes du catalogue (pagination, filtres, disponibilité) ; écrans E3 et E4 | Anthony |
| J4 | [#35](https://github.com/IamArayel/MSP2-PretOutil/issues/35), [#36](https://github.com/IamArayel/MSP2-PretOutil/issues/36) | Voters ; gestion du catalogue (E7, US6) | Chloé |
| J5 | [#44](https://github.com/IamArayel/MSP2-PretOutil/issues/44) | `ReservationService` et exceptions métier (RG1 à RG6) | Anthony (commits de la PR #50) |
| J5 | [#45](https://github.com/IamArayel/MSP2-PretOutil/issues/45) | Tests de `ReservationService` et de la contrainte `EXCLUDE` | Chloé (commits de la PR #50) |
| J5 | [#46](https://github.com/IamArayel/MSP2-PretOutil/issues/46) | Formulaire de réservation sur E4 (US4) | Chloé |
| J6 | [#49](https://github.com/IamArayel/MSP2-PretOutil/issues/49) | API REST des disponibilités (US11) et appel depuis E4 | Anthony |
| J6 | [#54](https://github.com/IamArayel/MSP2-PretOutil/issues/54) | Écran E5 Mes réservations et annulation (US5, RG6) | Anthony |
| J6 | [#55](https://github.com/IamArayel/MSP2-PretOutil/issues/55) | Écran E6 Sorties et retours (US7, US8, RG7) | Chloé |
| J6 | [#56](https://github.com/IamArayel/MSP2-PretOutil/issues/56) | Journal d'audit MongoDB et écran E9 (US10, RG8) | Chloé |
| J7 | [#64](https://github.com/IamArayel/MSP2-PretOutil/issues/64), [#65](https://github.com/IamArayel/MSP2-PretOutil/issues/65) | Plan de tests et contrôle d'accès de chaque route ; jeu d'essai « Réserver un objet » | Anthony |
| J7 | [#66](https://github.com/IamArayel/MSP2-PretOutil/issues/66), [#67](https://github.com/IamArayel/MSP2-PretOutil/issues/67) | Tests de sécurité (CSRF, injections, XSS) ; veille OWASP Top 10 ; en-têtes de sécurité HTTP (PR #74) | Chloé |
| J8 | [#76](https://github.com/IamArayel/MSP2-PretOutil/issues/76) | Écran E8 Gestion des comptes (US9), actions `COMPTE_*` ajoutées au journal | Anthony |
| J8 | [#77](https://github.com/IamArayel/MSP2-PretOutil/issues/77) | Accessibilité des écrans adhérent (E1 à E5) | Anthony |
| J8 | [#78](https://github.com/IamArayel/MSP2-PretOutil/issues/78), [#79](https://github.com/IamArayel/MSP2-PretOutil/issues/79) | Accessibilité des écrans de gestion (E6 à E9) ; vérification dans le navigateur et captures | Chloé |
| Style | [#94](https://github.com/IamArayel/MSP2-PretOutil/issues/94), [#100](https://github.com/IamArayel/MSP2-PretOutil/issues/100) | Charte graphique, logo et favicon ; style des listes déroulantes | Anthony |
| Style | [#95](https://github.com/IamArayel/MSP2-PretOutil/issues/95), [#101](https://github.com/IamArayel/MSP2-PretOutil/issues/101) | Composants de style communs ; pages d'erreur | Chloé |
| Extra | [#106](https://github.com/IamArayel/MSP2-PretOutil/issues/106) | Pages légales, bandeau cookies, photos des objets depuis E7 | Anthony (PR #107 et #108) |
| Extra | [#109](https://github.com/IamArayel/MSP2-PretOutil/issues/109), [#111](https://github.com/IamArayel/MSP2-PretOutil/issues/111) | Barre de recherche ; réactivation d'un objet (`OBJET_REACTIVE`) | Anthony |
| Chaque jour | [#9](https://github.com/IamArayel/MSP2-PretOutil/issues/9), [#24](https://github.com/IamArayel/MSP2-PretOutil/issues/24), [#37](https://github.com/IamArayel/MSP2-PretOutil/issues/37), [#47](https://github.com/IamArayel/MSP2-PretOutil/issues/47), [#57](https://github.com/IamArayel/MSP2-PretOutil/issues/57), [#68](https://github.com/IamArayel/MSP2-PretOutil/issues/68), [#80](https://github.com/IamArayel/MSP2-PretOutil/issues/80), [#96](https://github.com/IamArayel/MSP2-PretOutil/issues/96) | Relecture croisée, PR, README, notes du dossier | Anthony et Chloé |

## 4. Couverture des compétences

| Compétence du CCP 2 | Anthony | Chloé | Partie du travail de l'autre à présenter et savoir expliquer |
|---|---|---|---|
| Analyser les besoins et maquetter | Cahier des charges, maquettes et enchaînement (à deux), charte (#94) | Maquettes et enchaînement (à deux) | Aucune |
| Définir l'architecture logicielle | Schéma d'architecture et séquence (à deux) | Schéma et séquence (à deux), initialisation Docker (#4 à #6), en-têtes HTTP | Anthony : conteneurs Docker et en-têtes HTTP |
| Concevoir et mettre en place une base relationnelle | MCD, MPD, dictionnaire, migrations, fixtures, sauvegarde (#1 à #3, #17 à #19, #23) | Comptes aux droits minimaux (#22) | Anthony : comptes aux droits minimaux. Chloé : MCD, MPD et contrainte `EXCLUDE` |
| Développer des composants d'accès aux données SQL et NoSQL | SQL : catalogue, `ReservationService`, API, E5, E8, recherche, réactivation. NoSQL : actions ajoutées au journal (#76, #111) | NoSQL : journal d'audit, validateur, compte en ajout seul, E9 (#56). SQL : E7, E6, Voters | Anthony : document `AuditLog`, `AuditLogger`, validateur et compte en ajout seul. Chloé : requête du catalogue et `ReservationService` |

## 5. Rédaction

| Partie | Rédaction | Relecture |
|---|---|---|
| Tronc commun : parties 1, 2, 4 et 6 (#88) | Anthony | Chloé |
| Tronc commun : parties 3 (gestion de projet), 5 (architecture), 8 et 9 (#89) | Anthony, à partir des documents du dépôt | Chloé |
| Partie 7 et extraits personnels | Chaque candidat (#90, #91) | L'autre candidat (#92) |
