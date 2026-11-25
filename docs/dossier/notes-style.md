# Notes pour le dossier : journée Style

Ticket : [#96 Style-D Relecture croisée, captures refaites et tag v1.1.0](https://github.com/IamArayel/MSP2-PretOutil/issues/96)

Suite des [notes J8](notes-j8.md). Le code, gelé à J8 (`v1.0.0`), est rouvert une journée pour une charte graphique tirée du logo : couleurs, police, logo et favicon, photos des objets (#94, Anthony), composants de style communs à tous les écrans (#95, Chloé). Le soir, relecture croisée, vérification dans le navigateur, captures refaites et nouveau gel par le tag `v1.1.0` (#96, ensemble).

## 1. Preuves par compétence

| Compétence du CCP 2 | Preuves ajoutées | Où |
|---|---|---|
| Développer des interfaces utilisateur | Charte tirée du logo, contrastes calculés avant de choisir le rôle de chaque couleur ; composants définis une seule fois ; page de référence des composants | [accessibilite.md](../tests/accessibilite.md), sections 3, 9 et 10 ; [composants.png](captures/composants.png) |
| Accès aux données SQL | Colonne `objet.image` et contrainte `ck_objet_image` qui refuse tout chemin | [ObjetImageIntegrationTest.php](../../tests/Integration/ObjetImageIntegrationTest.php) |
| Sécurité et qualité (transverse) | Police servie depuis le dépôt (CSP `'self'`, RGPD) ; contraste et largeur mesurés sur chaque écran avec chaque profil ; défaut d'affichage mobile trouvé et corrigé | [accessibilite.md](../tests/accessibilite.md), section 10 ; [captures.md](captures.md), section 5 |

## 2. Relecture croisée

Chacun relit le ticket de l'autre : remarques notées ici, avec la suite donnée.

| Ticket relu | Relecteur | Remarque | Suite donnée |
|---|---|---|---|
| #95 Composants de style communs | Anthony | À compléter | |
| #94 Charte, logo, photos | Chloé | À compléter | |

## 3. Vérification et captures (#96)

| Point | Résultat |
|---|---|
| Suite de tests | 372 tests, 1 576 assertions, tous verts ; 383 tests et 1 701 assertions sur le tag `v1.1.0`, avec les pages d'erreur (#101) ([plan-de-tests.md](../tests/plan-de-tests.md)) |
| Contraste de chaque texte affiché, 13 pages, 4 profils | Aucun texte sous le seuil |
| Console et CSP pendant tout le parcours | Aucune erreur de l'application, seules les réponses 422 et 400 voulues |
| Mobile (375 px) et zoom à 200 % | Conforme après correction du composant `cadre-tableau` (E6 et E8 élargissaient la page, défaut présent depuis J8) |
| Captures | 33 refaites sous les mêmes noms, 3 ajoutées (accueil, logo, composants) ; 4 prises à la main dans Chrome (boîtes de confirmation, outils de développement) ; captures de terminal sans écran de l'application gardées de J8 ([captures.md](captures.md)) |

## 4. Suites

| Suite | Où |
|---|---|
| Tag `v1.1.0` sur `main` après la fusion, puis code de nouveau gelé | README, journal des décisions |
| Envoi de photos depuis E7 (taille, type, stockage) | Fait dans #106 (README, journal du 2026-11-25) |
