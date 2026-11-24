# Captures du dossier

Ticket : [#79 J8-B2 Vérification dans le navigateur et captures du dossier](https://github.com/IamArayel/MSP2-PretOutil/issues/79)

Liste unique des captures demandées par les notes de J3 à J7, rangées dans [captures/](captures/) et nommées par écran ou par outil. Les fichiers sont des PNG, pris avec le visuel final (après les corrections d'accessibilité de J8).

## Préparer

Fixtures rechargées juste avant : les dates sont relatives au jour du chargement, et les fixtures créent chaque état à capturer.

```bash
docker compose exec php bin/console doctrine:fixtures:load -n --em=proprietaire
docker compose exec php bin/console cache:pool:clear cache.rate_limiter
```

Comptes de test (mot de passe dans `src/DataFixtures/UtilisateurFixtures.php`, constante `MOT_DE_PASSE_TEST`) :

| Compte | Profil | Particularité utile aux captures |
|---|---|---|
| `adherent@pretoutil.test` | Camille, adhérente | Une réservation de chaque statut (E5) |
| `adherent.quota@pretoutil.test` | Noah, adhérent | 3 réservations actives : quota atteint (E4) |
| `gestionnaire@pretoutil.test` | Léa, gestionnaire | Sortie de l'Enceinte Bluetooth à valider aujourd'hui (E6) |
| `admin@pretoutil.test` | Sacha, administrateur | Glacière électrique pas encore rendue : retour en retard (E6), objet non réservable (E4) |

Navigateur : fenêtre d'environ 1440 px de large, zoom à 100 %, outils de développement fermés sauf pour les captures qui les montrent.

Déjà présentes le 2026-11-24, renommées selon cette liste : `e1-connexion.png`, `e1-connexion-echec.png`, `e2-inscription.png`, `e2-inscription-erreurs.png`, `terminal-comptes-restreints-1.png`, `terminal-comptes-restreints-2.png`, `terminal-deblocage-comptes.png`. Les quatre captures d'écran datent de J3, avant les corrections de J8 (contour des champs, mention des champs obligatoires sur E2) : à reprendre avec le visuel final.

## 1. Écrans

| Fichier | Compte | Comment l'obtenir | Note |
|---|---|---|---|
| `e1-connexion.png` | Visiteur | `/connexion` | J3 |
| `e1-connexion-echec.png` | Visiteur | Email et mauvais mot de passe : « Email ou mot de passe incorrect. » | J3 |
| `e1-connexion-blocage.png` | Visiteur | 5 échecs sur `adherent@pretoutil.test`, puis le bon mot de passe : « Trop de tentatives de connexion… ». Débloquer ensuite avec `cache:pool:clear cache.rate_limiter` | J3 |
| `e2-inscription.png` | Visiteur | `/inscription` | J3 |
| `e2-inscription-erreurs.png` | Visiteur | Email invalide et mot de passe trop court | J3 |
| `e3-catalogue.png` | Visiteur | `/catalogue` | J4 |
| `e3-catalogue-filtre.png` | Visiteur | Catégorie « Outillage » et une période | J4 |
| `e4-detail-visiteur.png` | Visiteur | Fiche de la Tente 4 places : périodes réservées, invitation à se connecter | J4 |
| `e4-detail-hors-service.png` | Adhérent | Fiche de la Meuleuse d'angle (hors service) | J4 |
| `e4-reservation.png` | Camille | Fiche du Broyeur de végétaux, formulaire vide | J5 |
| `e4-api-disponible.png` | Camille | Broyeur : saisir deux dates libres, message « disponible » de l'API sous les dates | J6 |
| `e4-api-indisponible.png` | Camille | Tente 4 places : saisir J+7 à J+9, message de refus de l'API | J6 |
| `e4-refus-dates.png` | Camille | Broyeur : fin avant le début, « Confirmer la réservation » | J5 |
| `e4-refus-14-jours.png` | Camille | Broyeur : période de 15 jours | J5 |
| `e4-refus-periode.png` | Camille | Tente 4 places : J+8 à J+10 (déjà réservée) | J5 |
| `e4-refus-quota.png` | Noah | Broyeur : n'importe quelle période libre | J5 |
| `e4-refus-retard.png` | Camille | Glacière électrique : n'importe quelle période à venir (objet pas encore rendu par l'emprunteur précédent, RG5) | J5 |
| `e5-confirmation.png` | Camille | Réserver le Broyeur : message « Réservation confirmée » sur Mes réservations | J5 |
| `e5-mes-reservations.png` | Camille | Liste avec chaque statut (confirmée, en cours, terminée, annulée) | J6 |
| `e5-annulation.png` | Camille | Annuler la Tente : boîte de confirmation, puis capture du message affiché | J6 |
| `e6-sorties-retours.png` | Léa | `/gestion/sorties-retours` : sortie de l'Enceinte, retour en retard de la Glacière | J6 |
| `e6-retour-hors-service.png` | Léa | Raquettes de badminton : « Hors service », « Enregistrer le retour » : messages de succès et d'avertissement (RG7) | J6 |
| `e7-gestion-catalogue.png` | Léa | `/gestion/catalogue` | J4 |
| `e7-formulaire-erreurs.png` | Léa | « Ajouter un objet », envoyer le formulaire vide | J4 |
| `e7-desactivation.png` | Léa | « Désactiver » sur la Brouette : message affiché, ligne « Retiré » | J4 |
| `e7-suppression-confirmation.png` | Léa | « Supprimer » sur un objet jamais réservé : boîte de confirmation (annuler ensuite) | J8 |
| `e8-gestion-comptes.png` | Sacha | `/admin/comptes` | J8 |
| `e9-journal.png` | Sacha | `/admin/journal`, après les actions ci-dessus | J6 |
| `e9-journal-filtre.png` | Sacha | Filtre « Connexion échouée » | J6 |

## 2. Navigateur : API, en-têtes, console

| Fichier | Comment l'obtenir | Note |
|---|---|---|
| `api-disponibilites.png` | `/api/objets/{id}/disponibilites` dans le navigateur (identifiant pris dans l'URL d'une fiche), réponse JSON | J6 |
| `api-erreur-400.png` | Même adresse avec `?debut=x&fin=y` : `{"erreur": "..."}` | J6 |
| `http-entetes-catalogue.png` | Outils de développement, onglet Réseau, requête `/catalogue` : en-têtes de réponse (CSP, `X-Frame-Options`…) | J7 |
| `console-e4-sans-erreur.png` | Outils de développement, onglet Console, sur une fiche E4 après la saisie des dates : aucune erreur | J7 |

## 3. Terminal et outils

Commandes à lancer depuis la racine du dépôt.

| Fichier | Commande ou outil | Note |
|---|---|---|
| `terminal-comptes-restreints-1.png` | `docker compose exec php bin/console dbal:run-sql "SELECT current_user"` | J3 |
| `terminal-comptes-restreints-2.png` | `docker compose exec php bin/console dbal:run-sql "DELETE FROM reservation"` : refusé | J3 |
| `terminal-deblocage-comptes.png` | `docker compose exec php bin/console cache:pool:clear cache.rate_limiter` | J3 |
| `code-migration-exclude.png` | Éditeur : `migrations/Version20261123095233.php`, contrainte `ex_reservation_chevauchement` | J3 |
| `terminal-explain.png` | `docker compose exec -T database sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < docs/conception/requetes_catalogue_explain.sql` : plans avant et après l'index | J4, J5 |
| `terminal-phpunit.png` | `docker compose exec php bin/phpunit --testdox` : début de la sortie (matrice d'accès, sécurité) et ligne finale `OK (... tests ...)` | J4 à J7 |
| `terminal-jeu-essai.png` | Fixtures rechargées, puis `bash docs/tests/jeu_essai_reserver.sh` : tableau des 20 cas | J7 |
| `terminal-audits.png` | `docker compose exec php composer audit` puis `docker compose exec php bin/console importmap:audit` | J7 |
| `mongodb-compass-audit-log.png` | MongoDB Compass (connexion : README, section « Se connecter aux bases ») : un document de `pretoutil.audit_log` déplié | J6 |

Les notes de J4 à J7 demandaient chacune une sortie de `phpunit --testdox` (31, 58, 98, puis 257 tests) : une seule capture de la suite finale les remplace, le nombre de tests de chaque étape restant écrit dans les notes.

Le diagramme de séquence demandé à J5 est déjà une image : [sequence-reserver.png](../diagrammes/sequence-reserver.png).

## 4. Vérifications faites en même temps

| Vérification | Résultat |
|---|---|
| Console du navigateur sans erreur sur chaque écran (CSP) | Conforme : accueil, E1 à E9 et l'API parcourus avec chaque profil, aucune erreur de script ni de CSP. Seule ligne relevée : « Failed to load resource : 422 » après l'envoi du formulaire d'objet vide, qui est le code de réponse voulu pour un formulaire invalide |
| La CSP bloque bien un script injecté | Conforme : un `<img onerror=…>` inséré dans la page est bloqué (`script-src-attr`), le gestionnaire ne s'exécute pas |
| Message de l'API sur E4 | Conforme : « Disponible sur ces dates. » sous les dates, appel `GET /api/objets/{id}/disponibilites?debut=…&fin=…` en 200 |
| Confirmations d'annulation (E5) et de suppression (E7) affichées | Conforme : questions « Annuler la réservation de « Tente 4 places » ? » et « Supprimer définitivement « Paddle gonflable » ? » ; réponse « Annuler » : rien n'est envoyé |
| Cycle de vie complet : réserver (Camille), valider la sortie puis le retour (Léa), voir les actions dans le journal (Sacha) | Conforme : Brouette réservée, sortie validée, retour « Hors service » enregistré ; le journal montre `RESERVATION_CREEE`, `RESERVATION_ANNULEE`, `SORTIE_VALIDEE`, `RETOUR_ENREGISTRE`, `OBJET_CREE`, `OBJET_DESACTIVE` avec le nom des acteurs |
| Mobile (375 px) | Conforme : aucune page ne déborde ; tableaux de E6 à E9 défilants dans leur cadre |

Vérifiées le 2026-11-24 dans le navigateur intégré de l'éditeur (Chromium), après avoir ajouté au trousseau de macOS l'autorité locale de Caddy (section suivante).

## 5. Anomalies

| Anomalie | Suite donnée |
|---|---|
| Après un retour hors service, « Brouette est maintenant hors service : **il** n'est plus réservable » : le pronom ne s'accorde pas avec un nom féminin | Corrigé : « L'objet « Brouette » est maintenant hors service : il n'est plus réservable. » (`SortiesRetoursController`) |
| Scénario de la liste : le refus « retour en retard » de E4 était attribué à l'emprunteur en retard ; il concerne l'objet pas encore rendu (RG5) | Liste corrigée (`e4-refus-retard.png`) ; pas d'anomalie de l'application |

## 6. Faire confiance au certificat local (une fois par poste)

Le certificat de `https://localhost` est signé par une autorité créée par Caddy dans le conteneur. Sans elle, les navigateurs affichent un avertissement et les navigateurs automatisés refusent le site.

```bash
docker compose cp php:/data/caddy/pki/authorities/local/root.crt /tmp/caddy-root.crt
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain /tmp/caddy-root.crt
```

Pour la retirer : Trousseaux d'accès, « Caddy Local Authority ». Elle change si le volume `caddy_data` est supprimé : recommencer alors.

Fixtures à recharger après les captures : les actions ci-dessus modifient les données.
