# Notes pour le dossier : J6

Ticket : [#57 J6-D Relecture croisée, PR et mise à jour du README](https://github.com/IamArayel/MSP2-PretOutil/issues/57)

Suite des [notes J5](notes-j5.md). J6 termine le cycle de vie d'une réservation et la partie NoSQL : écran E5 et annulation, API REST des disponibilités (Anthony), sorties et retours sur E6, journal d'audit MongoDB et écran E9 (Chloé). Toutes les user stories « Must » sont maintenant réalisées.

## 1. Preuves par compétence

| Compétence du CCP 2 | Preuves ajoutées à J6 | Où |
|---|---|---|
| Analyser les besoins et maquetter | Écrans E5, E6 et E9 d'après les maquettes | [templates/](../../templates/) |
| Définir l'architecture logicielle | API REST dans la couche présentation, erreurs JSON centralisées, schéma d'architecture mis à jour ; transitions du cycle de vie dans `ReservationService` | [api.md](../conception/api.md), [architecture.md](../diagrammes/architecture.md) |
| Concevoir et mettre en place la base relationnelle | Cycle de vie complet (confirmée, en cours, terminée, annulée) écrit par l'application et garanti par `ck_reservation_cycle_de_vie` | [ReservationService](../../src/Service/ReservationService.php) |
| Accès aux données SQL | Listes de E5 et E6 en une requête avec jointure, sorties à valider, retours en retard par l'index `idx_reservation_en_cours` | [ReservationRepository](../../src/Repository/ReservationRepository.php) |
| **Accès aux données NoSQL** | Document ODM `AuditLog` conforme au validateur `$jsonSchema`, `AuditLogger` en écriture seule avec le compte `pretoutil_app`, requête paginée et filtrée de E9, validateur mis à jour par `collMod` | [src/Document/](../../src/Document/), [AuditLogger](../../src/Service/AuditLogger.php), [AuditLogRepository](../../src/Repository/AuditLogRepository.php), [audit_log.mongodb.js](../conception/audit_log.mongodb.js) |
| Sécurité et qualité (transverse) | 98 tests (320 assertions) ; API en lecture seule, débit limité, sans donnée personnelle ; journal non modifiable par l'application | [tests/](../../tests/) |

## 2. Extraits de code à citer

| Extrait | Fichier | Ce qu'il montre |
|---|---|---|
| `AuditLog` et ses documents embarqués `ActeurAudit`, `CibleAudit` | [src/Document/](../../src/Document/) | Mapping ODM d'une collection au schéma contrôlé ; document en lecture seule |
| `AuditLogger::enregistrer()` | [AuditLogger.php](../../src/Service/AuditLogger.php) | Une panne de MongoDB est tracée dans les logs sans annuler l'action métier |
| `AuditLogRepository::lister()` | [AuditLogRepository.php](../../src/Repository/AuditLogRepository.php) | Requête MongoDB : filtre par une valeur de l'enum (aucun opérateur issu de la saisie), tri, pagination, index utilisés |
| `ConnexionEchoueeListener` | [src/EventListener/](../../src/EventListener/ConnexionEchoueeListener.php) | Journaliser un échec de connexion sans révéler si le compte existe |
| `ReservationService::validerSortie()` et `enregistrerRetour()` | [ReservationService.php](../../src/Service/ReservationService.php) | Transitions du cycle de vie, RG7, réservations à venir signalées |
| `DisponibiliteController` et `ApiExceptionSubscriber` | [src/Controller/Api/](../../src/Controller/Api/), [src/EventSubscriber/](../../src/EventSubscriber/) | API REST : validation, limitation de débit, erreurs JSON |
| `MesReservationsController::annuler()` | [MesReservationsController.php](../../src/Controller/MesReservationsController.php) | Voter, jeton CSRF propre à la réservation, service |

## 3. Choix techniques à justifier

Le détail et la date de chaque décision sont dans le [journal du README](../../README.md#16-journal-des-décisions).

| Choix | Justification courte |
|---|---|
| Journal d'audit dans MongoDB, écrit après le `COMMIT` | On ne journalise que ce qui a eu lieu ; `details` varie selon l'action |
| Panne de MongoDB : action métier conservée, erreur dans les logs | Le journal ne doit pas empêcher le service rendu à l'adhérent |
| Compte MongoDB limité à `find` et `insert` | Une faille applicative ne permet ni de modifier ni d'effacer le journal |
| Action `OBJET_SUPPRIME` ajoutée, validateur mis à jour par `collMod` | Remarque de relecture de J4 : toute action sensible est journalisée (RG8), sans toucher aux documents existants |
| Retour « hors service » : réservations à venir signalées, pas annulées | Le gestionnaire prévient l'adhérent ; une annulation automatique serait une décision métier non demandée |
| API interne, réutilisant `ReservationService` | Une seule écriture des règles ; l'API informe, la réservation revérifie tout |
| E5 dans un contrôleur dédié | Une responsabilité par contrôleur, pas de conflit avec E6 |

## 4. Démarche qualité : vérifications et écarts

| Vérification ou écart | Résultat |
|---|---|
| Tests automatisés | 98 tests, 320 assertions, tous verts (PostgreSQL et MongoDB de test) |
| API | 14 tests fonctionnels, et vérification par `curl` jusqu'au 429 |
| Test du 429 en échec alors que la limite fonctionnait | Symfony vide les services en mémoire entre deux requêtes d'un test : compteurs déplacés sur disque en test |
| Zone `aria-live` masquée par `display: none` | Certains lecteurs d'écran ne la surveillent pas : la zone reste dans la page, seul son contenu change |
| Gabarit modifié mais pas affiché | FrankenPHP garde l'application en mémoire en mode worker : `cache:clear` documenté dans le README |
| Message de retard ambigu sur E5 | « à rendre depuis le 24 » remplacé par « à rendre le 23 au plus tard » |
| Parcours complet du cycle de vie | 21 contrôles conformes (section 5) |

## 5. Parcours complet du 2026-11-24

Fait par requêtes HTTP sur les fixtures, avec les comptes de test, puis fixtures rechargées. Chaque action doit produire exactement un document dans `audit_log`.

| Étape | Résultat | Document attendu sur E9 |
|---|---|---|
| Inscription d'un nouveau compte | Conforme | `COMPTE_CREE` |
| Connexion avec un mauvais mot de passe | Refusée | `CONNEXION_ECHOUEE` |
| Réservation du Vidéoprojecteur depuis E4 | 303 | `RESERVATION_CREEE` |
| Annulation de la Tente depuis E5 | Annulée | `RESERVATION_ANNULEE` |
| Adhérente : E6 et E9 | 403 et 403 | |
| Sortie de l'Enceinte validée sur E6 | En cours | `SORTIE_VALIDEE` |
| Retour du Vidéoprojecteur « hors service » | Terminée, objet hors service, réservation à venir de Camille signalée au gestionnaire | `RETOUR_ENREGISTRE` |
| API sur le Vidéoprojecteur après le retour | `disponible: false` | |
| Création puis suppression d'un objet jamais réservé (E7) | Créé, puis supprimé | `OBJET_CREE`, `OBJET_SUPPRIME` |
| Gestionnaire : E9 | 403 | |
| Administrateur : E9 filtré sur chacune des 8 actions | Une entrée par action | |
| Collection `audit_log` | 8 documents, un par action | |
| E9 filtré avec une valeur inventée (`$ne`) | 200, liste complète, aucune erreur | |

Non vérifié : le rendu visuel et la navigation au clavier, car le certificat local bloque les navigateurs automatisés.

Les 8 documents du parcours restent dans le journal de la base de développement : le compte applicatif ne peut rien supprimer, et le rechargement des fixtures ne touche pas MongoDB. L'index TTL les effacera au bout d'un an.

## 6. Relecture des blocs B par Anthony

Le code respecte les tickets #55 et #56 : transitions dans le service avec exceptions métier, actions en POST avec jeton CSRF propre à chaque réservation, journal écrit après le `COMMIT` et jamais bloquant, filtre de E9 limité aux valeurs de l'enum, tests unitaires et d'intégration sur une base MongoDB de test. Remarques :

| Remarque | Proposition |
|---|---|
| Une ligne du journal des décisions dit encore « suppression non journalisée » | Dépassée par `OBJET_SUPPRIME` : une nouvelle ligne le signale |
| `COMPTE_DESACTIVE` et `ROLE_MODIFIE` ne sont jamais écrites | Prévues pour l'écran E8 (US9, « Should »), non planifié |
| Le message de retour « hors service » montre l'email de l'adhérent au gestionnaire | Nécessaire pour le prévenir ; réservé à `ROLE_MANAGER` |
| Pagination de E9 par `skip` | Suffisant pour le volume d'un an d'une association (TTL) |

## 7. Questions probables à l'entretien

| Question | Éléments de réponse | Source |
|---|---|---|
| Pourquoi MongoDB pour le journal ? | Suite d'événements sans jointure, `details` variable, durée de conservation par index TTL, compte en ajout seul | [architecture.md](../diagrammes/architecture.md) |
| Comment le schéma MongoDB est-il contrôlé ? | Validateur `$jsonSchema` strict côté base, document ODM côté application ; un test vérifie qu'un document invalide est refusé | [audit_log.mongodb.js](../conception/audit_log.mongodb.js) |
| Que se passe-t-il si MongoDB tombe ? | L'action métier est conservée, l'erreur est tracée dans les logs | [AuditLogger.php](../../src/Service/AuditLogger.php) |
| Comment empêchez-vous l'injection NoSQL ? | Filtre converti en enum avant la requête : une valeur inventée comme `$ne` est ignorée | [JournalAuditController.php](../../src/Controller/JournalAuditController.php) |
| Que renvoie l'API et comment est-elle protégée ? | JSON en lecture seule, sans donnée personnelle, 30 appels par minute, erreurs génériques | [api.md](../conception/api.md) |
| Que se passe-t-il quand un objet revient hors service ? | Il n'est plus réservable (RG7) ; ses réservations à venir sont signalées au gestionnaire | [ReservationService.php](../../src/Service/ReservationService.php) |
| Qui peut annuler une réservation, et jusqu'à quand ? | Son adhérent, jusqu'à la veille du début (RG6), décidé par un Voter testé | [ReservationVoter.php](../../src/Security/Voter/ReservationVoter.php) |

## 8. Captures à faire

- E5 : liste avec chaque statut, message après annulation
- E4 : message de l'API après saisie des dates (disponible, puis refusé)
- E6 : sorties à valider, retours en retard, message après un retour hors service
- E9 : journal complet, puis filtré sur une action
- Réponse JSON de l'API dans le navigateur, et une erreur 400
- Document `audit_log` dans MongoDB Compass
- Sortie de `phpunit --testdox` (98 tests)
