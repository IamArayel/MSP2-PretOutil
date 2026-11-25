# Plan de tests

Ticket : [#64 J7-A1 Plan de tests et contrôle d'accès sur chaque route (O5)](https://github.com/IamArayel/MSP2-PretOutil/issues/64)

Ce plan dit ce qui est testé, comment, et à quel critère on juge que l'application est prête. Il renvoie au détail de chaque famille de tests.

## 1. Objectifs

| Objectif | Mesure | Source |
|---|---|---|
| Règles de gestion | 100 % des règles RG1 à RG6 ont un test qui fait échouer la règle et un test qui la respecte | Critère O5 du [cahier des charges](../cahier-des-charges.md) |
| Contrôle d'accès | Chaque route a un test par profil (visiteur, adhérent, gestionnaire, administrateur) | Critère O5 |
| Sécurité | Chaque protection (CSRF, injections, XSS, limitation des tentatives) a un test qui échouerait si elle était retirée | [#66](https://github.com/IamArayel/MSP2-PretOutil/issues/66) |
| Fonctionnalité représentative | Jeu d'essai de « Réserver un objet » rejoué, résultat obtenu identique à l'attendu | [#65](https://github.com/IamArayel/MSP2-PretOutil/issues/65) |
| Non-régression | Toute la suite est verte avant chaque fusion dans `main` | Relecture croisée de fin de journée |

## 2. Niveaux de tests

| Niveau | Ce qu'il vérifie | Outils | Données |
|---|---|---|---|
| Unitaire | Une classe seule : règles du service, Voters, construction des documents du journal | PHPUnit, doubles de test, horloge fixée (`MockClock`) | Aucune base |
| Intégration | Ce que seules les bases garantissent : contrainte `EXCLUDE`, droits du compte applicatif, validateur MongoDB | PHPUnit, `KernelTestCase` | PostgreSQL et MongoDB `pretoutil_test` |
| Fonctionnel | Une vraie requête HTTP de bout en bout : routes, contrôle d'accès, formulaires, API | PHPUnit, `WebTestCase` (navigateur simulé) | PostgreSQL `pretoutil_test`, transaction annulée après chaque test |
| Manuel | Parcours complet de fin de journée, rendu visuel, navigation au clavier | Requêtes HTTP sur les fixtures, navigateur | Base de développement, fixtures rechargées ensuite |

Les tests automatisés tournent avec le compte applicatif `pretoutil_app`, aux droits minimaux : un test ne peut pas réussir grâce à des droits que l'application n'a pas en production.

## 3. Lancer les tests

Prérequis une fois par poste : bases de test créées et migrées (README, section Tests).

```bash
docker compose exec php bin/phpunit --testdox
```

Un seul fichier : `docker compose exec php bin/phpunit tests/Controller/ControleAccesTest.php`.

## 4. Inventaire au 2026-11-25

435 tests, 2 048 assertions, tous verts, en 40 secondes environ, avec la barre de recherche (#109, 10 tests dans `RechercheCatalogueTest.php` et 3 pages de recherche dans les contrôles d'accessibilité). Sur le tag `v1.2.0` : 415 tests, 1 918 assertions, avec #106 : pages légales (19 tests, dont 5 dans `PagesLegalesTest.php` et les deux nouvelles pages dans la matrice d'accès et les contrôles d'accessibilité) et photos depuis E7 (13 tests). Sur le tag `v1.1.0` : 383 tests, 1 701 assertions. Le style a ajouté 20 tests : alternative textuelle de chaque image, logo et favicon, photos des objets et contrainte `ck_objet_image` (#94) ; couleurs Tailwind brutes interdites dans les gabarits et page de référence des composants absente hors développement (#95) ; pages d'erreur et redirection vers la connexion (#101, 11 tests). La vérification du soir (#96), avant #101, comptait 372 tests et 1 576 assertions. Au gel de J8 (`v1.0.0`) : 363 tests, 1 466 assertions, identiques sur trois lancements de suite après un environnement relancé depuis zéro.

| Fichier | Niveau | Tests | Détail |
|---|---|---|---|
| `tests/Service/ReservationServiceTest.php` | Unitaire | 35 | [correspondance-rg-tests.md](correspondance-rg-tests.md) |
| `tests/Security/Voter/ReservationVoterTest.php` | Unitaire | 16 | RG6, accès aux réservations |
| `tests/Security/Voter/ObjetVoterTest.php` | Unitaire | 15 | Gestion et suppression des objets |
| `tests/Service/AuditLoggerTest.php` | Unitaire | 3 | RG8 sans bloquer l'action métier |
| `tests/Service/PhotoObjetTest.php` | Unitaire | 6 | Photo réencodée en WebP 960 × 720, PNG transparent, EXIF et script ajouté effacés, orientation EXIF, fichier illisible, nom hors du dossier ignoré (#106) |
| `tests/Service/CompteServiceTest.php` | Unitaire | 8 | Écran E8 : rôle, désactivation, réactivation, refus sur son propre compte |
| `tests/Integration/ReservationServiceIntegrationTest.php` | Intégration | 6 | `EXCLUDE`, quota sur données réelles |
| `tests/Integration/AuditLogIntegrationTest.php` | Intégration | 9 | Validateur MongoDB, écriture seule |
| `tests/Integration/ObjetImageIntegrationTest.php` | Intégration | 5 | Contrainte `ck_objet_image` : nom de photo accepté, chemin ou autre format refusé |
| `tests/Controller/Api/DisponibiliteControllerTest.php` | Fonctionnel | 14 | [api.md](../conception/api.md), section 6 |
| `tests/Controller/ControleAccesTest.php` | Fonctionnel | 120 | Section 5 ci-dessous |
| `tests/Controller/AccessibiliteTest.php` | Fonctionnel | 81 | Structure accessible de E1 à E9, avec et sans recherche, et des pages légales, alternative des images, logo et favicon, photos des objets, aucune couleur hors charte ([accessibilite.md](accessibilite.md)) |
| `tests/Controller/GestionComptesControllerTest.php` | Fonctionnel | 9 | Écran E8 par HTTP, session fermée d'un compte désactivé (RG9) |
| `tests/Controller/SecuriteTest.php` | Fonctionnel | 56 | Section 6 ci-dessous |
| `tests/Controller/EntetesSecuriteTest.php` | Fonctionnel | 12 | En-têtes de sécurité HTTP et CSP ([veille-owasp.md](../securite/veille-owasp.md), section 6) |
| `tests/Controller/PagesErreurTest.php` | Fonctionnel | 11 | Pages d'erreur sans le mode débogage, API en JSON, redirection vers la connexion puis retour à la page (#101) |
| `tests/Controller/GestionCataloguePhotoTest.php` | Fonctionnel | 7 | Photo depuis E7 : ajout, remplacement et retrait avec effacement du fichier, fichier texte et photo trop petite refusés, photo effacée avec l'objet (#106) |
| `tests/Controller/RechercheCatalogueTest.php` | Fonctionnel | 10 | Recherche par nom sur E3 et E7 : sans casse ni accents, combinée à la catégorie, `%` et `_` cherchés tels quels, injection SQL et XSS sans effet, objet retiré visible seulement sur E7, saisie trop longue signalée, bouton Rechercher sans JavaScript, recherche conservée dans la pagination (#109) |
| `tests/Controller/PagesLegalesTest.php` | Fonctionnel | 5 | Liens vers les pages légales, inscription refusée sans les CGU, bandeau d'information, aucun cookie pour consulter le site (#106) |

Un test qui utilise un fournisseur de données compte une fois par jeu de données : la matrice de contrôle d'accès, par exemple, donne 96 cas.

## 5. Contrôle d'accès de chaque route

Test : [tests/Controller/ControleAccesTest.php](../../tests/Controller/ControleAccesTest.php).

### Méthode

- La matrice `ROUTES` du test donne, pour chacune des 24 routes, la réponse attendue pour chaque profil. Un fournisseur de données en tire 96 cas, un par route et par profil.
- `testMatriceCouvreToutesLesRoutes` compare la matrice à la liste des routes de l'application : une route ajoutée sans être placée dans la matrice fait échouer la suite.
- Les requêtes en POST partent sans jeton CSRF : on vérifie qui passe le contrôle d'accès, pas l'action elle-même (le CSRF est testé par [#66](https://github.com/IamArayel/MSP2-PretOutil/issues/66)).
- Une redirection vers une autre page que la connexion compte comme un accès autorisé : l'action a répondu, par exemple en refusant le jeton absent.

### Matrice

« Connexion » : le visiteur est redirigé vers la page de connexion (302).

| Route | Méthode et chemin | Visiteur | Adhérent | Gestionnaire | Administrateur |
|---|---|---|---|---|---|
| `app_accueil` | GET `/` | 200 | 200 | 200 | 200 |
| `app_catalogue` | GET `/catalogue` | 200 | 200 | 200 | 200 |
| `app_catalogue_objet` | GET `/catalogue/{id}` | 200 | 200 | 200 | 200 |
| `api_objet_disponibilites` | GET `/api/objets/{id}/disponibilites` | 200 | 200 | 200 | 200 |
| `app_connexion` | GET `/connexion` | 200 | 302 vers l'accueil | 302 | 302 |
| `app_inscription` | GET `/inscription` | 200 | 302 vers l'accueil | 302 | 302 |
| `app_deconnexion` | GET `/deconnexion` sans jeton | 403 | 403 | 403 | 403 |
| `app_catalogue_reserver` | POST `/catalogue/{id}/reserver` | Connexion | 422 (jeton absent) | 422 | 422 |
| `app_mes_reservations` | GET `/mes-reservations` | Connexion | 200 | 200 | 200 |
| `app_mes_reservations_annuler` | POST `/mes-reservations/{id}/annuler` | Connexion | 303 (sa réservation) | 403 | 403 |
| `app_gestion_catalogue` | GET `/gestion/catalogue` | Connexion | 403 | 200 | 200 |
| `app_gestion_catalogue_nouveau` | GET `/gestion/catalogue/nouveau` | Connexion | 403 | 200 | 200 |
| `app_gestion_catalogue_modifier` | GET `/gestion/catalogue/{id}/modifier` | Connexion | 403 | 200 | 200 |
| `app_gestion_catalogue_desactiver` | POST `/gestion/catalogue/{id}/desactiver` | Connexion | 403 | 302 | 302 |
| `app_gestion_catalogue_supprimer` | POST `/gestion/catalogue/{id}/supprimer` | Connexion | 403 | 302 | 302 |
| `app_sorties_retours` | GET `/gestion/sorties-retours` | Connexion | 403 | 200 | 200 |
| `app_sorties_retours_sortie` | POST `/gestion/sorties-retours/{id}/sortie` | Connexion | 403 | 303 | 303 |
| `app_sorties_retours_retour` | POST `/gestion/sorties-retours/{id}/retour` | Connexion | 403 | 303 | 303 |
| `app_admin` | GET `/admin` | Connexion | 403 | 403 | 302 vers E8 |
| `app_journal_audit` | GET `/admin/journal` | Connexion | 403 | 403 | 200 |
| `app_gestion_comptes` | GET `/admin/comptes` | Connexion | 403 | 403 | 200 |
| `app_gestion_comptes_role` | POST `/admin/comptes/{id}/role` | Connexion | 403 | 403 | 303 |
| `app_gestion_comptes_desactiver` | POST `/admin/comptes/{id}/desactiver` | Connexion | 403 | 403 | 303 |
| `app_gestion_comptes_reactiver` | POST `/admin/comptes/{id}/reactiver` | Connexion | 403 | 403 | 303 |

### Cas complémentaires

| Test | Cas | Attendu |
|---|---|---|
| `testActionRefuseeEnGet` | Les 9 actions en POST appelées en GET (lien piégé dans une page ou un email) | 405, rien n'est modifié |
| `testDeconnexionAvecJeton` | Lien « Déconnexion » de la barre de navigation, qui porte un jeton CSRF | Session fermée, retour à l'accueil |
| `testCompteDesactiveNePeutPasSeConnecter` | Compte désactivé, bon mot de passe (RG9) | Connexion refusée, message « compte désactivé », aucune session |
| `testAnnulationDeLaReservationDUnAutreAdherentRefusee` | Un adhérent change l'identifiant dans l'URL d'annulation (IDOR) | 403, réservation toujours confirmée en base |
| `testMesReservationsNAfficheQueLesSiennes` | Écran E5 d'un autre adhérent | Aucune réservation d'autrui affichée |
| `testObjetRetireOuInconnuIntrouvable` | Objet retiré du catalogue, identifiant inexistant : fiche, API, réservation | 404 partout |

### Le test détecte-t-il vraiment une faille ?

Vérification du 2026-11-24, code restauré ensuite :

| Erreur volontaire | Résultat |
|---|---|
| Règle `^/admin` retirée de `access_control` dans `security.yaml` | 3 échecs : `/admin` ouvert au visiteur, à l'adhérent et au gestionnaire |
| Route `/gestion/oubliee` ajoutée sans ligne dans la matrice | `testMatriceCouvreToutesLesRoutes` échoue |

### Constats

| Constat | Suite donnée |
|---|---|
| `/admin` n'est protégée que par `access_control`, alors que `/admin/journal` et les écrans de gestion ont aussi un `IsGranted` sur le contrôleur | Le test le détecterait ; un `IsGranted('ROLE_ADMIN')` sur `EspaceController::admin()` alignerait la page sur les autres. **Fait à J8** (#76) |
| Un visiteur qui ouvre `/deconnexion` sans jeton reçoit 403 | Voulu : la déconnexion exige un jeton CSRF, un lien piégé ne peut pas déconnecter quelqu'un |
| Le gestionnaire et l'administrateur ne peuvent pas annuler la réservation d'un adhérent (403) | Conforme à RG6 : seul l'adhérent annule ; le gestionnaire n'a pas d'écran pour cela dans le MVP |
| Le test du 429 de l'API et le test du compte désactivé laissaient leurs compteurs sur disque, ce qui faisait échouer le lancement suivant | Compteurs de l'API et des tentatives de connexion remis à zéro au début des tests concernés |

## 6. Tests de sécurité

Test : [tests/Controller/SecuriteTest.php](../../tests/Controller/SecuriteTest.php), ticket [#66](https://github.com/IamArayel/MSP2-PretOutil/issues/66).

### Risque → test

| Risque | Protection | Test | Attendu |
|---|---|---|---|
| Requête forgée depuis un autre site (CSRF) | Jeton CSRF propre à chaque formulaire, et à chaque réservation ou objet pour les boutons | `testJetonCsrfAbsentOuFauxRefuse` : 10 formulaires (réserver, annuler, sortie, retour, désactiver, supprimer, créer et modifier un objet, inscription, connexion), jeton absent puis faux | Aucune erreur serveur, état inchangé en base (ou aucune session ouverte) |
| | | `testJetonCsrfValideAccepte` : témoin, même envoi avec le vrai jeton | Action effectuée : le refus vient bien du jeton |
| | Jeton sur le lien de déconnexion | `ControleAccesTest::testDeconnexionAvecJeton`, matrice (`/deconnexion` sans jeton → 403) | Section 5 |
| Injection SQL | Requêtes Doctrine paramétrées, filtres convertis par le formulaire (catégorie en entité, dates en objets date), paramètres d'URL typés | `testFiltreDuCatalogueInjecteRefuse` : `' OR 1=1 --`, `{id} OR 1=1`, `{id}; DROP TABLE objet; --`, `%`, `_` dans la catégorie ; `%`, `' OR '1'='1` dans la date | 422, message d'erreur du filtre, aucune erreur SQL, table `objet` intacte |
| | | `testDateSuivieDeSqlLueCommeUneDateSeule` : `2026-12-14' OR '1'='1` | Seule la date est lue : mêmes résultats que la date seule |
| | | `testFiltreDuCatalogueValideRestreintLesResultats` : témoin | Seuls les objets de la catégorie choisie |
| | | `testParametreDUrlInjecteRefuse` : numéro de page (E3, E7) et identifiant d'objet | 404 avant toute requête |
| | | `testParametreDeLApiInjecteRefuse` | 400, aucune erreur SQL |
| | | `testSaisieContenantDuSqlEnregistreeTelleQuelle` : prénom `Robert'); DROP TABLE utilisateur; --` | Compte créé, saisie stockée telle quelle |
| Injection NoSQL | Filtre de E9 lu comme une chaîne (`MapQueryParameter`) puis converti en `ActionAudit` : aucune valeur saisie n'atteint MongoDB | `testFiltreDuJournalInjecteRefuse` : `action[$ne]=x`, `action[$gt]=`, `action[$regex]=.*` | 404 |
| | | Même test : `action=$ne`, `action={"$ne":null}` | 200, filtre ignoré avec le message « Type d'action inconnu » |
| XSS | Échappement automatique de Twig ; `JsonResponse` encode `<` et `>` | `testObjetPiegeEchappeSurLesPages` : objet nommé `<script>alert(1)</script>`, description `<img src=x onerror=alert(2)>`, sur E3, E4 et E7 | Balises affichées comme du texte (`&lt;script&gt;`) |
| | | `testObjetPiegeEchappeDansLeJournal` : même nom dans le détail de E9 | Échappé |
| | | `testObjetPiegeEchappeDansLApi` | `<` et `>` encodés en `\u003C` et `\u003E` dans le JSON, valeur décodée intacte |
| Force brute sur la connexion (US2) | `login_throttling` : 5 échecs en 15 minutes | `testConnexionBloqueeApresCinqEchecs` : 5 échecs puis le bon mot de passe | « Trop de tentatives de connexion », aucune session |
| | | `testConnexionPossibleSousLeSeuil` : témoin, 4 échecs puis le bon mot de passe | Session ouverte |
| XSS (seconde barrière) | `Content-Security-Policy` avec nonce, sans `unsafe-inline` | `EntetesSecuriteTest::testChaqueScriptPorteLeNonceDeLaRequete`, `testAucunJavaScriptEnLigneDansLesGabarits` | Chaque script porte le nonce ; aucun `on…=` dans les gabarits |
| Clickjacking, mauvaise configuration | `X-Frame-Options`, `frame-ancestors`, HSTS, `nosniff`, `Referrer-Policy` | `EntetesSecuriteTest` | En-têtes présents sur les pages et l'API, HSTS en HTTPS seulement |
| Vol de la base | Mot de passe haché (`auto` : bcrypt ou Argon2, salé) | `testMotDePasseStockeSousFormeDEmpreinte` : inscription par le formulaire, colonne lue en SQL | Mot de passe absent de la colonne, empreinte `$2y$` ou `$argon2`, vérifiée par `password_verify` |

### Le test détecte-t-il vraiment une faille ?

Vérification du 2026-11-24 : chaque protection a été retirée seule, puis le code restauré.

| Protection retirée | Résultat |
|---|---|
| `csrf_protection: false` sur les formulaires Symfony (réservation, objet, inscription) | 8 échecs : les 4 formulaires acceptent un jeton absent ou n'en ont plus |
| Contrôle du jeton supprimé dans les boutons (annuler, sortie, retour, désactiver, supprimer) | 10 échecs |
| `enable_csrf: false` dans le pare-feu | 2 échecs : connexion avec un jeton absent ou faux |
| Filtre `\|raw` sur le nom de l'objet (E4) | `testObjetPiegeEchappeSurLesPages` échoue |
| Filtre `\|raw` sur le détail du journal (E9) | `testObjetPiegeEchappeDansLeJournal` échoue |
| Mot de passe enregistré sans `hashPassword()` | `testMotDePasseStockeSousFormeDEmpreinte` échoue |
| Bloc `login_throttling` retiré | `testConnexionBloqueeApresCinqEchecs` échoue : le bon mot de passe ouvre la session au 6e essai |
| `EntetesSecuriteSubscriber` désactivé | 11 échecs dans `EntetesSecuriteTest` |
| Filtre de E9 lu brut dans la requête et transmis tel quel au repository | 3 échecs : `action[$ne]=x`, `[$gt]` et `[$regex]` renvoient 200 avec tout le journal, l'injection fonctionne |

Injection SQL : la protection est portée par Doctrine (paramètres liés) et par le typage des filtres. La retirer reviendrait à réécrire les requêtes en concaténation : cela n'a pas été simulé. `testSaisieContenantDuSqlEnregistreeTelleQuelle` échouerait alors avec une erreur de syntaxe (500).

### Constats

| Constat | Suite donnée |
|---|---|
| E3 n'a pas de recherche par texte : les saisies de la carte (`' OR 1=1 --`, `%`, `_`) sont testées sur les filtres catégorie et date, les paramètres d'URL et les champs de l'inscription | `%` et `_` ne seraient un risque que dans un `LIKE` : à tester si une recherche par texte est ajoutée |
| Une date suivie de texte (`2026-12-14' OR '1'='1`) est acceptée : le formulaire ne lit que la date | Sans risque, le texte n'atteint pas la requête ; test ajouté pour le garantir |
| Le message de blocage indique « 16 minutes » juste après le 6e essai, pour une fenêtre de 15 minutes | Arrondi supérieur de Symfony sur le temps restant ; sans incidence sur la sécurité |

## 7. Critères de réussite

| Critère | Seuil |
|---|---|
| Suite automatisée | 100 % des tests verts sur une base de test recréée |
| Règles de gestion | Chaque règle de RG1 à RG6 a un cas refusé et un cas accepté ([correspondance-rg-tests.md](correspondance-rg-tests.md)) |
| Contrôle d'accès | 24 routes sur 24 dans la matrice, 4 profils chacune |
| Jeu d'essai | Résultat obtenu identique à l'attendu pour chaque cas, ou écart expliqué ([#65](https://github.com/IamArayel/MSP2-PretOutil/issues/65)) |
| Sécurité | Chaque protection a un test ([#66](https://github.com/IamArayel/MSP2-PretOutil/issues/66)) |

## 8. Hors du périmètre

| Non testé automatiquement | Raison, et ce qui le remplace |
|---|---|
| Rendu visuel et navigation au clavier | Pas d'outil de test de navigateur dans le MVP ; structure accessible testée par `AccessibiliteTest`, le reste vérifié à la main ([accessibilite.md](accessibilite.md), section 5) |
| Montée en charge | Volume d'une association ; requête du catalogue mesurée par `EXPLAIN` ([requetes-catalogue.md](../conception/requetes-catalogue.md)) |
| Sauvegarde et restauration | Testées par script sur une pile Docker isolée (README, section Sauvegarde) |
