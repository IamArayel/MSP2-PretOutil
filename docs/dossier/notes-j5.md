# Notes pour le dossier : J5

Ticket : [#47 J5-D Relecture croisée, PR et mise à jour du README](https://github.com/IamArayel/MSP2-PretOutil/issues/47)

Suite des [notes J4](notes-j4.md). J5 réalise la fonctionnalité centrale, réserver un objet (US4) : le service métier et ses exceptions (bloc A, Anthony), ses tests et le formulaire de l'écran E4 (Chloé). La journée a aussi ajouté au cahier des charges une API REST interne, réalisée à J6.

## 1. Preuves par compétence

| Compétence du CCP 2 | Preuves ajoutées à J5 | Où |
|---|---|---|
| Analyser les besoins et maquetter | Formulaire de réservation de E4 d'après la maquette ; US11 et API REST ajoutées au cahier des charges (v1.2) | [templates/catalogue/objet.html.twig](../../templates/catalogue/objet.html.twig), [cahier des charges, section 6.5](../cahier-des-charges.md) |
| Définir l'architecture logicielle | Contrôleur sans règle métier, service qui porte toutes les règles, exceptions métier ; diagramme de séquence aligné sur le code | [ReservationService](../../src/Service/ReservationService.php), [sequence-reserver.md](../diagrammes/sequence-reserver.md) |
| Concevoir et mettre en place la base relationnelle | Index partiel `idx_reservation_en_cours`, contrainte `EXCLUDE` vérifiée par un test d'intégration | [Version20261124054944.php](../../migrations/Version20261124054944.php) |
| Accès aux données SQL | Verrou `SELECT ... FOR UPDATE`, transaction, comptage, chevauchement, retour en retard ; requête du catalogue réécrite et mesurée | [ReservationRepository](../../src/Repository/ReservationRepository.php), [requetes-catalogue.md](../conception/requetes-catalogue.md) |
| Sécurité et qualité (transverse) | 58 tests (unitaires et intégration), 100 % de RG1 à RG6 couvertes (critère O5) ; CSRF, accès réservé aux adhérents | [correspondance-rg-tests.md](../tests/correspondance-rg-tests.md) |

## 2. Extraits de code à citer

| Extrait | Fichier | Ce qu'il montre |
|---|---|---|
| `ReservationService::reserver()` | [ReservationService.php](../../src/Service/ReservationService.php) | Ordre des règles, transaction, verrou, traduction de l'erreur `23P01` |
| `ReservationRefuseeException` et ses 6 sous-classes | [src/Exception/](../../src/Exception/) | Un refus par règle, un message affichable, une seule classe à intercepter |
| `CatalogueController::reserver()` | [CatalogueController.php](../../src/Controller/CatalogueController.php) | Le contrôleur transmet et affiche, sans connaître les règles ; redirection 303 |
| `ReservationRepository::verrouillerAdherent()` | [ReservationRepository.php](../../src/Repository/ReservationRepository.php) | Verrou pessimiste Doctrine pour RG4 |
| `testRG2ConflitConcurrentTraduitEnRefusMetier` et `testContrainteExcludeTraduiteEnPeriodeIndisponible` | [tests/](../../tests/) | Le même risque testé deux fois : erreur simulée, puis vraie contrainte PostgreSQL |
| Tableau RG → tests | [correspondance-rg-tests.md](../tests/correspondance-rg-tests.md) | Chaque règle a un test qui la fait échouer et un qui la respecte |

## 3. Choix techniques à justifier

Le détail et la date de chaque décision sont dans le [journal du README](../../README.md#16-journal-des-décisions).

| Choix | Justification courte |
|---|---|
| Un objet dont le retour est en retard n'est pas réservable | Décision du binôme : l'objet n'est pas là ; même règle dans le service et dans le catalogue |
| Une exception par règle, sous une classe mère | Le contrôleur affiche le refus sans connaître la règle ; les tests distinguent chaque cas |
| RG1 et RG3 avant la transaction, le reste dedans | Les premières ne dépendent que de la saisie ; les autres lisent des données qui peuvent changer |
| Verrou sur l'adhérent avant de compter | Sans lui, deux demandes simultanées dépasseraient le quota |
| Transaction sur la connexion, pas `wrapInTransaction()` | `wrapInTransaction()` ferme l'EntityManager au premier refus métier |
| Validation dans le formulaire et dans le service | Le formulaire répond tout de suite ; le service reste la référence, quel que soit le point d'entrée |
| Tests unitaires et tests d'intégration | Les unitaires vérifient la logique vite ; l'intégration vérifie ce que seul PostgreSQL garantit (`EXCLUDE`, droits de `pretoutil_app`) |
| API REST interne plutôt qu'externe | Elle sert le métier existant sans dépendre d'un tiers (réalisée à J6, #49) |

## 4. Démarche qualité : vérifications et écarts

| Vérification ou écart | Résultat |
|---|---|
| Service vérifié sur les fixtures | 16 cas conformes ([reservation-service.md](../conception/reservation-service.md), section 4) |
| Jeu d'essai du service, premier passage | 3 cas refusés par le quota au lieu de RG2 : le jeu d'essai réutilisait des comptes déjà au quota ; corrigé, le service était juste |
| Catalogue après la règle du retard | 20 cas conformes ; une première version de la requête passait de 6 à 16 ms, puis à 83 ms : index partiel ajouté, 4,6 ms |
| Mesures faussées | Le script `EXPLAIN` laissait 1,4 million de lignes mortes ; `VACUUM ANALYZE` ajouté avant et après |
| Tests automatisés | 58 tests, 118 assertions, tous verts (après création de la base de test, voir section 6) |
| Parcours complet par HTTP | 17 contrôles conformes (section 5) |

## 5. Parcours de réservation du 2026-11-24

Fait par requêtes HTTP sur les fixtures, avec les comptes de test, puis fixtures rechargées.

| Profil | Contrôle | Résultat |
|---|---|---|
| Visiteur | E4 sans formulaire, invitation à se connecter ; POST de réservation redirigé vers la connexion | Conforme |
| Adhérent | Formulaire affiché (dates, jeton, bouton) | Conforme |
| Adhérent | Dates vides ; fin avant début ; début hier : 422 et message sous le champ | Conforme |
| Adhérent | 15 jours (RG3) ; retour en retard ; chevauchement (RG2) : 422 et message du service | Conforme |
| Adhérent | Objet hors service : formulaire absent ; envoi forcé refusé (422), rien en base | Conforme |
| Adhérent | Objet désactivé : 404 ; sans jeton CSRF : 422, rien en base | Conforme |
| Adhérent | Réservation valide : 303 vers « Mes réservations », message de confirmation, réservation en base | Conforme |
| Adhérent | Renvoi du même formulaire (double clic) : refusé, une seule réservation en base | Conforme |
| Adhérent | 4e réservation active (RG4), pour Camille puis pour le compte « quota » : 422 et message | Conforme |
| Gestionnaire | Réservation acceptée (hiérarchie des rôles) | Conforme |

Non vérifié : le rendu visuel et la navigation au clavier, car le certificat local bloque les navigateurs automatisés.

## 6. Relecture du bloc B et des tests par Anthony

Le code respecte les tickets #45 et #46 : contrôleur sans règle métier, DTO validé, jeton CSRF propre au formulaire, refus affichés tels quels, redirection 303 après succès, tests qui couvrent chaque branche du diagramme de séquence, y compris le verrou et l'absence de `COMMIT` en cas de refus. Remarques :

| Remarque | Proposition |
|---|---|
| Les tests d'intégration échouent tant que la base `pretoutil_test` n'existe pas sur la machine | Procédure du README suivie telle quelle : elle fonctionne. À faire une fois par poste, et `migrate` en environnement de test après chaque nouvelle migration |
| Objet hors service : un envoi forcé affiche le panneau « hors service » et non le message du service | Même information pour l'adhérent ; acceptable |
| Les `use` de `CatalogueController` ne sont pas triés | Détail de style, à corriger au passage |
| L'écran E5 « Mes réservations » est encore un emplacement | Prévu à J6 : la redirection 303 y mène déjà, avec le message de confirmation |

## 7. Questions probables à l'entretien

| Question | Éléments de réponse | Source |
|---|---|---|
| Que se passe-t-il si deux adhérents réservent le même objet au même instant ? | Le service vérifie RG2, mais les deux peuvent passer la vérification ; la contrainte `EXCLUDE` refuse le second `INSERT` (`23P01`), traduit en message clair | [reservation-service.md](../conception/reservation-service.md) |
| Et si le même adhérent envoie deux demandes en même temps ? | Verrou `FOR UPDATE` sur sa ligne : la seconde attend la première, puis compte 3 réservations | [ReservationService.php](../../src/Service/ReservationService.php) |
| Pourquoi valider les dates deux fois ? | Le formulaire répond vite ; le service protège aussi les autres points d'entrée (API, commandes, tests) | Section 3 |
| Comment le contrôleur affiche-t-il un refus sans connaître la règle ? | Il intercepte `ReservationRefuseeException` et affiche son message | [CatalogueController.php](../../src/Controller/CatalogueController.php) |
| Comment testez-vous une erreur qui n'arrive qu'en accès simultanés ? | En unitaire, erreur `23P01` simulée ; en intégration, repository qui ne voit pas le chevauchement, et vraie contrainte PostgreSQL | [correspondance-rg-tests.md](../tests/correspondance-rg-tests.md) |
| Pourquoi un index de plus ? | Mesuré : la règle du retard faisait passer la requête du catalogue de 6 à 16 ms ; avec l'index, 4,6 ms | [requetes-catalogue.md](../conception/requetes-catalogue.md), section 4 |
| Pourquoi une API, et pourquoi interne ? | Consigne de l'épreuve ; elle sert la réservation (disponibilité avant l'envoi) sans dépendre d'un service tiers | [cahier des charges, section 6.5](../cahier-des-charges.md) |

## 8. Captures à faire

- E4 : formulaire vide, puis chaque message de refus (dates, 14 jours, quota, période indisponible, retour en retard)
- Message « Réservation confirmée » sur « Mes réservations »
- Sortie de `phpunit --testdox` (58 tests)
- Diagramme de séquence mis à jour ([sequence-reserver.png](../diagrammes/sequence-reserver.png))
- Extrait d'`EXPLAIN` avec les deux index partiels ([requetes-catalogue.md](../conception/requetes-catalogue.md), section 4)
