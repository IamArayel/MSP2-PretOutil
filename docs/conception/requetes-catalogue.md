# Requêtes du catalogue

Ticket : [#33 J4-A1 Requêtes du catalogue : pagination, filtres et disponibilité](https://github.com/IamArayel/MSP2-PretOutil/issues/33)

Requêtes de l'US3 (consulter et filtrer le catalogue) et de l'écran E4 (périodes déjà réservées). Elles illustrent la compétence « développer des composants d'accès aux données SQL ».

## 1. Composants

| Composant | Rôle |
|---|---|
| `ObjetRepository::rechercherCatalogue()` | Objets actifs triés par nom, filtrés, paginés par 20 |
| `ReservationRepository::periodesReservees()` | Périodes déjà réservées d'un objet, à partir d'une date |
| `App\Dto\CriteresCatalogue` | Filtres facultatifs : texte cherché dans le nom (#109), catégorie, date de début, date de fin |
| `App\Pagination\Page` | Une page de résultats (éléments, total, numéro), sans dépendance à Doctrine pour les contrôleurs et les vues |
| `StatutReservation::actifs()` | Statuts qui bloquent un objet (`confirmee`, `en_cours`), définis à un seul endroit |

Les requêtes sont construites avec le QueryBuilder de Doctrine : toutes les valeurs passent par des paramètres liés, aucune n'est concaténée dans la requête (protection contre l'injection SQL). Le journal SQL de l'environnement de développement le confirme : chaque valeur apparaît comme `?`.

## 2. Règles appliquées

| Règle | Mise en œuvre |
|---|---|
| Catalogue = objets actifs (US6, RG5) | `o.actif = true` ; un objet désactivé n'apparaît jamais |
| Recherche par nom (#109), aussi sur E7 (`listerPourGestion()`) | `LOWER(UNACCENT(o.nom)) LIKE LOWER(UNACCENT(:recherche))`, motif `%texte%` dont les `%`, `_` et `\` saisis sont échappés ; « echelle » trouve « Échelle ». Pas d'index : `unaccent()` n'est pas immuable, et le parcours d'une table de quelques centaines d'objets reste négligeable |
| Objet hors service visible sans filtre de période | Affiché avec son état, pour informer ; exclu dès qu'une période est demandée (RG5 : non réservable) |
| Disponible sur une période (RG2) | Aucune réservation active dont la période chevauche celle demandée, `NOT EXISTS` sur `reservation` |
| Bornes incluses | `r.dateDebut <= :fin AND r.dateFin >= :debut` : même règle que la contrainte `EXCLUDE` (`daterange '[]'`) ; un objet rendu le 20 n'est pas disponible le 20 |
| Période partielle | Le filtre de période ne s'applique que si les deux dates sont fournies ; la validation des dates est faite par le formulaire de E3 (#34) |
| Tri stable | Par nom, puis par identifiant : deux objets de même nom gardent le même ordre d'une page à l'autre |
| Pagination | `Paginator` de Doctrine, 20 objets par page ; un numéro de page inférieur à 1 est ramené à 1 |
| Pas de requête par objet | La catégorie est chargée dans la même requête (`addSelect`) : une page coûte 2 requêtes (liste et comptage), quel que soit le nombre d'objets |
| Retour en retard (décision du 2026-11-24, ticket #44) | Un objet sorti dont la date de fin est passée n'est disponible sur aucune période tant que son retour n'est pas enregistré : `o.id NOT IN (...)`, même règle que `ReservationService` |
| Périodes réservées (E4) | Dates seules, sans donnée sur l'adhérent (minimisation RGPD) |

Requête SQL produite par Doctrine pour le filtre de disponibilité (extrait du journal) :

```sql
SELECT o0_.id, o0_.nom, ..., c1_.id, c1_.libelle
FROM objet o0_ INNER JOIN categorie c1_ ON o0_.categorie_id = c1_.id
WHERE o0_.actif = true AND o0_.etat <> ?
  AND (NOT (EXISTS (SELECT 1 FROM reservation r2_
                    WHERE r2_.objet_id = o0_.id AND r2_.statut IN (?, ?)
                      AND r2_.date_debut <= ? AND r2_.date_fin >= ?)))
  AND o0_.id NOT IN (SELECT r3_.objet_id FROM reservation r3_
                     WHERE r3_.statut = ? AND r3_.date_fin < ?)
ORDER BY o0_.nom ASC, o0_.id ASC LIMIT 20
```

## 3. Vérification sur les fixtures : 20 cas, 20 conformes

Exécutée le 2026-11-24 avec [requetes_catalogue_tests.php](requetes_catalogue_tests.php), sur les fixtures chargées le jour même, après l'ajout de la règle du retour en retard (cas 8 à 11, 14 et 15 mis à jour). J désigne le jour du chargement.

| N° | Cas | Attendu | Obtenu |
|---|---|---|---|
| 1 | Sans filtre, page 1 | 23 objets au total, 20 affichés, 2 pages, ordre alphabétique | Conforme (Appareil à raclette, Brouette, Broyeur de végétaux...) |
| 2 | Sans filtre : objet désactivé (Motobineuse) | Absent | Absent |
| 3 | Sans filtre : objet hors service (Meuleuse) | Présent | Présent |
| 4 | Sans filtre, page 2 | 3 objets | 3 objets |
| 5 | Page 3, au-delà de la dernière | Aucun objet, total 23 | Aucun objet, total 23 |
| 6 | Page 0 ou négative | Ramenée à la page 1 | Ramenée à la page 1 |
| 7 | Catégorie Outillage | 6 objets | 6 objets |
| 8 | Période J+7 à J+9 | 20 objets : Tente (réservée), Meuleuse (hors service) et Glacière (en retard) exclues | 20 objets, conforme |
| 9 | Période J+9 à J+10, bornes incluses | 19 objets : Tente (finit J+9) et Taille-haie (commence J+10) exclus | 19 objets, les deux exclus |
| 10 | Période J+5 à J+6, aucune réservation | 21 objets (Meuleuse et Glacière exclues) | 21 objets |
| 11 | Période J+3 à J+4 : Scie réservée, Tondeuse annulée | 20 objets : Scie exclue, Tondeuse présente | Conforme |
| 12 | Outillage et période J+3 à J+4 | 4 objets | 4 objets |
| 13 | Période J à J+1 : Vidéoprojecteur en cours, Enceinte réservée | Les deux exclus | Les deux exclus |
| 14 | Période J à J+1 : Glacière en retour en retard (fin J-1) | Exclue | Exclue |
| 15 | Période J+20 à J+21 : Glacière toujours en retard | Exclue (le retard bloque toute période) | Exclue |
| 16 | Une seule date fournie | Filtre de période ignoré : 23 objets | 23 objets |
| 17 | Périodes réservées : Tente | J+7 à J+9 | J+7 à J+9 |
| 18 | Périodes réservées : Vidéoprojecteur, en cours | J-2 à J+1 | J-2 à J+1 |
| 19 | Périodes réservées : Tondeuse, annulée | Aucune | Aucune |
| 20 | Périodes réservées : Perceuse, terminée | Aucune | Aucune |

## 4. Plan d'exécution et index

Avec 24 objets, PostgreSQL lit toujours les tables en entier : `EXPLAIN` n'apprend rien. Le script [requetes_catalogue_explain.sql](requetes_catalogue_explain.sql) ajoute 10 000 objets et 200 000 réservations dans une transaction annulée à la fin, puis analyse les deux requêtes.

| Requête | Avant | Après l'index `idx_reservation_dates_actives` |
|---|---|---|
| Catalogue filtré sur une période (page 1) | 25,5 ms : lecture complète de `reservation` (200 000 lignes, 18 ms) | 7,0 ms : `Bitmap Index Scan` sur l'index partiel |
| Périodes réservées d'un objet | 0,04 ms : index de la clé étrangère `objet_id` | Inchangé |

**Écart trouvé et corrigé** : aucun index ne servait le filtre de disponibilité, qui cherche des réservations actives par dates. L'index partiel `idx_reservation_dates_actives` sur `(date_debut, date_fin)`, limité aux réservations actives, est ajouté par la migration `Version20261124044037`. Il reste petit, car les réservations terminées et annulées, de loin les plus nombreuses avec le temps, n'y figurent pas.

Comme pour la contrainte `EXCLUDE` (voir [mpd.md, section 10](mpd.md#10-base-créée-par-les-migrations-doctrine-j3)), PostgreSQL réécrit la condition de l'index : la migration crée l'index avec `statut IN (...)`, et le mapping de `Reservation` contient la forme réécrite, pour que Doctrine ne propose pas de le recréer à chaque migration. Vérifié : `doctrine:migrations:diff` ne détecte aucun changement et `doctrine:schema:validate` est vert.

Il reste une lecture complète de `objet` (10 000 lignes, 1 ms) pour le tri par nom : négligeable à l'échelle d'une association, aucun index ajouté.

### Ajout de la règle du retour en retard (ticket #44)

| Version de la requête | Temps | Plan |
|---|---|---|
| Sans la règle du retard (J4) | 6 ms | Index partiel `idx_reservation_dates_actives` |
| Retard ajouté par `OR` dans le même `NOT EXISTS` | 16 ms | L'index partiel n'est plus utilisé |
| Retard dans un second `NOT EXISTS` corrélé | 83 ms | Une recherche par objet (10 000 boucles) |
| Retard en `NOT IN` non corrélé | 13 ms, instable | Lecture complète de `reservation` pour trouver les quelques réservations en cours |
| **`NOT IN` non corrélé et index `idx_reservation_en_cours`** | **4,6 ms, stable** | Les deux index partiels |

L'index partiel `idx_reservation_en_cours` sur `date_fin`, limité aux réservations en cours (les objets sortis, très peu nombreux), est ajouté par la migration `Version20261124054944`. Il sert aussi à `ReservationRepository::existeRetourEnRetard()` et servira à l'écran E6 pour lister les retours en retard (J6). La condition de l'index suit la même précaution que celle de `idx_reservation_dates_actives`.

**Piège de mesure rencontré** : les insertions annulées du script laissent des lignes mortes jusqu'au prochain nettoyage. Après plusieurs passages, `reservation` en contenait 1,4 million, et même la requête de J4 était passée de 6 à 33 ms. Le script lance donc `VACUUM ANALYZE` avant et après la mesure.

## 5. Retour en retard : décision du 2026-11-24

Constat de J4 : une réservation `en_cours` dont la date de fin est passée (objet pas encore rendu) ne bloquait pas les périodes suivantes, ce qui est cohérent avec RG2 mais pas avec la réalité, puisque l'objet n'est pas là.

Décision prise par le binôme au début de J5 (ticket #44) : **un objet dont le retour est en retard n'est pas réservable** tant que le gestionnaire n'a pas enregistré son retour. `ReservationService` refuse la réservation (`ObjetNonReservableException`) et le catalogue ne l'annonce disponible sur aucune période (cas 14 et 15). Son badge du jour reste « Sorti ».
