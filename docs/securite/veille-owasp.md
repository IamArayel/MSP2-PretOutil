# Veille sécurité : OWASP Top 10

Ticket : [#67 J7-B2 Veille sécurité OWASP Top 10](https://github.com/IamArayel/MSP2-PretOutil/issues/67)

Cette veille confronte Prêt'Outil aux risques de référence des applications web. Pour chaque risque, elle dit s'il concerne le projet, quelle mesure est en place et quel test la prouve. Les tests cités sont décrits dans le [plan de tests](../tests/plan-de-tests.md).

## 1. Sources consultées

Consultées le 2026-11-24.

| Source | Ce qu'on en retient | Lien |
|---|---|---|
| OWASP Top 10:2025 (8e édition, en vigueur) | Liste des 10 risques ci-dessous. Nouveautés par rapport à 2021 : la chaîne d'approvisionnement logicielle (A03) et la gestion des conditions exceptionnelles (A10) ; la SSRF rejoint le contrôle d'accès (A01) | [top10.owasp.org/2025](https://top10.owasp.org/2025/) |
| Avis de sécurité Symfony | Deux failles de `symfony/http-foundation` publiées le 2026-11-04, corrigées en 7.4.23 (section 4) ; avis Symfony UX du 2026-11-24 | [symfony.com/blog/category/security-advisories](https://symfony.com/blog/category/security-advisories) |
| CNIL, recommandation « Mots de passe » (délibération n° 2022-100 du 21 juillet 2022) | Jamais de mot de passe en clair : fonction à sens unique salée. Avec une restriction d'accès (délai ou nombre d'essais limité), 50 bits d'entropie suffisent ; renouvellement périodique non recommandé pour un compte ordinaire | [cnil.fr, mots de passe](https://www.cnil.fr/fr/mots-de-passe-une-nouvelle-recommandation-pour-maitriser-sa-securite) |
| CNIL, Guide de la sécurité des données personnelles | Fiches retenues : authentifier les utilisateurs (4), sécuriser les sites web (11), encadrer les développements (12), tracer les opérations (17), sauvegarder (18), chiffrement et hachage (21), API (25) | [cnil.fr, guide](https://www.cnil.fr/fr/principes-cles/guide-de-la-securite-des-donnees-personnelles) |
| Audit des dépendances | `composer audit` et `importmap:audit` lancés sur le projet (section 4) | Commandes du README |

## 2. Les 10 risques appliqués à Prêt'Outil

| Risque OWASP 2025 | En bref | Concerne Prêt'Outil ? | Mesure en place | Preuve |
|---|---|---|---|---|
| **A01 Contrôle d'accès défaillant** | Un utilisateur agit hors de ses droits : page d'un autre rôle, ressource d'un autre utilisateur (IDOR), SSRF | Oui : 3 rôles, réservations personnelles | `access_control` par préfixe (`config/packages/security.yaml`), `IsGranted` sur chaque contrôleur, Voters `ObjetVoter` et `ReservationVoter` (propriétaire seul pour annuler), actions en POST uniquement. SSRF : non concerné, l'application n'appelle aucune URL externe | `ControleAccesTest` : 20 routes × 4 profils, route oubliée détectée, IDOR sur l'annulation, actions refusées en GET ; tests des Voters |
| **A02 Mauvaise configuration de sécurité** | Réglages par défaut, services inutiles, messages d'erreur trop bavards, en-têtes manquants | Oui | Ports des bases publiés sur `127.0.0.1` seulement ; secrets dans `.env.local` non versionné ; compte applicatif aux droits minimaux (PostgreSQL et MongoDB) ; cookie de session `secure`, `httponly`, `samesite=lax` ; HTTP redirigé vers HTTPS (308) ; en-têtes de sécurité sur chaque réponse (`EntetesSecuriteSubscriber` : HSTS, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`) ; hub Mercure limité au développement (`frankenphp/dev.d/`) ; erreurs de l'API génériques (`ApiExceptionSubscriber`) | `EntetesSecuriteTest` ; `ReservationServiceIntegrationTest::testCompteApplicatif`, `AuditLogIntegrationTest` (suppression refusée, code 13), `bin/console app:mongodb:check` |
| **A03 Défaillances de la chaîne d'approvisionnement** | Dépendance vulnérable, abandonnée ou piégée ; outils de build compromis | Oui : Composer, images Docker | `composer.lock` versionné ; Symfony 7.4 LTS ; `composer audit` et `importmap:audit` sans faille ; aucune dépendance JavaScript tierce (importmap limité à `app.js`) | Section 4 |
| **A04 Défaillances cryptographiques** | Données sensibles en clair, algorithmes faibles | Oui : mots de passe, session | Hasher `auto` (bcrypt ou Argon2id, salé) conforme à la CNIL ; HTTPS par Caddy ; cookie de session `secure` | `SecuriteTest::testMotDePasseStockeSousFormeDEmpreinte` |
| **A05 Injection** | SQL, NoSQL, XSS, commandes | Oui : PostgreSQL, MongoDB, pages Twig | Voir section 3 ; contre le XSS, en seconde barrière, une `Content-Security-Policy` n'exécute que les scripts qui portent le nonce de la requête | `SecuriteTest` : injections SQL et NoSQL, XSS sur E3, E4, E7, E9 et l'API ; `EntetesSecuriteTest` : CSP sans `unsafe-inline`, nonce sur chaque script |
| **A06 Conception non sécurisée** | Faille dans la règle elle-même, que le code applique fidèlement | Oui : réservation concurrente, quota | Règles RG1 à RG7 centralisées dans `ReservationService` ; contrainte `EXCLUDE` en base contre la double réservation ; verrou sur l'adhérent pour le quota ; limitation de débit de l'API ; aucune donnée personnelle dans l'API | `ReservationServiceTest` (35 tests), `ReservationServiceIntegrationTest` (`EXCLUDE`, quota), `DisponibiliteControllerTest` (429, aucune donnée personnelle) |
| **A07 Défaillances d'authentification** | Force brute, mots de passe faibles, session mal gérée | Oui | `login_throttling` (5 échecs en 15 minutes) ; mot de passe de 12 caractères minimum ; message d'échec générique ; jeton CSRF sur la connexion et la déconnexion ; compte désactivé refusé (`UtilisateurChecker`) ; identifiant de session renouvelé à la connexion (stratégie par défaut de Symfony) | `SecuriteTest` : blocage après 5 échecs, CSRF de la connexion ; `ControleAccesTest` : compte désactivé, déconnexion sans jeton refusée |
| **A08 Défaillances d'intégrité des logiciels ou des données** | Code ou données modifiés sans contrôle, désérialisation non sûre | Peu | Aucun `unserialize` ni `exec` sur une donnée reçue ; schéma de la base modifié uniquement par des migrations versionnées ; journal d'audit en ajout seul (rôle `auditAppendOnly`) et validé par `$jsonSchema` | `AuditLogIntegrationTest` : documents invalides refusés (121), modification et suppression refusées (13) |
| **A09 Défaillances de journalisation et d'alerte** | Actions sensibles non tracées, attaques non détectées | Oui | Journal d'audit MongoDB (RG8) : réservations, sorties, retours, gestion du catalogue, connexions échouées et blocages ; conservation 1 an (index TTL) ; consultable sur E9 par l'administrateur ; échec d'écriture du journal tracé dans les logs | `AuditLoggerTest`, `AuditLogIntegrationTest` ; parcours E9 de J6-B2 |
| **A10 Mauvaise gestion des conditions exceptionnelles** | Erreur mal traitée : application qui « échoue ouverte », message qui révèle l'interne, transaction à moitié écrite | Oui | Écritures métier dans une transaction annulée en cas d'erreur ; exceptions métier traduites en messages (`ReservationRefuseeException`) ; contrainte `EXCLUDE` traduite en refus ; panne de MongoDB sans effet sur l'action métier ; jeton CSRF invalide → refus, jamais accepté par défaut ; erreurs de l'API en JSON génériques | `ReservationServiceTest::testRG2ConflitConcurrentTraduitEnRefusMetier`, `testAutreErreurSqlNonTraduite`, `AuditLoggerTest::testMongoDbIndisponibleNeBloquePasLActionMetier`, `SecuriteTest` (aucune réponse 500) |

## 3. Focus : injections SQL et NoSQL

### SQL (PostgreSQL)

- **Requêtes paramétrées.** Toutes les requêtes passent par Doctrine (QueryBuilder ou DQL) avec `setParameter()`. Aucune n'est construite par concaténation d'une saisie. Exemple : `ObjetRepository::catalogue()`, `->andWhere('o.categorie = :categorie')->setParameter('categorie', $criteres->categorie)`.
- **Saisies typées avant la requête.** Le filtre catégorie de E3 est converti en entité par le formulaire (`EntityType`), les dates en objets date, les numéros de page et identifiants en entiers (`MapQueryParameter`, prérequis de route `\d+`). Une valeur qui ne se convertit pas est refusée avant d'atteindre la base.
- **Défense en profondeur.** Le compte applicatif `pretoutil_app` n'a que `SELECT`, `INSERT` et `UPDATE` sur les quatre tables, plus `DELETE` sur `objet` seulement (migration `Version20261123140000`) : même injectée, une requête ne pourrait ni supprimer une table, ni modifier le schéma, ni effacer un utilisateur ou une réservation.
- **Preuves.** `SecuriteTest` : `' OR 1=1 --`, `; DROP TABLE objet`, `%` et `_` dans les filtres, saisie `Robert'); DROP TABLE utilisateur; --` enregistrée telle quelle.

### NoSQL (MongoDB)

- **Le risque.** PHP transforme `?action[$ne]=x` en tableau `['$ne' => 'x']`. Transmis tel quel au filtre, il deviendrait l'opérateur MongoDB « différent de » et renverrait tout le journal.
- **La mesure.** Sur E9, le paramètre est lu comme une chaîne (`#[MapQueryParameter] string $action`) : un tableau est refusé (404). La chaîne est ensuite convertie en énumération `ActionAudit` ; une valeur inconnue est ignorée. Le repository ne reçoit qu'une `ActionAudit` (`AuditLogRepository::lister(?ActionAudit $action)`) : aucune valeur saisie n'atteint MongoDB.
- **Défense en profondeur.** Le compte applicatif MongoDB ne peut que lire et ajouter dans `audit_log` ; le validateur `$jsonSchema` refuse tout document hors schéma.
- **Preuves.** `SecuriteTest::testFiltreDuJournalInjecteRefuse`. En transmettant volontairement le filtre brut au repository, `action[$ne]=x` renvoyait tout le journal et le test échouait (plan de tests, section 6).

## 4. Dépendances

Résultats du 2026-11-24 (Composer 2.11.2, PHP 8.5.13) :

| Commande | Résultat |
|---|---|
| `docker compose exec php composer audit` | `No security vulnerability advisories found.` Aucun paquet abandonné signalé |
| `docker compose exec php bin/console importmap:audit` | `No vulnerabilities found.` |

Avis Symfony récents confrontés aux versions installées :

| Avis | Composant | Corrigé en | Installé | Statut |
|---|---|---|---|---|
| CVE-2026-135802 ([GHSA-5mx8-mwrp-5693](https://symfony.com/blog/ghsa-5mx8-mwrp-5693-generated-urls-can-point-to-a-host-chosen-by-the-client)) : URL générées vers un hôte choisi par le client | `symfony/http-foundation` | 7.4.23 | 7.4.23 | Corrigé |
| CVE-2026-135801 ([GHSA-3h27-hjv7-fqjx](https://symfony.com/blog/ghsa-3h27-hjv7-fqjx-path-rules-enforced-in-front-of-php-can-be-bypassed-by-appending-the-path-to-the-script-name)) : règles de chemin du serveur web contournables (`/index.phpadmin`) | `symfony/http-foundation` | 7.4.23 | 7.4.23 | Corrigé ; de plus, `/admin` est protégé par le pare-feu Symfony, que cette faille ne contourne pas |
| Avis Symfony UX du 2026-11-24 (`ux-icons`, `ux-live-component`, `stimulus-bridge`) | Symfony UX | — | Non installé | Non concerné |

Mise à jour : `composer update "symfony/*"` dans la contrainte `7.4.*`, puis la suite de tests. Les versions 8.x sont disponibles ; le projet reste sur la LTS 7.4, maintenue pour les correctifs de sécurité.

## 5. Constats et mesures écartées

### Constats de la veille

| Constat | Risque | Suite donnée |
|---|---|---|
| Aucun en-tête de sécurité HTTP : ni `Strict-Transport-Security`, ni `Content-Security-Policy`, ni `X-Frame-Options`, ni `X-Content-Type-Options`, ni `Referrer-Policy` (relevé sur `/connexion`) | A02 : clickjacking possible, pas de seconde barrière si un XSS passait | **Corrigé** (section 6) |
| Hub Mercure du squelette `symfony-docker` actif, avec abonnements anonymes, alors que l'application ne l'utilise pas ; secret par défaut en développement | A02 : service exposé inutilement | **Corrigé** (section 6) |
| L'inscription signale un email déjà utilisé, et n'a pas de limitation de débit | A07 : énumération des comptes, création de comptes en masse | Accepté pour le MVP (le message aide l'utilisateur ; la connexion, elle, reste générique) ; limiteur de débit sur `/inscription` en évolution |
| Mot de passe : 12 caractères minimum, sans exigence de complexité | A07 | Conforme au cas « restriction d'accès » de la CNIL (au moins 50 bits d'entropie avec un nombre d'essais limité, assuré par `login_throttling`) ; liste de mots de passe courants refusés en évolution |

### Mesures écartées pour le MVP

| Mesure | Raison |
|---|---|
| Authentification à deux facteurs | Association de quartier, données peu sensibles (nom, email, téléphone) ; le blocage après 5 échecs couvre la force brute |
| Alerte automatique (email, supervision) sur les blocages de connexion | Volume faible : l'administrateur consulte le journal sur E9 ; pas de serveur d'envoi d'emails dans le MVP |
| Pare-feu applicatif (WAF), CAPTCHA | Disproportionné pour le volume ; `login_throttling` et la limite de débit de l'API suffisent |
| Chiffrement des échanges entre conteneurs (PostgreSQL, MongoDB) | Réseau Docker interne, ports publiés sur `127.0.0.1` seulement |
| Analyse automatique dans une intégration continue (audit, SAST) | Pas de CI dans le MVP ; `composer audit` à lancer à la main avant chaque fusion de fin de journée (README, commandes utiles) |
| Suppression automatique des comptes inactifs depuis 3 ans (RGPD) | Durée décrite et point de départ enregistré (`derniereActiviteLe`) ; purge non automatisée dans le MVP (cahier des charges, section 8) |

## 6. Corrections après la veille

Appliquées le 2026-11-24, sur la branche `chore/entetes-securite`.

### En-têtes de sécurité HTTP

Posés par `src/EventSubscriber/EntetesSecuriteSubscriber.php` sur chaque réponse de l'application (pages et API) :

| En-tête | Valeur | Ce qu'il empêche |
|---|---|---|
| `Content-Security-Policy` | `default-src 'self'`, `script-src 'nonce-…' 'strict-dynamic'`, `style-src 'self'`, `img-src 'self' data:`, `connect-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'none'` | Un script injecté (XSS) ne s'exécute pas : il ne connaît pas le nonce, tiré au hasard à chaque requête. Formulaires envoyés vers un autre site, page affichée dans un cadre |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains`, en HTTPS seulement | Retour forcé en HTTP (interception sur un réseau public). Désactivé en développement : sur `localhost`, il s'imposerait aux autres projets |
| `X-Frame-Options` | `DENY` | Clickjacking, pour les navigateurs qui ignorent `frame-ancestors` |
| `X-Content-Type-Options` | `nosniff` | Fichier interprété comme un script contre son type déclaré |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Adresse complète des pages transmise à un autre site |
| `Permissions-Policy` | caméra, micro, géolocalisation, paiement désactivés | Usage de ces fonctions par un script injecté |

Adaptations du code pour respecter la CSP :

- le nonce est placé sur les balises `<script>` par la fonction Twig `csp_nonce()` (`src/Twig/CspExtension.php`), transmise à `importmap()` ;
- les confirmations « Annuler la réservation » et « Supprimer définitivement » n'utilisent plus `onsubmit="…"`, que la CSP bloquerait, mais `data-confirmation` et `assets/confirmation.js` ;
- la page d'erreur de débogage de Symfony (styles et scripts en ligne) n'a pas de CSP, en développement seulement.

Preuve : `tests/Controller/EntetesSecuriteTest.php` (12 tests). En désactivant le subscriber, 11 échouent. Un test refuse aussi tout `on…=` ou `javascript:` dans les gabarits.

### Hub Mercure

Le bloc `mercure` quitte `frankenphp/Caddyfile` pour `frankenphp/dev.d/mercure.caddy`, monté par `compose.override.yaml` uniquement : le rechargement à chaud de FrankenPHP, en développement, en a besoin. L'image de production n'embarque pas ce dossier, le `Caddyfile` n'y déclare donc aucun hub (vérifié avec `frankenphp adapt`). Les variables `MERCURE_*` inutilisées sont retirées de `compose.yaml` et `compose.prod.yaml`.

