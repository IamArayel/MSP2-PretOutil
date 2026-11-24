# API REST des disponibilités

Ticket : [#49 J6-C1 API REST des disponibilités (US11)](https://github.com/IamArayel/MSP2-PretOutil/issues/49) · Exigences : [cahier des charges, section 6.5](../cahier-des-charges.md#65-api-rest-des-disponibilités)

API en lecture seule qui indique la disponibilité d'un objet. L'écran E4 l'appelle en JavaScript dès que l'adhérent a saisi ses dates, pour l'informer avant l'envoi du formulaire (US11). Elle rend l'architecture répartie concrète : un client appelle un service par HTTP et reçoit du JSON.

## 1. Point d'accès

```text
GET /api/objets/{id}/disponibilites
GET /api/objets/{id}/disponibilites?debut=AAAA-MM-JJ&fin=AAAA-MM-JJ
```

| Paramètre | Où | Obligatoire | Format | Rôle |
|---|---|---|---|---|
| `id` | Chemin | Oui | Entier | Identifiant de l'objet |
| `debut` | Requête | Non, mais avec `fin` | `AAAA-MM-JJ` (ISO 8601) | Premier jour de la période à vérifier, inclus |
| `fin` | Requête | Non, mais avec `debut` | `AAAA-MM-JJ` (ISO 8601) | Dernier jour de la période à vérifier, inclus |

Accès public, comme le catalogue : aucune authentification, aucun jeton CSRF (aucune donnée n'est modifiée).

## 2. Réponses

### 200 : objet trouvé

```json
{
    "objet": { "id": 12, "nom": "Tente 4 places" },
    "statut_du_jour": { "code": "disponible", "libelle": "Disponible" },
    "periodes_reservees": [
        { "debut": "2026-12-01", "fin": "2026-12-03" }
    ],
    "periode_demandee": {
        "debut": "2026-12-02",
        "fin": "2026-12-04",
        "disponible": false,
        "motif": "Cet objet est déjà réservé sur une partie de cette période. Choisissez d'autres dates."
    }
}
```

| Champ | Contenu |
|---|---|
| `objet` | Identifiant et nom |
| `statut_du_jour.code` | `disponible`, `reserve`, `sorti` ou `hors_service` (même calcul que le badge des écrans E3 et E4) |
| `periodes_reservees` | Réservations actives à venir ou en cours : dates seulement, jamais l'adhérent |
| `periode_demandee` | Présent seulement si `debut` et `fin` sont fournis |
| `periode_demandee.disponible` | `true` si la réservation serait acceptée sur cette période |
| `periode_demandee.motif` | Message de la règle qui la refuserait, `null` si disponible |

La disponibilité applique les règles de `ReservationService` par sa méthode `verifierDisponibilite()`, sans les recopier : RG1 (dates), RG3 (14 jours), RG5 (objet hors service) et retour en retard, RG2 (chevauchement). Le quota RG4 dépend de l'adhérent et n'est pas vérifié par l'API, qui est publique. L'API informe seulement : à l'envoi du formulaire, `reserver()` revérifie toutes les règles, sous verrou.

### Erreurs

Toutes les erreurs ont la forme `{"erreur": "message"}`, mise en place par `App\EventSubscriber\ApiExceptionSubscriber` pour les chemins `/api/`.

| Code | Cas | Message |
|---|---|---|
| 400 | Date inexistante ou mal formée (`2026-02-30`, `2026-12-1`, `demain`) | Le paramètre debut (ou fin) doit être une date au format AAAA-MM-JJ. |
| 400 | Une seule des deux dates | Les paramètres debut et fin vont ensemble. |
| 404 | Objet inconnu ou retiré du catalogue | Objet introuvable. |
| 405 | Méthode autre que `GET` | Méthode non autorisée : l'API est en lecture seule (GET). |
| 429 | Plus de 30 appels par minute depuis la même adresse IP ; en-tête `Retry-After` | Trop de requêtes. Réessayez dans quelques instants. |
| 500 | Erreur inattendue | Erreur interne. (aucun détail technique) |

Une période refusée par une règle de gestion n'est pas une erreur : la réponse est 200, avec `disponible: false` et le motif.

## 3. Exemples

```bash
curl -k "https://localhost/api/objets/12/disponibilites"
curl -k "https://localhost/api/objets/12/disponibilites?debut=2026-12-02&fin=2026-12-04"
```

## 4. Sécurité et sobriété

| Mesure | Mise en œuvre |
|---|---|
| Lecture seule | Route déclarée en `GET` ; toute autre méthode renvoie 405 |
| Minimisation (RGPD) | Dates et statuts seulement ; un test vérifie que l'email et le nom de l'adhérent n'apparaissent pas |
| Entrées validées | Format exact `AAAA-MM-JJ`, les deux dates ensemble ; identifiant numérique (`requirements`) |
| Limitation de débit | `symfony/rate-limiter`, fenêtre glissante, 30 appels par minute et par adresse IP (`config/packages/rate_limiter.yaml`) |
| Pas de fuite d'information | Messages d'erreur génériques, sauf pour les erreurs de saisie |
| Échappement | Protections par défaut de `JsonResponse` conservées ; côté client, le message est inséré avec `textContent`, jamais `innerHTML` |
| Fraîcheur | `Cache-Control: no-store` : une disponibilité n'est jamais servie depuis un cache |
| Pas de polling | Un appel à chaque modification d'une date ; une saisie plus récente annule l'appel en cours |

## 5. Client : écran E4

Le module [assets/disponibilites.js](../../assets/disponibilites.js) est chargé par AssetMapper sur toutes les pages, mais n'agit que sur un formulaire qui porte l'attribut `data-disponibilites-url`, c'est-à-dire le formulaire de réservation de E4 affiché à un adhérent connecté.

- Quand les deux dates sont remplies, il appelle l'API et affiche « Disponible sur ces dates. » ou le motif du refus.
- Le message est placé dans une zone `aria-live` toujours présente dans la page : les lecteurs d'écran l'annoncent.
- Amélioration progressive : sans JavaScript, ou si l'API ne répond pas, rien n'est affiché et le formulaire fonctionne comme avant.

Un visiteur non connecté n'a pas de formulaire sur E4 : il vérifie les disponibilités avec le filtre de période du catalogue (E3).

## 6. Tests

[tests/Controller/Api/DisponibiliteControllerTest.php](../../tests/Controller/Api/DisponibiliteControllerTest.php), 14 tests fonctionnels (vraies requêtes HTTP sur la base de test, transaction annulée après chaque test) :

| Cas | Attendu |
|---|---|
| Sans période | 200, JSON, `no-store`, objet, statut du jour, périodes réservées, pas de `periode_demandee` |
| Données personnelles | Ni l'email ni le nom de l'adhérent dans la réponse |
| Période qui chevauche une réservation | `disponible: false`, motif RG2 |
| Période libre | `disponible: true`, motif `null` |
| Objet hors service | Statut `hors_service`, `disponible: false` |
| Mêmes règles que la réservation | Motifs RG1 (début passé) et RG3 (15 jours) |
| 4 paramètres invalides | 400 et `erreur` |
| Objet désactivé, objet inconnu | 404 en JSON |
| `POST` | 405 en JSON |
| 31 appels | Les 30 premiers acceptés, le 31e : 429 et `Retry-After` |

Vérifié aussi par `curl` sur la base de développement : mêmes réponses, et 429 après 30 appels dans la minute.
