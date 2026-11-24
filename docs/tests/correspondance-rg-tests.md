# Correspondance règles de gestion → tests

Tickets : [#45 J5-A2 Tests de ReservationService (RG1 à RG6)](https://github.com/IamArayel/MSP2-PretOutil/issues/45), [#55 J6-B1 Sorties et retours](https://github.com/IamArayel/MSP2-PretOutil/issues/55)

Objectif O5 du cahier des charges : 100 % des règles RG1 à RG6 couvertes par au moins un test qui fait échouer la règle et un test qui la respecte.

Lancer les tests : `docker compose exec php bin/phpunit --testdox` (prérequis : base de test, voir le README, section Tests).

## Fichiers de tests

| Fichier | Type | Base de données |
|---|---|---|
| [tests/Service/ReservationServiceTest.php](../../tests/Service/ReservationServiceTest.php) | Unitaire : une méthode par branche du [diagramme de séquence](../diagrammes/sequence-reserver.md) | Simulée (doubles), horloge fixée au 10/12/2026 |
| [tests/Integration/ReservationServiceIntegrationTest.php](../../tests/Integration/ReservationServiceIntegrationTest.php) | Intégration : service, repository et PostgreSQL | `pretoutil_test`, compte `pretoutil_app`, transaction annulée après chaque test |
| [tests/Security/Voter/ReservationVoterTest.php](../../tests/Security/Voter/ReservationVoterTest.php) | Unitaire : droit d'annuler | Aucune |

## Tableau RG → tests

« Aujourd'hui » vaut le 10/12/2026 dans les tests unitaires.

| Règle | Cas refusé (la règle échoue) | Cas accepté (la règle est respectée) |
|---|---|---|
| **RG1** Fin après le début, début pas dans le passé | `testRG1Periode` : début hier ; fin avant le début → `PeriodeInvalideException` | `testRG1Periode` : début aujourd'hui ; début et fin le même jour |
| **RG2** Pas de chevauchement avec une réservation active | Unitaire : `testRG2PeriodeDejaReservee` → `PeriodeIndisponibleException`<br>Intégration : `testChevauchementDetecteAvantEnregistrement` (bornes incluses : début le jour de fin de l'autre) | Intégration : `testPeriodeDUneReservationAnnuleeReservable` (une réservation annulée ne bloque pas) |
| **RG2** en cas d'accès simultanés | Unitaire : `testRG2ConflitConcurrentTraduitEnRefusMetier` (erreur SQL 23P01 simulée)<br>Intégration : `testContrainteExcludeTraduiteEnPeriodeIndisponible` (vraie contrainte `ex_reservation_chevauchement`, SQLSTATE 23P01) | Unitaire : `testAutreErreurSqlNonTraduite` (une autre erreur SQL n'est pas déguisée en refus métier) |
| **RG3** 14 jours au maximum | `testRG3Duree` : 15 jours → `DureeDepasseeException` | `testRG3Duree` : 14 jours exactement |
| **RG4** 3 réservations actives au maximum | Unitaire : `testRG4QuatriemeReservationRefusee`<br>Intégration : `testQuatriemeReservationActiveRefusee` → `QuotaAtteintException` | Unitaire : `testRG4TroisiemeReservationAcceptee`<br>Intégration : `testReservationAnnuleeNeComptePasDansLeQuota` (2 actives + 1 annulée) |
| **RG4** sous verrou | | `testRG4QuotaCompteSousVerrouDeLAdherent` : transaction, puis verrou sur l'adhérent, puis comptage |
| **RG5** Objet hors service ou désactivé non réservable | `testRG5ObjetNonReservable` : hors service ; retiré du catalogue ; retour en retard de l'emprunteur précédent → `ObjetNonReservableException` | `testRG5ObjetAbimeResteReservable` |
| **RG6** Annulation jusqu'à la veille | `ReservationVoterTest::testAnnuler` : le jour du début ; début passé ; autre adhérent<br>`ReservationServiceTest::testAnnulationImpossible` : en cours, terminée, déjà annulée → `AnnulationImpossibleException` | `ReservationVoterTest::testAnnuler` : la veille du début ; 10 jours avant<br>`ReservationServiceTest::testAnnulerUneReservationConfirmee` |

## Sorties et retours (US7, US8, RG7)

| Règle | Cas refusé | Cas accepté |
|---|---|---|
| **US7** Valider une sortie | `testSortieRefusee` : période qui commence demain ; déjà en cours ; annulée ; objet hors service ; objet pas encore rendu par l'emprunteur précédent → `SortieImpossibleException`, statut inchangé | `testValiderSortie` (période commencée aujourd'hui, statut relu sous verrou), `testValiderSortieDUnePeriodeCommenceeLaVeille` |
| **US8** Enregistrer un retour | `testRetourRefuse` : confirmée ; terminée ; annulée → `RetourImpossibleException`, état de l'objet inchangé | `testEnregistrerRetour` : bon ; abîmé ; objet abîmé rendu en bon état |
| **RG7** Retour hors service | | `testRG7RetourHorsServiceSignaleLesReservationsAVenir` : objet hors service, réservations à venir renvoyées, pas annulées |

## Autres vérifications

| Test | Ce qu'il vérifie |
|---|---|
| `testReservationValideEnregistreeEtJournalisee` | Cas nominal : transaction validée, réservation confirmée, heures ignorées (jours entiers), journalisation `RESERVATION_CREEE` après le `COMMIT`, dernière activité mise à jour (RGPD) |
| `refusAvantTransaction()` (RG1, RG3) | Règles vérifiées avant d'ouvrir la transaction : rien n'est écrit ni journalisé |
| `refusDansTransaction()` (RG2, RG4, RG5) | Refus après ouverture : `ROLLBACK`, pas de `COMMIT`, pas de journalisation |
| `testCompteApplicatif` | Les tests d'intégration tournent avec le compte aux droits minimaux, sur `pretoutil_test` |

## Les tests détectent-ils vraiment une erreur ?

Vérification du 2026-11-24 : chaque règle a été volontairement cassée dans `ReservationService`, puis le code a été restauré.

| Erreur introduite | Test en échec |
|---|---|
| RG1 : début accepté jusqu'à la veille d'aujourd'hui | `testRG1Periode` (début hier) |
| RG3 : durée comptée sans le premier jour (15 jours acceptés) | `testRG3Duree` (15 jours) |
| RG4 : `>` au lieu de `>=` (4e réservation acceptée) | `testRG4QuatriemeReservationRefusee` |
| RG6 : `<=` au lieu de `<` (annulation le jour même) | `ReservationVoterTest::testAnnuler` (le jour du début) |
