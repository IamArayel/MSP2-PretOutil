# Cahier des charges : Prêt'Outil

| Version | Date | Auteur | Objet |
|---|---|---|---|
| 1.0 | 2026-11-23 | Anthony Dégeilh | Version initiale (J1) |
| 1.1 | 2026-11-23 | Anthony Dégeilh, Chloé Baïsse | Projet réalisé en binôme |
| 1.2 | 2026-11-24 | Anthony Dégeilh, Chloé Baïsse | API REST interne des disponibilités (US11, section 6.5) |

---

## 1. Présentation du projet

Prêt'Outil est une application web de gestion du prêt de matériel entre une association et ses adhérents. Elle remplace le tableur partagé utilisé aujourd'hui.

Le projet est réalisé en formation, pour la mise en situation professionnelle du CCP 2 « Concevoir et développer une application sécurisée organisée en couches » du titre CDA. Il ne correspond pas à une demande réelle d'un commanditaire : l'association décrite est un cas d'étude.

## 2. Contexte et existant

L'association prête environ 150 objets (outillage, matériel de camping, matériel audiovisuel) à environ 200 adhérents. Trois bénévoles gèrent les sorties et les retours.

**Existant** : un tableur partagé, une ligne par prêt, modifiable par tous les bénévoles.

**Problèmes constatés** :

| # | Problème | Conséquence |
|---|---|---|
| P1 | Aucun contrôle des dates | Réservations en double sur un même objet |
| P2 | Pas de statut clair des objets | On ne sait pas ce qui est disponible, sorti ou en panne |
| P3 | Aucune traçabilité | Impossible de savoir qui a modifié quoi |
| P4 | Données personnelles visibles par tous | Non-conformité RGPD |

## 3. Objectifs

| # | Objectif | Indicateur | Problème traité |
|---|---|---|---|
| O1 | Supprimer les réservations en double | 0 chevauchement possible (contrainte BDD + tests) | P1 |
| O3 | Assurer la traçabilité | 100 % des actions sensibles journalisées | P3 |
| O4 | Protéger les données personnelles | Coordonnées visibles uniquement par l'admin et l'adhérent concerné | P4 |
| O5 | Garantir la qualité du code | 100 % des RG1 à RG6 couvertes par un test unitaire ; contrôle d'accès testé sur chaque route protégée | P1, P4 |

Le statut de chaque objet (disponible, réservé, sorti, hors service) est affiché dans le catalogue et traite P2.

**Échéance** : MVP, dossier et diaporama livrés en 10 jours ouvrés, plus 2 jours de marge.

## 4. Périmètre

### Dans le périmètre (MVP)

- Inscription, connexion, déconnexion
- Catalogue public filtrable
- API REST interne en lecture seule : disponibilités d'un objet, appelée par l'écran E4
- Réservation, consultation et annulation par l'adhérent
- Gestion du catalogue, des sorties et des retours par le gestionnaire
- Gestion des rôles et désactivation des comptes par l'administrateur
- Journal d'audit consultable par l'administrateur

### Hors périmètre

- Paiement, caution, adhésion en ligne
- Notifications par email ou SMS
- Photos des objets
- Application mobile native
- Gestion de plusieurs associations
- Plusieurs exemplaires d'un même objet (1 objet = 1 article physique)
- Validation des comptes par l'administrateur
- Export et suppression RGPD automatisés (procédure décrite, non codée)

## 5. Acteurs

| Acteur | Rôle technique | Description | Hérite de |
|---|---|---|---|
| Visiteur | (anonyme) | Personne non connectée | |
| Adhérent | `ROLE_USER` | Membre de l'association qui emprunte du matériel | Visiteur |
| Gestionnaire | `ROLE_MANAGER` | Bénévole qui gère le catalogue, les sorties et les retours | Adhérent |
| Administrateur | `ROLE_ADMIN` | Responsable de l'application et des comptes | Gestionnaire |

La hiérarchie des rôles est configurée dans Symfony (`role_hierarchy`) : un gestionnaire peut aussi emprunter, un administrateur peut tout faire.

## 6. Besoins fonctionnels

### 6.1 User stories

