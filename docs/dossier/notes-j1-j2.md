# Notes pour le dossier : J1 et J2

Ticket : [#9 J2-D Relecture croisée, PR et mise à jour du README](https://github.com/IamArayel/MSP2-PretOutil/issues/9)

Matière brute pour rédiger le dossier à J9-J10, sans avoir à tout reconstituer. Chaque section renvoie aux fichiers sources.

## 1. Preuves par compétence

| Compétence du CCP 2 | Preuves disponibles | Où |
|---|---|---|
| Analyser les besoins et maquetter | Cahier des charges (US1 à US10, RG1 à RG9), cas d'utilisation, 6 maquettes, enchaînement des écrans | [cahier-des-charges.md](../cahier-des-charges.md), [diagrammes/](../diagrammes/), [maquettes/](../maquettes/) |
| Définir l'architecture logicielle | Schéma d'architecture, sécurité par couche, éco-conception, diagramme de séquence | [architecture.md](../diagrammes/architecture.md), [sequence-reserver.md](../diagrammes/sequence-reserver.md) |
| Concevoir la base relationnelle | MCD, MLD, MPD, contraintes, rôle aux droits minimaux, jeu d'essai 21/21, dictionnaire, RGPD | [conception/](../conception/) |
| Accès aux données SQL et NoSQL | Collection `audit_log` (validateur, TTL, rôle), jeu d'essai 8/8, connexion ODM testée (`app:mongodb:check`) | [audit_log.mongodb.js](../conception/audit_log.mongodb.js), [mpd.md](../conception/mpd.md) section 7 |

La dernière compétence n'a pour l'instant que sa partie conception : le code des repositories et de l'`AuditLogger` arrive à J4-J6.

## 2. Visuels prêts à insérer

| Visuel | Fichier | Partie du dossier |
|---|---|---|
| Diagramme de cas d'utilisation | [cas-utilisation.png](../diagrammes/cas-utilisation.png) | Expression des besoins |
| Maquettes E1, E3, E4, E5, E6, E9 | [maquettes/](../maquettes/) | Maquettage |
| Enchaînement des écrans | [enchainement-ecrans.svg](../diagrammes/enchainement-ecrans.svg) | Maquettage |
| MCD | [mcd.png](../conception/mcd.png) | Base de données |
| MPD | [mpd.png](../conception/mpd.png) | Base de données |
| Diagramme de séquence « Réserver » | [sequence-reserver.png](../diagrammes/sequence-reserver.png) | Architecture |
| Schéma d'architecture | [architecture.png](../diagrammes/architecture.png) | Architecture |

Extraits de code à citer : contrainte `ex_reservation_chevauchement` et `ck_reservation_cycle_de_vie` ([mpd.sql](../conception/mpd.sql)), validateur `$jsonSchema` ([audit_log.mongodb.js](../conception/audit_log.mongodb.js)), tableau du jeu d'essai ([mpd.md](../conception/mpd.md) section 8).

## 3. Choix techniques à justifier

Le détail et la date de chaque décision sont dans le [journal du README](../../README.md#16-journal-des-décisions). Les plus importants pour le jury :

| Choix | Justification courte |
|---|---|
| Symfony 7.4 LTS, `dunglas/symfony-docker` | Version maintenue jusqu'en 2028 ; environnement identique pour les deux membres du binôme, HTTPS local |
| PostgreSQL pour le métier, MongoDB pour l'audit | Intégrité relationnelle d'un côté ; événements de forme variable et durée de conservation automatique de l'autre |
| Réservation comme entité, sortie et retour dans la réservation | Un adhérent peut réserver plusieurs fois le même objet ; relation 1-1 avec le prêt |
| RG2 en service et en contrainte `EXCLUDE` | Message clair dans le cas courant, garantie absolue en cas d'accès simultanés |
| Verrou sur l'adhérent pour RG4 | Deux demandes simultanées ne dépassent pas le quota |
| `VARCHAR` + `CHECK` plutôt qu'`ENUM` | Compatible avec les enums PHP de Doctrine, évolution par simple migration |
| Rôles aux droits minimaux (`pretoutil_app`, `auditAppendOnly`) | Une faille applicative ne permet ni de supprimer des réservations ni de falsifier le journal |
| Journal après le `COMMIT` | On ne journalise que ce qui a eu lieu ; le journal ne bloque pas le service |
| Tailwind via AssetMapper | Pas de Node ; CSS minifié et versionné (éco-conception) |

## 4. Démarche qualité : écarts trouvés et corrigés

Le jury apprécie de voir une démarche de test qui a réellement trouvé quelque chose.

| Écart | Comment il a été trouvé | Correction |
|---|---|---|
| Statut inconnu signalé par une autre contrainte que prévu | Jeu d'essai PostgreSQL, cas 9 | Attendu corrigé : PostgreSQL évalue les `CHECK` par ordre alphabétique |
| Conflit de clé primaire après insertion d'`id` explicites | Jeu d'essai PostgreSQL, cas 17 | Séquences resynchronisées ; décision pour J3 : fixtures sans `id` |
| Identifiant encodé en `long` refusé par MongoDB | Analyse du validateur | Types `int` et `long` acceptés, cas de test ajouté |
| Conception écrite pour PostgreSQL 17, environnement en 16 | Relecture du schéma d'architecture | Jeu d'essai rejoué sur la version 16 (21/21), documents alignés |
| Stratégie de nommage Doctrine mal documentée | Comparaison avec `doctrine.yaml` | Conventions corrigées |
| Installation | Relance complète depuis un clone neuf (ticket #9) | Aucun écart : les 5 étapes et les 5 vérifications passent |

## 5. Questions probables à l'entretien

Chaque membre doit pouvoir répondre seul, y compris sur la partie de l'autre.

| Question | Éléments de réponse | Source |
|---|---|---|
| Pourquoi deux bases de données ? | Section 3 ci-dessus ; le journal n'a pas de jointure et `details` change selon l'action | [architecture.md](../diagrammes/architecture.md) |
| Que se passe-t-il si deux personnes réservent le même objet au même instant ? | Le premier `INSERT` passe, le second est refusé par `EXCLUDE` (SQLSTATE 23P01), traduit en message | [sequence-reserver.md](../diagrammes/sequence-reserver.md) |
| Comment empêchez-vous l'injection SQL et NoSQL ? | Requêtes préparées Doctrine ; aucun opérateur MongoDB construit depuis la saisie ; droits minimaux | [architecture.md](../diagrammes/architecture.md) section 1 |
| Pourquoi pas d'entité Prêt ? | Relation 1-1 avec la réservation : une table de plus sans information nouvelle | [mcd.md](../conception/mcd.md) |
| Comment respectez-vous le RGPD ? | Minimisation (pas d'IP), durées de conservation, anonymisation, TTL du journal | [dictionnaire-donnees.md](../conception/dictionnaire-donnees.md) |
| Quelles mesures d'éco-conception ? | Pagination, pas de polling, index, assets minifiés, pas de police externe | [architecture.md](../diagrammes/architecture.md) section 4 |
| Où est vérifiée chaque règle de gestion ? | Tableau des RG avec la couche responsable | [cahier-des-charges.md](../cahier-des-charges.md), [sequence-reserver.md](../diagrammes/sequence-reserver.md) section 3 |

## 6. À capturer pendant le développement

- J3 : migration avec `btree_gist` et `EXCLUDE`, écran de connexion avec blocage après 5 échecs.
- J5 : message de refus sur E4 (quota atteint, période indisponible).
- J6 : un document `audit_log` réel dans MongoDB, page du journal.
- J7 : sortie des tests unitaires et du jeu d'essai de la fonctionnalité « Réserver ».
