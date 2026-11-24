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

## 4. Inventaire au 2026-11-24

190 tests, 481 assertions, tous verts, en 10 secondes environ.

| Fichier | Niveau | Tests | Détail |
|---|---|---|---|
| `tests/Service/ReservationServiceTest.php` | Unitaire | 35 | [correspondance-rg-tests.md](correspondance-rg-tests.md) |
| `tests/Security/Voter/ReservationVoterTest.php` | Unitaire | 16 | RG6, accès aux réservations |
| `tests/Security/Voter/ObjetVoterTest.php` | Unitaire | 15 | Gestion et suppression des objets |
| `tests/Service/AuditLoggerTest.php` | Unitaire | 3 | RG8 sans bloquer l'action métier |
| `tests/Integration/ReservationServiceIntegrationTest.php` | Intégration | 6 | `EXCLUDE`, quota sur données réelles |
| `tests/Integration/AuditLogIntegrationTest.php` | Intégration | 9 | Validateur MongoDB, écriture seule |
| `tests/Controller/Api/DisponibiliteControllerTest.php` | Fonctionnel | 14 | [api.md](../conception/api.md), section 6 |
| `tests/Controller/ControleAccesTest.php` | Fonctionnel | 92 | Section 5 ci-dessous |

Un test qui utilise un fournisseur de données compte une fois par jeu de données : la matrice de contrôle d'accès, par exemple, donne 80 cas.

## 5. Contrôle d'accès de chaque route

Test : [tests/Controller/ControleAccesTest.php](../../tests/Controller/ControleAccesTest.php).

### Méthode

- La matrice `ROUTES` du test donne, pour chacune des 20 routes, la réponse attendue pour chaque profil. Un fournisseur de données en tire 80 cas, un par route et par profil.
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
| `app_admin` | GET `/admin` | Connexion | 403 | 403 | 200 |
| `app_journal_audit` | GET `/admin/journal` | Connexion | 403 | 403 | 200 |

### Cas complémentaires

| Test | Cas | Attendu |
|---|---|---|
| `testActionRefuseeEnGet` | Les 6 actions en POST appelées en GET (lien piégé dans une page ou un email) | 405, rien n'est modifié |
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
| `/admin` n'est protégée que par `access_control`, alors que `/admin/journal` et les écrans de gestion ont aussi un `IsGranted` sur le contrôleur | Le test le détecterait ; un `IsGranted('ROLE_ADMIN')` sur `EspaceController::admin()` alignerait la page sur les autres. Proposé en relecture |
| Un visiteur qui ouvre `/deconnexion` sans jeton reçoit 403 | Voulu : la déconnexion exige un jeton CSRF, un lien piégé ne peut pas déconnecter quelqu'un |
| Le gestionnaire et l'administrateur ne peuvent pas annuler la réservation d'un adhérent (403) | Conforme à RG6 : seul l'adhérent annule ; le gestionnaire n'a pas d'écran pour cela dans le MVP |
| Le test du 429 de l'API et le test du compte désactivé laissaient leurs compteurs sur disque, ce qui faisait échouer le lancement suivant | Compteurs de l'API et des tentatives de connexion remis à zéro au début des tests concernés |

## 6. Critères de réussite

| Critère | Seuil |
|---|---|
| Suite automatisée | 100 % des tests verts sur une base de test recréée |
| Règles de gestion | Chaque règle de RG1 à RG6 a un cas refusé et un cas accepté ([correspondance-rg-tests.md](correspondance-rg-tests.md)) |
| Contrôle d'accès | 20 routes sur 20 dans la matrice, 4 profils chacune |
| Jeu d'essai | Résultat obtenu identique à l'attendu pour chaque cas, ou écart expliqué ([#65](https://github.com/IamArayel/MSP2-PretOutil/issues/65)) |
| Sécurité | Chaque protection a un test ([#66](https://github.com/IamArayel/MSP2-PretOutil/issues/66)) |

## 7. Hors du périmètre

| Non testé automatiquement | Raison, et ce qui le remplace |
|---|---|
| Rendu visuel et navigation au clavier | Pas d'outil de test de navigateur dans le MVP ; vérifiés à la main à J8 |
| Montée en charge | Volume d'une association ; requête du catalogue mesurée par `EXPLAIN` ([requetes-catalogue.md](../conception/requetes-catalogue.md)) |
| Sauvegarde et restauration | Testées par script sur une pile Docker isolée (README, section Sauvegarde) |
