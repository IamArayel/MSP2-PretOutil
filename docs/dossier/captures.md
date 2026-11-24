# Captures du dossier

Ticket : [#79 J8-B2 Vérification dans le navigateur et captures du dossier](https://github.com/IamArayel/MSP2-PretOutil/issues/79)

Liste unique des captures demandées par les notes de J3 à J7, rangées dans [captures/](captures/) et nommées par écran ou par outil. Les fichiers sont des PNG, pris avec le visuel final (après les corrections d'accessibilité de J8).


## Préparer

Fixtures rechargées juste avant : les dates sont relatives au jour du chargement, et les fixtures créent chaque état à capturer.

```bash
docker compose exec php bin/console doctrine:fixtures:load -n --em=proprietaire
docker compose exec php bin/console cache:pool:clear cache.rate_limiter
```

Comptes de test (mot de passe dans le README, avant la liste des comptes de test) :

| Compte | Profil | Particularité utile aux captures |
|---|---|---|
| `adherent@pretoutil.test` | Camille, adhérente | Une réservation de chaque statut (E5) |
| `adherent.quota@pretoutil.test` | Noah, adhérent | 3 réservations actives : quota atteint (E4) |
| `gestionnaire@pretoutil.test` | Léa, gestionnaire | Sortie de l'Enceinte Bluetooth à valider aujourd'hui (E6) |
| `admin@pretoutil.test` | Sacha, administrateur | Glacière électrique pas encore rendue : retour en retard (E6), objet non réservable (E4) |

Navigateur : Google Chrome, fenêtre d'environ 1 450 px de large, zoom à 100 %. Captures des pages cadrées sur le contenu (environ 1 570 px de large) ; terminal et éditeur capturés fenêtre par fenêtre (`screencapture` de macOS).

## 1. Écrans

| Fichier | Compte | Comment l'obtenir | Note | État |
|---|---|---|---|---|
| `e1-connexion.png` | Visiteur | `/connexion` | J3 | ✅ |
| `e1-connexion-echec.png` | Visiteur | Email et mauvais mot de passe : « Email ou mot de passe incorrect. » | J3 | ✅ |
| `e1-connexion-blocage.png` | Visiteur | 5 échecs sur `adherent@pretoutil.test`, puis le bon mot de passe : « Trop de tentatives de connexion… ». Débloquer ensuite avec `cache:pool:clear cache.rate_limiter` | J3 | ✅ |
| `e2-inscription.png` | Visiteur | `/inscription` | J3 | ✅ |
| `e2-inscription-erreurs.png` | Visiteur | Email invalide, téléphone invalide et mot de passe trop court | J3 | ✅ |
| `e3-catalogue.png` | Visiteur | `/catalogue` | J4 | ✅ |
| `e3-catalogue-filtre.png` | Visiteur | Catégorie « Outillage » et une période | J4 | ✅ |
| `e4-detail-visiteur.png` | Visiteur | Fiche de la Tente 4 places : périodes réservées, invitation à se connecter | J4 | ✅ |
| `e4-detail-hors-service.png` | Adhérent | Fiche de la Meuleuse d'angle (hors service) | J4 | ✅ |
| `e4-reservation.png` | Camille | Fiche du Broyeur de végétaux, formulaire vide | J5 | ✅ |
| `e4-api-disponible.png` | Camille | Broyeur : saisir deux dates libres, message « Disponible sur ces dates. » de l'API sous les dates | J6 | ✅ |
| `e4-api-indisponible.png` | Camille | Tente 4 places : saisir J+7 à J+9, message de refus de l'API | J6 | ✅ |
| `e4-refus-dates.png` | Camille | Broyeur : fin avant le début, « Confirmer la réservation » : erreur sous le champ (refus du serveur) | J5 | ✅ |
| `e4-refus-14-jours.png` | Camille | Broyeur : période de 15 jours | J5 | ✅ |
| `e4-refus-periode.png` | Camille | Tente 4 places : J+8 à J+10 (déjà réservée) | J5 | ✅ |
| `e4-refus-quota.png` | Noah | Broyeur : n'importe quelle période libre | J5 | ✅ |
| `e4-refus-retard.png` | Camille | Glacière électrique : n'importe quelle période à venir (objet pas encore rendu par l'emprunteur précédent, RG5) | J5 | ✅ |
| `e5-confirmation.png` | Camille | Réserver un objet libre : message « Réservation confirmée » sur Mes réservations | J5 | ✅ |
| `e5-mes-reservations.png` | Camille | Liste avec chaque statut (confirmée, en cours, terminée, annulée) | J6 | ✅ |
| `e5-annulation-confirmation.png` | Camille | « Annuler » sur la Tente : boîte de confirmation du navigateur (répondre « Annuler » ensuite) | J6 | ✅ |
| `e5-annulation.png` | Camille | Annuler la Tente : message affiché | J6 | ✅ |
| `e6-sorties-retours.png` | Léa | `/gestion/sorties-retours` : sortie de l'Enceinte, retour en retard de la Glacière | J6 | ✅ |
| `e6-retour-hors-service.png` | Léa | Raquettes de badminton : « Hors service », « Enregistrer le retour » : messages de succès et d'avertissement (RG7) | J6 | ✅ |
| `e7-gestion-catalogue.png` | Léa | `/gestion/catalogue` | J4 | ✅ |
| `e7-formulaire-erreurs.png` | Léa | « Ajouter un objet », envoyer le formulaire vide | J4 | ✅ |
| `e7-desactivation.png` | Léa | « Désactiver » sur la Brouette : message affiché | J4 | ✅ |
| `e7-desactivation-retire.png` | Léa | Page 2 de la liste : la Brouette passe en « Retiré » | J4 | ✅ |
| `e7-suppression-confirmation.png` | Léa | « Supprimer » sur un objet jamais réservé : boîte de confirmation du navigateur (répondre « Annuler » ensuite) | J8 | ✅ |
| `e8-gestion-comptes.png` | Sacha | `/admin/comptes`, après la connexion de chaque compte (colonne « Dernière activité ») | J8 | ✅ |
| `e9-journal.png` | Sacha | `/admin/journal`, après les actions ci-dessus | J6 | ✅ |
| `e9-journal-filtre.png` | Sacha | Filtre « Connexion échouée » | J6 | ✅ |

## 2. Navigateur : API, en-têtes, console

| Fichier | Comment l'obtenir | Note | État |
|---|---|---|---|
| `api-disponibilites.png` | `/api/objets/{id}/disponibilites?debut=…&fin=…` dans le navigateur (identifiant pris dans l'URL d'une fiche), sur une période qui chevauche une réservation : réponse JSON avec `periodes_reservees` et le motif du refus | J6 | ✅ |
| `api-erreur-400.png` | Même adresse avec `?debut=x&fin=y` : `{"erreur": "..."}`, code 400 | J6 | ✅ |
| `http-entetes-catalogue.png` | Outils de développement, onglet Réseau, requête `/catalogue` : en-têtes de réponse (CSP, `X-Frame-Options`…) | J7 | ✅ |
| `console-e4-sans-erreur.png` | Outils de développement, onglet Console, sur une fiche E4 après la saisie des dates : aucune erreur | J7 | ✅ |

## 3. Terminal et outils

Commandes à lancer depuis la racine du dépôt.

| Fichier | Commande ou outil | Note | État |
|---|---|---|---|
| `terminal-comptes-restreints-1.png` | `docker compose exec php bin/console dbal:run-sql "SELECT current_user"` | J3 | ✅ |
| `terminal-comptes-restreints-2.png` | `docker compose exec php bin/console dbal:run-sql "DELETE FROM reservation"` : refusé | J3 | ✅ |
| `terminal-deblocage-comptes.png` | `docker compose exec php bin/console cache:pool:clear cache.rate_limiter` | J3 | ✅ |
| `code-migration-exclude.png` | Éditeur : `migrations/Version20261123095233.php`, extension `btree_gist` et contrainte `ex_reservation_chevauchement` | J3 | ✅ |
| `terminal-explain.png` | `docker compose exec -T database sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < docs/conception/requetes_catalogue_explain.sql` : plan de la requête du catalogue sur 10 000 objets et 200 000 réservations, avec les deux index partiels | J4, J5 | ✅ |
| `terminal-phpunit-matrice.png` | `docker compose exec php bin/phpunit --testdox --filter ControleAccesTest` : début de la matrice d'accès | J4 à J7 | ✅ |
| `terminal-phpunit-fin.png` | `docker compose exec php bin/phpunit --testdox` : fin de la sortie (tests de sécurité) et ligne `OK (363 tests, 1485 assertions)` | J4 à J7 | ✅ |
| `terminal-jeu-essai.png` | Fixtures rechargées, puis `bash docs/tests/jeu_essai_reserver.sh` : tableau des 20 cas, tous conformes | J7 | ✅ |
| `terminal-audits.png` | `docker compose exec php composer audit` puis `docker compose exec php bin/console importmap:audit` : aucune faille | J7 | ✅ |
| `mongodb-audit-log.png` | `mongosh` avec le compte administrateur, comme pour `audit_log.mongodb.js` (README, section « Journal d'audit ») : dernier document `RETOUR_ENREGISTRE` de `pretoutil.audit_log` | J6 | ✅ |

Les notes de J4 à J7 demandaient chacune une sortie de `phpunit --testdox` (31, 58, 98, puis 257 tests) : deux captures de la suite finale les remplacent, le nombre de tests de chaque étape restant écrit dans les notes. Le journal d'erreurs de Symfony (accès refusés voulus par les tests) sort sur la sortie d'erreur : il est masqué par `2>/dev/null` pour garder la sortie lisible.

MongoDB Compass n'est pas installé sur le poste : le document est lu avec `mongosh`, il montre les mêmes champs.

Le diagramme de séquence demandé à J5 est déjà une image : [sequence-reserver.png](../diagrammes/sequence-reserver.png). L'extrait d'`EXPLAIN` avant l'index reste celui de [requetes-catalogue.md](../conception/requetes-catalogue.md), section 4 : la capture montre le plan actuel, avec les index.

## 4. Vérifications faites en même temps

| Vérification | Résultat |
|---|---|
| Console du navigateur sans erreur sur chaque écran (CSP) | Conforme : accueil, E1 à E9 et l'API parcourus avec chaque profil, aucune erreur de script ni de CSP. Seule ligne relevée : « Failed to load resource : 422 » après l'envoi du formulaire d'objet vide, qui est le code de réponse voulu pour un formulaire invalide. Revérifié dans Chrome pendant les captures : seules des exceptions du gestionnaire de mots de passe installé dans le navigateur, aucune de l'application |
| La CSP bloque bien un script injecté | Conforme : un `<img onerror=…>` inséré dans la page est bloqué (`script-src-attr`), le gestionnaire ne s'exécute pas |
| Message de l'API sur E4 | Conforme : « Disponible sur ces dates. » sous les dates, appel `GET /api/objets/{id}/disponibilites?debut=…&fin=…` en 200 |
| Confirmations d'annulation (E5) et de suppression (E7) affichées | Conforme : questions « Annuler la réservation de « Tente 4 places » ? » et « Supprimer définitivement « Paddle gonflable » ? » ; réponse « Annuler » : rien n'est envoyé |
| Cycle de vie complet : réserver (Camille), valider la sortie puis le retour (Léa), voir les actions dans le journal (Sacha) | Conforme : Brouette réservée, sortie validée, retour « Hors service » enregistré ; le journal montre `RESERVATION_CREEE`, `RESERVATION_ANNULEE`, `SORTIE_VALIDEE`, `RETOUR_ENREGISTRE`, `OBJET_CREE`, `OBJET_DESACTIVE` avec le nom des acteurs |
| Mobile (375 px) | Conforme : aucune page ne déborde ; tableaux de E6 à E9 défilants dans leur cadre |

Vérifiées le 2026-11-24 dans le navigateur intégré de l'éditeur (Chromium), après avoir ajouté au trousseau de macOS l'autorité locale de Caddy (section 6), puis revues dans Google Chrome pendant les captures.

## 5. Anomalies

| Anomalie | Suite donnée |
|---|---|
| Après un retour hors service, « Brouette est maintenant hors service : **il** n'est plus réservable » : le pronom ne s'accorde pas avec un nom féminin | Corrigé : « L'objet « Brouette » est maintenant hors service : il n'est plus réservable. » (`SortiesRetoursController`) |
| Scénario de la liste : le refus « retour en retard » de E4 était attribué à l'emprunteur en retard ; il concerne l'objet pas encore rendu (RG5) | Liste corrigée (`e4-refus-retard.png`) ; pas d'anomalie de l'application |
| E8 : « Dernière activité » restait « Aucune » pour un compte qui se connecte sans réserver, alors que le dictionnaire de données la définit comme « dernière connexion ou réservation » ; c'est aussi le départ du délai de conservation (RGPD) | Corrigé : `ConnexionReussieListener` l'enregistre à chaque connexion réussie ; test `testConnexionReussieEnregistreLaDerniereActivite` (vérifié en retirant l'écouteur : le test échoue) |
| `docker compose exec php bin/phpunit` s'arrêtait vers le 165e test sur 362 : mémoire de PHP épuisée (128 Mo). Xdebug, actif en mode `develop` dans l'environnement de développement, double la mémoire de la suite (56 Mo sans lui, 110 Mo avec) | Corrigé : `memory_limit` à 512 Mo dans `phpunit.dist.xml` ; la commande du README repasse |
| `composer audit` : 9 failles publiées les 23 et 24 novembre dans `twig/twig` 3.30.0 (contournements du bac à sable, échappement manquant dans un cas de macro) | Corrigé : `composer update twig/twig`, passage en 3.31.1 (seul `composer.lock` change) ; audit propre, 363 tests verts |

Remarques sur l'environnement de développement, sans effet sur l'application :

- Le rechargement à chaud de FrankenPHP recharge la page quand un fichier du projet change : un message flash affiché à ce moment est consommé par le rechargement et disparaît. Ne pas écrire dans le projet pendant la capture d'un message.
- La barre de notification d'un gestionnaire de mots de passe, posée par-dessus la page après une connexion, intercepte les clics : la fermer avant de cliquer sur un bouton.

## 6. Faire confiance au certificat local (une fois par poste)

Le certificat de `https://localhost` est signé par une autorité créée par Caddy dans le conteneur. Sans elle, les navigateurs affichent un avertissement et les navigateurs automatisés refusent le site.

```bash
docker compose cp php:/data/caddy/pki/authorities/local/root.crt /tmp/caddy-root.crt
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain /tmp/caddy-root.crt
```

Pour la retirer : Trousseaux d'accès, « Caddy Local Authority ». Elle change si le volume `caddy_data` est supprimé : recommencer alors.

Fixtures à recharger après les captures : les actions ci-dessus modifient les données.
