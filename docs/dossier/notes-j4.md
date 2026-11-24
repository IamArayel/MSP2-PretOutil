# Notes pour le dossier : J4

Ticket : [#37 J4-D Relecture croisée, PR et mise à jour du README](https://github.com/IamArayel/MSP2-PretOutil/issues/37)

Suite des [notes J3](notes-j3.md). J4 ouvre le catalogue aux visiteurs (bloc A) et sa gestion aux gestionnaires, avec un contrôle d'accès métier par Voters (bloc B).

## 1. Preuves par compétence

| Compétence du CCP 2 | Preuves ajoutées à J4 | Où |
|---|---|---|
| Analyser les besoins et maquetter | Écrans E3 et E4 réalisés d'après les maquettes, E7 d'après la charte | [templates/catalogue/](../../templates/catalogue/), [templates/gestion_catalogue/](../../templates/gestion_catalogue/) |
| Définir l'architecture logicielle | Contrôleurs fins, services (`CatalogueService`, `ObjetService`), objets de lecture (`Page`, `PageCatalogue`, `FicheObjet`) indépendants de Doctrine, Voters | [src/Service/](../../src/Service/), [src/Catalogue/](../../src/Catalogue/), [src/Security/Voter/](../../src/Security/Voter/) |
| Concevoir et mettre en place la base relationnelle | Index partiel `idx_reservation_dates_actives` justifié par `EXPLAIN` | [Version20261124044037.php](../../migrations/Version20261124044037.php), [requetes-catalogue.md](../conception/requetes-catalogue.md) |
| Accès aux données SQL | Pagination, filtres combinables, requête de disponibilité en `NOT EXISTS`, requêtes groupées pour éviter une requête par ligne | [ObjetRepository](../../src/Repository/ObjetRepository.php), [ReservationRepository](../../src/Repository/ReservationRepository.php) |
| Sécurité (transverse) | Voters testés (31 tests), CSRF propre à chaque action et objet, paramètres liés, validation des filtres | [tests/Security/Voter/](../../tests/Security/Voter/), [GestionCatalogueController](../../src/Controller/GestionCatalogueController.php) |

## 2. Extraits de code à citer

| Extrait | Fichier | Ce qu'il montre |
|---|---|---|
| `ObjetRepository::filtrerDisponibles()` | [ObjetRepository.php](../../src/Repository/ObjetRepository.php) | RG2 et RG5 en DQL : `NOT EXISTS`, bornes incluses, paramètres liés |
| Requête SQL générée et plan d'exécution | [requetes-catalogue.md](../conception/requetes-catalogue.md), sections 2 et 4 | Ce que Doctrine envoie à PostgreSQL ; 25 ms sans index, 7 ms avec |
| `ReservationRepository::statutsActifsAuJour()` | [ReservationRepository.php](../../src/Repository/ReservationRepository.php) | Une requête pour toute une page au lieu d'une par objet |
| `CatalogueService::statutDuJour()` | [CatalogueService.php](../../src/Service/CatalogueService.php) | Donnée dérivée calculée, jamais stockée |
| `ObjetVoter` | [ObjetVoter.php](../../src/Security/Voter/ObjetVoter.php) | Droit métier qui dépend des données : suppression physique seulement si l'objet n'a jamais été réservé |
| `ReservationVoter::estAnnulable()` | [ReservationVoter.php](../../src/Security/Voter/ReservationVoter.php) | RG6 avec une horloge injectée, testable à date fixe |
| `GestionCatalogueController::jetonValide()` | [GestionCatalogueController.php](../../src/Controller/GestionCatalogueController.php) | Jeton CSRF lié à l'action et à l'objet |
| `ObjetService::supprimer()` | [ObjetService.php](../../src/Service/ObjetService.php) | Clé étrangère en dernier recours si une réservation arrive entre-temps |

## 3. Choix techniques à justifier

Le détail et la date de chaque décision sont dans le [journal du README](../../README.md#16-journal-des-décisions).

| Choix | Justification courte |
|---|---|
| Filtre de disponibilité aligné sur la contrainte `EXCLUDE` | Le catalogue n'annonce disponible que ce que la base acceptera |
| Index partiel sur les réservations actives | Mesuré avec `EXPLAIN` sur 200 000 réservations ; l'index reste petit car l'historique n'y figure pas |
| Repositories qui renvoient une `Page` | Les vues ne dépendent pas de l'ORM ; la même classe sert à E3, E7 et bientôt E9 |
| Filtres en GET sans CSRF, actions de gestion en POST avec CSRF | Une recherche ne modifie rien et se partage ; une action qui modifie des données exige un jeton |
| Voters en plus de `access_control` | Le chemin protège l'écran ; le Voter décide selon la donnée (objet déjà réservé, propriétaire, veille du début) |
| Suppression logique par défaut, physique seulement sans historique | Garder l'historique des prêts ; la clé étrangère `RESTRICT` garantit la règle |

## 4. Démarche qualité : vérifications et écarts

| Vérification ou écart | Résultat |
|---|---|
| Requêtes du catalogue sur les fixtures | 19 cas conformes, rejoués après la fusion des deux blocs ([requetes-catalogue.md](../conception/requetes-catalogue.md), section 3) |
| Plan d'exécution | Lecture complète de `reservation` détectée, corrigée par l'index partiel |
| Index partiel et Doctrine | PostgreSQL réécrit la condition : migration et mapping adaptés pour que Doctrine ne voie pas d'écart |
| Tests unitaires des Voters | 31 tests, 31 conformes |
| Conflit de fusion sur `ReservationRepository` | Une fin de méthode perdue à la fusion, corrigée par un commit dédié ; vérifiée par la relance des 19 cas |
| Parcours complet par rôle | 23 contrôles conformes (section 5) |

## 5. Parcours complet du 2026-11-24

Fait par requêtes HTTP sur les fixtures, avec les comptes de test, puis fixtures rechargées.

| Profil | Contrôles | Résultat |
|---|---|---|
| Visiteur | Accueil, catalogue, détail accessibles ; E7, mes réservations et une action POST redirigent vers la connexion | Conforme |
| Compte désactivé | Connexion refusée (RG9) | Conforme |
| Adhérent | Catalogue, détail, mes réservations accessibles ; E7 et ses actions refusées (403) ; lien E7 absent de la barre | Conforme |
| Gestionnaire | Liste E7 paginée avec les objets désactivés ; bouton « Supprimer » absent pour les 10 objets déjà réservés de la page 1 ; création invalide (422 et message) ; création sans jeton refusée ; création, modification, désactivation (objet retiré du catalogue public, 404) ; jeton d'un autre objet refusé ; suppression d'un objet réservé refusée (403) ; suppression d'un objet jamais réservé ; GET sur une action POST (405) | Conforme |
| Administrateur | Accès à E7 par la hiérarchie des rôles | Conforme |

Non vérifié : le rendu visuel et la navigation au clavier, car le certificat local bloque les navigateurs automatisés. À faire à la main avant les captures.

## 6. Relecture du bloc B par Anthony

Le code est conforme au ticket : Voters testés, contrôleur sans logique métier, CSRF propre à chaque action, DTO validé selon le dictionnaire, une seule requête par page pour les objets déjà réservés. Remarques pour les jours suivants :

| Remarque | Proposition |
|---|---|
| La suppression physique n'est pas journalisée (pas d'action prévue dans `audit_log`) | Ajouter `OBJET_SUPPRIME` à J6, avec l'`AuditLogger` réel (enum et validateur MongoDB) |
| Désactiver un objet ou le passer hors service ne prévient pas s'il a des réservations à venir | À trancher avec le point ouvert du retour en retard, à J5 ou J6 |
| Un objet désactivé ne peut pas être réactivé depuis E7 | Non exigé par US6 ; à ajouter si le temps le permet |
| « Désactiver » un objet déjà désactivé affiche quand même le message de succès | Détail d'affichage, sans effet sur les données |

## 7. Questions probables à l'entretien

| Question | Éléments de réponse | Source |
|---|---|---|
| Comment savez-vous qu'un objet est disponible sur une période ? | `NOT EXISTS` sur les réservations actives qui chevauchent la période, bornes incluses, même règle que la contrainte `EXCLUDE` | [requetes-catalogue.md](../conception/requetes-catalogue.md) |
| Pourquoi avoir ajouté un index ? | Mesure avec `EXPLAIN` sur un volume réaliste : lecture complète de la table sans index | Section 4 de ce document |
| Différence entre `access_control` et un Voter ? | Le premier protège un chemin selon le rôle, le second décide selon la donnée concernée | [ObjetVoter.php](../../src/Security/Voter/ObjetVoter.php) |
| Comment testez-vous RG6 sans attendre la veille ? | Horloge injectée dans `ReservationVoter`, fixée dans les tests | [ReservationVoterTest.php](../../tests/Security/Voter/ReservationVoterTest.php) |
| Pourquoi pas de jeton CSRF sur les filtres ? | Une recherche en GET ne modifie rien ; toutes les actions qui modifient sont en POST avec jeton | [FiltreCatalogueType.php](../../src/Form/FiltreCatalogueType.php) |
| Pourquoi ne pas supprimer un objet déjà réservé ? | Garder l'historique des prêts ; la clé étrangère `RESTRICT` refuserait de toute façon | [ObjetService.php](../../src/Service/ObjetService.php) |

## 8. Captures à faire

- E3 Catalogue sans filtre, puis filtré sur une catégorie et une période
- E4 Détail : un objet avec indisponibilités, un objet hors service, la vue visiteur
- E7 Gestion du catalogue : liste, formulaire avec erreurs, message après désactivation
- Sortie de `phpunit --testdox` (31 tests)
- Extrait de `EXPLAIN` avant et après l'index (section 4 de [requetes-catalogue.md](../conception/requetes-catalogue.md))