| ID | Acteur | Besoin | Critères d'acceptation | Priorité |
|---|---|---|---|---|
| US1 | Visiteur | Créer un compte | Email unique ; mot de passe d'au moins 12 caractères ; compte actif dès la création | Must |
| US2 | Utilisateur | Se connecter et se déconnecter | Message d'erreur générique en cas d'échec ; blocage temporaire après 5 échecs | Must |
| US3 | Visiteur | Consulter et filtrer le catalogue | Filtres catégorie et disponibilité sur une période ; 20 objets par page | Must |
| US4 | Adhérent | Réserver un objet sur une période | RG1 à RG5 respectées ; confirmation affichée | Must |
| US5 | Adhérent | Voir et annuler ses réservations | Annulation jusqu'à la veille du début (RG6) | Must |
| US6 | Gestionnaire | Ajouter, modifier, désactiver un objet | Suppression logique si l'objet a déjà été réservé | Must |
| US7 | Gestionnaire | Valider la sortie d'un objet | La réservation passe à « En cours » | Must |
| US8 | Gestionnaire | Enregistrer un retour et l'état de l'objet | État bon, abîmé ou hors service (RG7) | Must |
| US9 | Administrateur | Changer le rôle d'un compte ou le désactiver | Action journalisée | Should |
| US10 | Administrateur | Consulter le journal d'audit | Liste paginée ; filtre par type d'action | Must |
| US11 | Visiteur ou adhérent | Savoir si un objet est disponible sur les dates choisies, avant d'envoyer la réservation | Réponse affichée sans recharger la page ; l'écran reste utilisable sans JavaScript | Should |

### 6.2 Règles de gestion

| ID | Règle | Où elle est garantie |
|---|---|---|
| RG1 | Date de fin ≥ date de début ; date de début ≥ aujourd'hui | Validator + service + `CHECK` BDD |
| RG2 | Pas deux réservations actives qui se chevauchent sur un même objet | Service + contrainte `EXCLUDE` BDD |
| RG3 | Durée de réservation de 14 jours maximum | Service |
| RG4 | 3 réservations actives maximum par adhérent | Service |
| RG5 | Objet hors service ou désactivé non réservable | Service |
| RG6 | Annulation possible jusqu'à la veille de la date de début | Service + Voter |
| RG7 | Retour « hors service » : l'objet devient indisponible | Service |
| RG8 | Toute action sensible est journalisée | Service `AuditLogger` (MongoDB) |
| RG9 | Un compte désactivé ne peut plus se connecter | `UserChecker` Symfony |

Une réservation est **active** quand son statut est « Confirmée » ou « En cours ».

### 6.3 Cycle de vie d'une réservation

| Statut | Transition possible vers | Déclencheur | Acteur |
|---|---|---|---|
| Confirmée | En cours | Sortie validée (US7) | Gestionnaire |
| Confirmée | Annulée | Annulation (US5) | Adhérent |
| En cours | Terminée | Retour enregistré (US8) | Gestionnaire |
| Annulée, Terminée | (aucune) | | |

### 6.4 Inventaire des écrans

| Écran | Accès | User stories |
|---|---|---|
| E1 Connexion | Visiteur | US2 |
| E2 Inscription | Visiteur | US1 |
| E3 Catalogue | Tous | US3 |
| E4 Détail d'un objet et réservation | Tous (réservation : adhérent) | US4, US11 |
| E5 Mes réservations | Adhérent | US5 |
| E6 Sorties et retours | Gestionnaire | US7, US8 |
| E7 Gestion du catalogue | Gestionnaire | US6 |
| E8 Gestion des comptes | Administrateur | US9 |
| E9 Journal d'audit | Administrateur | US10 |

Les écrans E1, E3, E4, E5, E6 et E9 sont maquettés dans Figma. E2, E7 et E8 reprennent les mêmes composants (formulaire, tableau) et ne sont pas maquettés.

### 6.5 API REST des disponibilités

L'application expose une interface de programmation (API) en lecture seule. L'écran E4 l'appelle en JavaScript pour indiquer la disponibilité d'un objet dès que les dates sont saisies (US11). Elle rend l'architecture répartie concrète : un client appelle un service par HTTP et reçoit du JSON.

| Méthode et chemin | Réponse | Codes |
|---|---|---|
| `GET /api/objets/{id}/disponibilites` | Statut du jour de l'objet et périodes déjà réservées à venir | 200 ; 404 si l'objet est inconnu ou désactivé |
| `GET /api/objets/{id}/disponibilites?debut=AAAA-MM-JJ&fin=AAAA-MM-JJ` | Idem, plus la disponibilité sur la période demandée et, si elle est refusée, le motif | 200 ; 400 si les dates sont invalides ; 404 |

