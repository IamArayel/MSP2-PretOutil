# Notes pour le dossier : J7

Ticket : [#68 J7-D Relecture croisée, PR et mise à jour du README](https://github.com/IamArayel/MSP2-PretOutil/issues/68)

Suite des [notes J6](notes-j6.md). J7 démontre la qualité et la sécurité par des preuves rejouables : plan de tests, contrôle d'accès de chaque route et jeu d'essai de la réservation (Anthony), tests de sécurité, veille OWASP et en-têtes de sécurité HTTP (Chloé). La suite passe de 98 à 257 tests.

## 1. Preuves par compétence

| Compétence du CCP 2 | Preuves ajoutées à J7 | Où |
|---|---|---|
| Définir l'architecture logicielle | Stratégie de sécurité par couche, confrontée à l'OWASP Top 10:2025 ; en-têtes de sécurité dans un subscriber dédié | [veille-owasp.md](../securite/veille-owasp.md), [EntetesSecuriteSubscriber](../../src/EventSubscriber/EntetesSecuriteSubscriber.php) |
| Concevoir et mettre en place la base relationnelle | Jeu d'essai de la réservation : entrée, attendu, obtenu, ligne en base ; base de test recréée et migrée depuis zéro | [jeu-essai-reserver.md](../tests/jeu-essai-reserver.md) |
| Accès aux données SQL | Injections SQL testées sur les filtres, les URL, l'API et les saisies ; requêtes paramétrées et compte aux droits minimaux | [SecuriteTest](../../tests/Controller/SecuriteTest.php) |
| Accès aux données NoSQL | Injection d'opérateurs MongoDB (`$ne`, `$gt`, `$regex`) refusée sur E9 ; test qui échoue si le filtre brut atteint le repository | [plan-de-tests.md](../tests/plan-de-tests.md), section 6 |
| Sécurité et qualité (transverse) | Plan de tests ; contrôle d'accès des 20 routes pour 4 profils (critère O5) ; CSRF sur 10 formulaires ; XSS ; force brute ; CSP à nonce ; chaque protection retirée volontairement pour vérifier que son test échoue | [plan-de-tests.md](../tests/plan-de-tests.md) |

## 2. Extraits de code à citer

| Extrait | Fichier | Ce qu'il montre |
|---|---|---|
| Matrice `ROUTES` et `testMatriceCouvreToutesLesRoutes` | [ControleAccesTest.php](../../tests/Controller/ControleAccesTest.php) | Un tableau lisible par le jury devient 80 tests ; une route oubliée fait échouer la suite |
| `testJetonCsrfAbsentOuFauxRefuse` et son témoin `testJetonCsrfValideAccepte` | [SecuriteTest.php](../../tests/Controller/SecuriteTest.php) | Un test de refus ne prouve rien seul : le témoin montre que le vrai jeton passe, donc que le refus vient du jeton |
| `testFiltreDuJournalInjecteRefuse` | [SecuriteTest.php](../../tests/Controller/SecuriteTest.php) | Injection NoSQL : `action[$ne]=x` refusé (404), aucune valeur saisie n'atteint MongoDB |
| `EntetesSecuriteSubscriber::ajouterEntetes()` et `csp_nonce()` | [src/EventSubscriber/](../../src/EventSubscriber/EntetesSecuriteSubscriber.php), [src/Twig/](../../src/Twig/CspExtension.php) | CSP à nonce : seconde barrière contre le XSS, nonce stocké dans la requête (mode worker) |
| Script du jeu d'essai | [jeu_essai_reserver.sh](../tests/jeu_essai_reserver.sh) | Le jeu d'essai est rejoué, pas seulement décrit : verdict par cas, code HTTP et document du journal |

## 3. Choix techniques à justifier

Le détail et la date de chaque décision sont dans le [journal du README](../../README.md#16-journal-des-décisions).

| Choix | Justification courte |
|---|---|
| Matrice routes × profils qui échoue si une route manque | O5 reste vrai quand l'application grandit |
| Un témoin pour chaque test de refus | Distingue « la protection refuse » de « le test est mal écrit » |
| Chaque protection retirée volontairement, puis restaurée | Preuve que chaque test détecte vraiment la faille qu'il vise |
| Jeu d'essai rejoué par un script HTTP versionné | Résultats reproductibles par le binôme ou le jury |
| Accès simultanés prouvés par les tests automatisés, pas par HTTP | Par HTTP, on ne peut pas forcer deux transactions à se croiser |
| CSP à nonce plutôt que `unsafe-inline` | Un script injecté ne s'exécute pas ; les `onsubmit` des gabarits sont remplacés par `data-confirmation` |
| Hub Mercure limité au développement | Service du squelette `symfony-docker` inutilisé par l'application : retiré de la production |
| US9 (écran E8) placée au matin de J8 | Décision du binôme : elle active les actions `COMPTE_DESACTIVE` et `ROLE_MODIFIE` du journal, avant le gel du code |

## 4. Démarche qualité : vérifications et écarts

| Vérification ou écart | Résultat |
|---|---|
| Suite complète sur une base de test recréée (drop, create, migrate) | 257 tests, 794 assertions, tous verts, en 20 à 40 secondes |
| Un lancement à 791 assertions juste après la première recréation | Non reproduit : trois lancements suivants, dont un sur une base de nouveau recréée, à 794. À surveiller : un test a probablement une assertion conditionnelle |
| Jeu d'essai de la réservation | 20 cas sur 20 conformes |
| Contrôle d'accès | 20 routes sur 20, 4 profils ; règle `^/admin` retirée : 3 échecs ; route hors matrice : 1 échec |
| Tests de sécurité | 9 protections retirées une à une : chaque fois, au moins un test échoue (plan de tests, section 6) |
| Compteurs de limitation conservés sur disque entre deux lancements | Échecs selon l'ordre des tests ; compteurs remis à zéro au début des tests concernés |
| `composer audit` et `importmap:audit` | Aucune faille connue ; `symfony/http-foundation` en 7.4.23, qui corrige les deux avis du 2026-11-04 |
| Veille : aucun en-tête de sécurité, hub Mercure ouvert | Corrigés le jour même (#74), avec 12 tests |

## 5. Relecture croisée

### Anthony relit J7-B1 et J7-B2 (Chloé)

Le code respecte les tickets #66 et #67, et va au-delà avec les en-têtes de sécurité (#74) : chaque protection a un test et un témoin, les écritures sont vérifiées en base, la veille cite ses sources et donne pour chaque risque une mesure et une preuve. Faits vérifiés : version de `symfony/http-foundation`, migration des droits, commande `app:mongodb:check`, ports sur `127.0.0.1`, redirection HTTP en 308, en-têtes servis par `https://localhost`.

| Remarque | Suite donnée |
|---|---|
| Le README a été reformaté par l'éditeur (colonnes des tableaux alignées) : 689 lignes modifiées pour 69 réellement changées | Rien à corriger ; à éviter pour garder des différences lisibles en relecture (désactiver le formatage automatique des `.md`) |
| Un tiret cadratin dans la veille, contraire à la charte de rédaction | Corrigé (« Sans objet ») |
| Description de l'encodage JSON devenue illisible (`< >`) dans le plan de tests et dans un commentaire du test | Corrigée : `<` et `>` |
| Incohérence des notes J6 sur `$ne` (404 ou 200) | Levée par les tests : `action[$ne]=x` donne 404, `action=$ne` donne 200 avec un message ; les deux notes étaient justes, chacune pour une forme |
| La CSP n'a pas été vérifiée dans un navigateur (certificat local) | À contrôler à la main à J8 : pas d'erreur CSP dans la console sur E4 (appel de l'API) et confirmations d'annulation et de suppression |

### Chloé relit J7-A1 et J7-A2 (Anthony)

PR #70 et #71 relues et fusionnées par Chloé. Points relevés par Anthony dans ses propres livrables, à valider ensemble :

| Point | Suite donnée |
|---|---|
| `/admin` n'est protégée que par `access_control`, les autres pages de gestion ont aussi un `IsGranted` | À aligner à J8 avec l'écran E8, qui touche aussi l'espace administrateur |
| Le jeu d'essai laisse des documents dans le journal de développement | Accepté : le compte applicatif ne peut rien supprimer, l'index TTL les efface au bout d'un an |

## 6. Questions probables à l'entretien

| Question | Éléments de réponse | Source |
|---|---|---|
| Comment savez-vous que chaque page est protégée ? | Une matrice de 20 routes × 4 profils, testée ; un test échoue si une route n'y figure pas | [plan-de-tests.md](../tests/plan-de-tests.md), section 5 |
| Comment savez-vous que vos tests détectent une faille ? | Chaque protection a été retirée volontairement : au moins un test échoue à chaque fois | Plan de tests, sections 5 et 6 |
| Montrez le jeu d'essai de votre fonctionnalité principale | 20 cas, entrée, attendu, obtenu, base et journal ; rejouable par un script | [jeu-essai-reserver.md](../tests/jeu-essai-reserver.md) |
| Comment vous protégez-vous de l'injection SQL ? Et NoSQL ? | Requêtes paramétrées, saisies typées par le formulaire, compte aux droits minimaux ; filtre de E9 converti en enum, tableau refusé | [veille-owasp.md](../securite/veille-owasp.md), section 3 |
| À quoi sert la Content-Security-Policy si Twig échappe déjà ? | Seconde barrière : si un échappement était oublié, le script injecté ne s'exécute pas sans le nonce de la requête | Veille, section 6 |
| Quelle veille faites-vous ? | OWASP Top 10:2025, avis Symfony confrontés aux versions installées, CNIL ; `composer audit` avant chaque fusion de fin de journée | Veille, sections 1 et 4 |
| Que se passe-t-il après 5 mauvais mots de passe ? | Blocage de 15 minutes pour cet email et cette adresse IP, même avec le bon mot de passe ; l'échec est journalisé | `SecuriteTest::testConnexionBloqueeApresCinqEchecs` |

## 7. Captures à faire

- Sortie de `phpunit --testdox` : début de la matrice d'accès et des tests de sécurité (257 tests)
- Sortie du script du jeu d'essai (tableau des 20 cas)
- En-têtes de réponse de `/catalogue` dans les outils de développement du navigateur (CSP, `X-Frame-Options`)
- Console du navigateur sur E4 sans erreur CSP
- `composer audit` et `importmap:audit` sans faille
