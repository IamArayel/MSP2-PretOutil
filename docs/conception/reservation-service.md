# ReservationService : règles de réservation

Ticket : [#44 J5-A1 ReservationService et exceptions métier (RG1 à RG6)](https://github.com/IamArayel/MSP2-PretOutil/issues/44)

Cœur métier de l'application (US4, US5). Le déroulé suit le [diagramme de séquence « Réserver un objet »](../diagrammes/sequence-reserver.md), mis à jour après l'implémentation.

## 1. Interface

| Méthode | Rôle | Exceptions |
|---|---|---|
| `reserver(Utilisateur $adherent, Objet $objet, \DateTimeImmutable $debut, \DateTimeImmutable $fin): Reservation` | Enregistre une réservation confirmée après avoir vérifié RG1 à RG5 | Voir section 2 |
| `annuler(Reservation $reservation, Utilisateur $adherent): void` | Passe une réservation confirmée au statut annulée | `AnnulationImpossibleException` |

Les droits sont vérifiés avant l'appel, par le contrôleur : adhérent connecté (`ROLE_USER`) pour réserver, `ReservationVoter::ANNULER` (propriétaire, jusqu'à la veille du début, RG6) pour annuler. Le service vérifie les règles de gestion.

## 2. Règles et exceptions

Toutes les exceptions héritent de `App\Exception\ReservationRefuseeException`. Leur message est destiné à l'adhérent : le contrôleur l'affiche tel quel, sans connaître la règle en cause.

| Ordre | Règle | Exception | Message affiché |
|---|---|---|---|
| 1 | RG1 : fin après début | `PeriodeInvalideException` | La date de fin doit être après la date de début. |
| 2 | RG1 : début aujourd'hui au plus tôt | `PeriodeInvalideException` | La réservation ne peut pas commencer dans le passé. |
| 3 | RG3 : 14 jours au plus, premier et dernier jour compris | `DureeDepasseeException` | Une réservation dure 14 jours au maximum. |
| 4 | RG5 : objet actif | `ObjetNonReservableException` | Cet objet ne fait plus partie du catalogue. |
| 5 | RG5 : objet pas hors service | `ObjetNonReservableException` | Cet objet est hors service : il ne peut pas être réservé. |
| 6 | Objet rendu par l'emprunteur précédent (décision du 2026-11-24) | `ObjetNonReservableException` | Cet objet n'a pas encore été rendu par l'emprunteur précédent... |
| 7 | RG4 : 3 réservations actives au plus, sous verrou | `QuotaAtteintException` | Vous avez déjà 3 réservations en cours ou à venir : c'est le maximum. |
| 8 | RG2 : pas de chevauchement (requête) | `PeriodeIndisponibleException` | Cet objet est déjà réservé sur une partie de cette période. Choisissez d'autres dates. |
| 9 | RG2 en accès simultanés : contrainte `EXCLUDE` (SQLSTATE `23P01`) | `PeriodeIndisponibleException` | Même message |
| | Annulation d'une réservation qui n'est plus confirmée | `AnnulationImpossibleException` | Cette réservation ne peut plus être annulée. |

Les règles 1 à 3 ne dépendent que de la demande : elles sont vérifiées avant d'ouvrir la transaction. Les règles 4 à 9 sont vérifiées dans la transaction.

## 3. Choix de conception

| Choix | Raison |
|---|---|
| Une exception par règle, une classe mère commune | Le contrôleur intercepte la classe mère ; les tests et le dossier distinguent chaque règle |
| Transaction ouverte sur la connexion, pas `wrapInTransaction()` | `wrapInTransaction()` ferme l'EntityManager à la moindre exception, y compris un refus métier ; l'écran E4 doit pouvoir s'afficher ensuite |
| Verrou `SELECT ... FOR UPDATE` sur l'adhérent avant de compter (RG4) | Deux demandes simultanées du même adhérent passent l'une après l'autre |
| Erreur `23P01` traduite en `PeriodeIndisponibleException` | La contrainte `EXCLUDE` couvre la course entre deux adhérents ; l'adhérent voit le même message que pour un chevauchement ordinaire |
| Journalisation après le `COMMIT` | On ne journalise que ce qui a été enregistré (RG8) |
| `derniere_activite_le` mise à jour à chaque réservation et annulation | Point de départ de la durée de conservation de 3 ans (RGPD, dictionnaire de données) |
| Horloge injectée (`Psr\Clock\ClockInterface`) | « Aujourd'hui » se fixe dans les tests (J5-A2) |
| Durée comptée premier et dernier jour compris | Du 12 au 25 = 14 jours : la règle telle que l'adhérent la comprend |

Le filtre de disponibilité du catalogue applique les mêmes règles RG2, RG5 et retour en retard ([requetes-catalogue.md](requetes-catalogue.md)).

## 4. Vérification sur les fixtures : 16 cas, 16 conformes

Exécutée le 2026-11-24 avec [reservation_service_tests.php](reservation_service_tests.php) sur la base de développement, puis fixtures rechargées. J désigne le jour du chargement. Les tests unitaires de J5-A2 reprendront ces cas.

| N° | Cas | Attendu | Obtenu |
|---|---|---|---|
| 1 | Réservation valide (Brouette, J+5 à J+6) | Acceptée, dernière activité mise à jour | Conforme |
| 2 | RG1 : fin avant début | `PeriodeInvalideException` | Conforme |
| 3 | RG1 : début hier | `PeriodeInvalideException` | Conforme |
| 4 | RG1 : début aujourd'hui | Acceptée | Conforme |
| 5 | RG3 : 15 jours | `DureeDepasseeException` | Conforme |
| 6 | RG3 : 14 jours exactement | Acceptée | Conforme |
| 7 | RG5 : objet désactivé | `ObjetNonReservableException` | Conforme |
| 8 | RG5 : objet hors service | `ObjetNonReservableException` | Conforme |
| 9 | Retour en retard (Glacière) | `ObjetNonReservableException` | Conforme |
| 10 | RG4 : 4e réservation active | `QuotaAtteintException` | Conforme |
| 11 | RG2 : chevauchement | `PeriodeIndisponibleException` | Conforme |
| 12 | RG2 : période qui commence le lendemain de la fin d'une autre | Acceptée | Conforme |
| 13 | RG2 en accès simultanés (repository qui ne voit pas le chevauchement) | `PeriodeIndisponibleException` par la contrainte `EXCLUDE`, rien en base | Conforme |
| 14 | Annuler une réservation confirmée | Statut annulée | Conforme |
| 15 | La réservation annulée ne compte plus dans le quota | Nouvelle réservation acceptée | Conforme |
| 16 | Annuler une réservation en cours | `AnnulationImpossibleException` | Conforme |

Le journal SQL confirme le verrou : une requête `SELECT 1 FROM utilisateur ... FOR UPDATE` par réservation tentée après les vérifications de RG5.

**Écart du premier passage** : les cas 11 à 13 étaient refusés par `QuotaAtteintException`, parce que les comptes utilisés avaient atteint leur quota dans les cas précédents. Le service était correct (RG4 est vérifiée avant RG2, comme prévu) : le jeu d'essai a été corrigé en utilisant un compte qui n'avait qu'une réservation active.
