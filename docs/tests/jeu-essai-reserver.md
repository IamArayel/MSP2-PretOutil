# Jeu d'essai « Réserver un objet »

Ticket : [#65 J7-A2 Jeu d'essai « Réserver un objet » (entrée, attendu, obtenu)](https://github.com/IamArayel/MSP2-PretOutil/issues/65) · Plan : [plan-de-tests.md](plan-de-tests.md)

La réservation (US4) est la fonctionnalité la plus représentative de Prêt'Outil : elle traverse toutes les couches, applique les règles RG1 à RG5, écrit dans PostgreSQL et journalise dans MongoDB (RG8). Le déroulé attendu est celui du [diagramme de séquence](../diagrammes/sequence-reserver.md).

Chaque cas est rejoué par de vraies requêtes HTTP, comme un navigateur, par le script [jeu_essai_reserver.sh](jeu_essai_reserver.sh). Pour chaque cas, le script relève le code HTTP, le message affiché, les réservations en base et les documents ajoutés au journal d'audit.

## 1. État initial

| Élément | Valeur |
|---|---|
| Données | Fixtures rechargées juste avant (`doctrine:fixtures:load`) |
| Date du jour (J) | 2026-11-24 ; les dates des fixtures sont relatives au jour du chargement |
| Règles | 14 jours au maximum (bornes incluses), 3 réservations actives au maximum (confirmées ou en cours) |

| Compte | Réservations actives au départ |
|---|---|
| Jeanne, nouvel adhérent inscrit par le script (écran d'inscription) | 0 |
| Camille, `adherent@pretoutil.test` | 2 : Tente 4 places de J+7 à J+9, Vidéoprojecteur en cours |
| Noah, `adherent.quota@pretoutil.test` | 3 : le quota est atteint |
| Léa, gestionnaire (aussi adhérente, par la hiérarchie des rôles) | 1 : Enceinte Bluetooth |
| Sacha, administrateur | 1 : Glacière électrique, en cours, retour attendu hier (J-1) et pas encore rendue |

| Objet | Particularité |
|---|---|
| Meuleuse d'angle | Hors service |
| Motobineuse | Retirée du catalogue |
| Tondeuse thermique | Une réservation annulée de J+3 à J+4 |
| Autres objets utilisés | Disponibles |

## 2. Lancer le jeu d'essai

Environnement de développement uniquement, depuis la racine du dépôt :

```bash
docker compose exec php bin/console doctrine:fixtures:load -n --em=proprietaire
bash docs/tests/jeu_essai_reserver.sh
docker compose exec php bin/console doctrine:fixtures:load -n --em=proprietaire
```

Le second chargement efface le compte et les réservations créés par le script. Les documents du journal restent dans MongoDB : le compte de l'application ne peut rien supprimer, et l'index de conservation les efface au bout d'un an.

Le script juge un cas conforme quand le code HTTP obtenu est celui attendu et que le journal a reçu exactement un document `RESERVATION_CREEE` par réservation acceptée. Les messages ont été relus un par un (section 4).

## 3. Résultats du 2026-11-24

20 cas, 20 conformes. « Journal » : documents `RESERVATION_CREEE` ajoutés pendant le cas.

### Visiteur

| N° | Entrée | Attendu | Obtenu | En base | Journal |
|---|---|---|---|---|---|
| 1 | Visiteur non connecté, Brouette de J+5 à J+6 | 302 vers la connexion, rien en base | 302 vers `/connexion` | Brouette : 0 réservation | +0 |

### Refus (Jeanne, 0 réservation active, pour que le quota n'intervienne pas)

| N° | Règle | Entrée | Attendu | Obtenu | En base | Journal |
|---|---|---|---|---|---|---|
| 2 | Saisie | Brouette, dates vides | 422, un message sous chaque champ | 422, « Indiquez la date de début. », « Indiquez la date de fin. » | Jeanne : 0 | +0 |
| 3 | RG1 | Brouette de J+6 à J+5 | 422, fin avant début refusée | 422, « La date de fin doit être après la date de début. » | Jeanne : 0 | +0 |
| 4 | RG1 | Brouette de J-1 à J+2 | 422, début passé refusé | 422, « La réservation ne peut pas commencer dans le passé. » | Jeanne : 0 | +0 |
| 5 | RG3 | Escabeau de J+20 à J+34 (15 jours) | 422, durée refusée | 422, « Une réservation dure 14 jours au maximum. » | Jeanne : 0 | +0 |
| 6 | RG2 | Tente de J+8 à J+10 (Camille l'a de J+7 à J+9) | 422, période indisponible | 422, « Cet objet est déjà réservé sur une partie de cette période. Choisissez d'autres dates. » | Tente : 1 | +0 |
| 7 | RG2, borne | Tente de J+9 à J+11 (commence le jour où Camille la rend) | 422, période indisponible (bornes incluses) | 422, même message | Tente : 1 | +0 |
| 8 | RG5 | Meuleuse hors service : formulaire absent, envoi forcé avec un jeton valide | 422, objet non réservable | 422, « Cet objet est hors service : il ne peut pas être réservé pour le moment. » | Meuleuse : 0 | +0 |
| 9 | RG5 | Motobineuse retirée du catalogue, envoi forcé | 404 | 404 | Motobineuse : 0 | +0 |
| 10 | RG5 | Glacière, dont le retour était attendu hier | 422, objet pas encore rendu | 422, « Cet objet n'a pas encore été rendu par l'emprunteur précédent : il ne peut pas être réservé pour le moment. » | Glacière : 1 (celle de Sacha) | +0 |
| 11 | CSRF | Brouette de J+5 à J+6, jeton inventé | 422, formulaire refusé | 422, « La page a expiré. Merci de renvoyer le formulaire. » | Jeanne : 0 | +0 |

### Acceptations (Jeanne)

| N° | Règle | Entrée | Attendu | Obtenu | En base | Journal |
|---|---|---|---|---|---|---|
| 12 | RG3, limite | Escabeau de J+20 à J+33 (14 jours exactement) | 303 vers « Mes réservations » | 303 vers `/mes-reservations` | Jeanne : 1 | +1 |
| 13 | RG1, limite | Réchaud de J à J (commence aujourd'hui, un seul jour) | 303 | 303 vers `/mes-reservations` | Jeanne : 2 | +1 |
| 14 | RG2 | Tondeuse de J+3 à J+4, période d'une réservation annulée | 303, message de confirmation | 303, « Réservation confirmée : Tondeuse thermique du 27/11/2026 au 28/11/2026. » | Jeanne : 3 | +1 |
| 15 | RG4 | Paddle de J+30 à J+31 (4e réservation active) | 422, quota atteint | 422, « Vous avez déjà 3 réservations en cours ou à venir : c'est le maximum. » | Jeanne : 3 | +0 |

### Autres comptes

| N° | Règle | Entrée | Attendu | Obtenu | En base | Journal |
|---|---|---|---|---|---|---|
| 16 | Cas nominal | Camille, Brouette de J+5 à J+6 | 303, message de confirmation | 303, « Réservation confirmée : Brouette du 29/11/2026 au 30/11/2026. » | Camille : 3 | +1 |
| 17 | Double envoi | Camille renvoie le même formulaire (double clic, actualisation) | 422, une seule réservation | 422, message du quota | Brouette : 1 | +0 |
| 18 | RG4 | Noah (3 actives), Paddle de J+30 à J+31 | 422, quota atteint | 422, message du quota | Noah : 3 | +0 |

### Accès simultanés

| N° | Règle | Entrée | Attendu | Obtenu | En base | Journal |
|---|---|---|---|---|---|---|
| 19 | RG2 | Léa et Sacha réservent le Vélo enfant de J+40 à J+41, requêtes envoyées au même instant | Une acceptée, une refusée | 303 et 422, « Cet objet est déjà réservé… » | Vélo : 1 | +1 |
| 20 | RG4 | Léa (2 actives après le cas 19) envoie 3 réservations d'objets différents au même instant | Une acceptée, deux refusées | 303, 422 et 422 | Léa : 3 | +1 |

## 4. Analyse

| Constat | Explication |
|---|---|
| Aucun écart entre attendu et obtenu | Les 20 cas sont conformes, à la date et sur les données de la section 1 |
| Chaque refus laisse la base et le journal inchangés | La transaction est annulée (`ROLLBACK`) et la journalisation n'a lieu qu'après le `COMMIT` (RG8) |
| RG1 (cas 3 et 4) et dates vides (cas 2) : messages sous les champs | Refusés dès le formulaire (`ReservationDto`), avant le service, qui les revérifie pour les autres points d'entrée |
| Cas 8 : le panneau « hors service » répond, pas le message du service | La fiche affiche l'état de l'objet. Le service a bien refusé (rien en base) ; l'information donnée est la même |
| Cas 17 : le double envoi est refusé par le quota, pas par RG2 | Camille est à 3 réservations après le cas 16, et le quota est vérifié avant le chevauchement. Avec de la place, RG2 l'aurait refusé (cas 6) |
| Cas 19 : refus par la requête du service, pas par la contrainte `EXCLUDE` | Les deux requêtes sont parties ensemble, mais la seconde a lu la réservation déjà enregistrée : aucune erreur `23P01` dans les logs. Par HTTP, on ne peut pas forcer deux transactions à se croiser |
| Cas 20 : les 3 demandes d'une même session sont traitées l'une après l'autre | PHP verrouille la session pendant une requête. Le verrou du quota (`SELECT ... FOR UPDATE`) est prouvé par le test unitaire ci-dessous |

### Ce que le jeu d'essai HTTP ne peut pas prouver

Les accès vraiment simultanés sont prouvés par des tests automatisés, qui forcent la situation au lieu d'espérer la provoquer :

| Risque | Test | Ce qu'il prouve |
|---|---|---|
| Deux adhérents réservent le même objet au même instant | `ReservationServiceIntegrationTest::testContrainteExcludeTraduiteEnPeriodeIndisponible` | La vérification du service ne voit rien (simulé), PostgreSQL refuse l'`INSERT` (`23P01`), l'adhérent reçoit le message RG2 |
| Le même adhérent envoie deux demandes au même instant | `ReservationServiceTest::testRG4QuotaCompteSousVerrouDeLAdherent` | Le service ouvre la transaction, verrouille la ligne de l'adhérent, puis compte ses réservations : la seconde demande attend la première |

Détail de toutes les règles et de leurs tests : [correspondance-rg-tests.md](correspondance-rg-tests.md).