Règles :

- **Mêmes règles que la réservation** : RG1, RG2, RG3, RG5 et retour en retard, appliquées par les services existants, sans les recopier. La réservation reste vérifiée par `ReservationService` à l'envoi du formulaire : l'API informe, elle ne garantit pas.
- **Aucune donnée personnelle** : dates et statuts seulement, jamais l'adhérent.
- **Lecture seule et publique** : méthode `GET` uniquement, comme le catalogue ; pas de jeton CSRF nécessaire puisqu'aucune donnée n'est modifiée.
- **Format** : JSON, dates au format ISO 8601 (`AAAA-MM-JJ`), erreurs au même format (`{"erreur": "..."}`).
- **Sobriété** : appel déclenché par la saisie des dates, jamais à intervalle régulier (pas de polling) ; nombre d'appels limité par adresse IP.

## 7. Besoins non fonctionnels

| Domaine | Exigence |
|---|---|
| Sécurité | Mots de passe hachés (hasher `auto`) ; `login_throttling` ; contrôle d'accès serveur (`access_control`, Voters) ; requêtes préparées ; validation de toutes les entrées ; CSRF ; échappement Twig ; utilisateur BDD aux droits minimaux ; API en lecture seule, sans donnée personnelle, paramètres validés, débit limité |
| RGPD | Données limitées au nom, email, téléphone ; conservation 3 ans après la dernière activité ; procédure d'export et de suppression documentée |
| Éco-conception | Pagination ; pas de polling ; requêtes indexées ; assets minifiés ; pas d'images dans le MVP |
| Accessibilité | Contrastes suffisants ; champs labellisés ; navigation au clavier ; statuts indiqués par un texte et pas seulement par une couleur |
| Compatibilité | Dernières versions de Firefox, Chrome, Safari et Edge ; affichage à partir de 1024 px de large |
| Exploitabilité | Docker Compose ; fixtures du jeu d'essai ; procédure de sauvegarde et de restauration |

## 8. Contraintes

| Type | Contrainte |
|---|---|
| Technique | Symfony 7, Twig, Tailwind CSS, PostgreSQL, MongoDB, Docker |
| Délai | 10 jours ouvrés plus 2 jours de marge |
| Ressources | Un binôme de développeurs : Anthony Dégeilh et Chloé Baïsse |
| Réglementaire | RGPD |
| Épreuve | Couvrir les 4 compétences du CCP 2 ; dossier de 30 à 40 pages ; présentation de 30 min |

## 9. Livrables

| Livrable | Format | Échéance |
|---|---|---|
| Cahier des charges | Markdown (ce document) | J1 |
| Diagramme de cas d'utilisation | PlantUML + SVG | J1 |
| Maquettes et enchaînement des écrans | Figma + Mermaid | J1 |
| MCD, MPD, diagramme de séquence, architecture | Diagrammes | J2 |
| Documentation de l'API (chemins, paramètres, réponses, exemples) | Markdown | J6 |
| Code source et scripts BDD | Dépôt Git | J8 |
| Plan de tests et jeu d'essai | Markdown | J7 |
| Dossier de projet et diaporama | PDF | J10 |

## 10. Critères de recette

Le MVP est accepté quand :

1. Les user stories « Must » passent leurs critères d'acceptation.
2. Toute tentative de réservation qui chevauche une réservation existante est refusée, par le service et par la BDD.
3. Chaque route protégée renvoie un refus (403 ou redirection vers la connexion) pour un rôle insuffisant.
4. Chaque action sensible produit une entrée dans le journal d'audit.
5. La base de test se recrée à l'identique avec les fixtures, et se restaure depuis une sauvegarde.

## 11. Glossaire

| Terme | Définition |
|---|---|
| Objet | Article physique prêté par l'association |
| Réservation | Demande d'emprunt d'un objet sur une période |
| Réservation active | Réservation au statut « Confirmée » ou « En cours » |
| API REST | Interface de programmation accessible par HTTP : un programme client demande une ressource par son chemin et reçoit une réponse structurée (ici en JSON) |
| JSON | Format texte d'échange de données structurées, lisible par JavaScript et par PHP |
| Sortie | Remise de l'objet à l'adhérent |
| Retour | Restitution de l'objet, avec constat de son état |
| Journal d'audit | Historique horodaté des actions sensibles, stocké dans MongoDB |
