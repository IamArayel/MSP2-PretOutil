# Accessibilité de base

Tickets : [#77 J8-A2 Écrans adhérent (E1 à E5)](https://github.com/IamArayel/MSP2-PretOutil/issues/77) · [#78 J8-B1 Écrans de gestion (E6 à E9)](https://github.com/IamArayel/MSP2-PretOutil/issues/78) · Plan : [plan-de-tests.md](plan-de-tests.md)

Objectif du MVP : les critères essentiels du RGAA 4.1 (niveau AA du WCAG 2.1) sont conformes, ou leur écart est justifié, sur chaque écran. Un audit RGAA complet (106 critères) est hors de portée du projet.

## 1. Méthode

| Vérification | Comment | Qui |
|---|---|---|
| Structure : langue, titre de page, titres, étiquettes, aide et erreurs reliées aux champs, tableaux, lien d'évitement | `tests/Controller/AccessibiliteTest.php`, sur chaque écran et dans ses états d'erreur | Automatique, à chaque lancement de la suite |
| Contrastes des couleurs de la charte | [contrastes_charte.py](contrastes_charte.py) : rapports calculés depuis les couleurs Tailwind compilées | Automatique, à relancer si la charte change |
| Navigation au clavier, lecteur d'écran, zoom à 200 %, affichage mobile | Dans un vrai navigateur (le certificat local bloque les navigateurs automatisés) | À la main, section 5 |

## 2. Grille des écrans adhérent (E1 à E5)

Vérifiée le 2026-11-24. ✅ conforme · 🔧 corrigé à J8 · ➖ sans objet.

| Critère (RGAA 4.1) | Accueil | E1 Connexion | E2 Inscription | E3 Catalogue | E4 Fiche et réservation | E5 Mes réservations |
|---|---|---|---|---|---|---|
| 8.3 Langue de la page (`lang="fr"`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.6 Titre de page pertinent | ✅ | ✅ | ✅ | ✅ | ✅ nom de l'objet | ✅ |
| 9.1 Un `h1`, niveaux de titres sans saut | ✅ | ✅ | ✅ | ✅ un `h2` par objet | ✅ | ✅ |
| 11.1 Chaque champ a une étiquette | ➖ | ✅ | ✅ | ✅ | ✅ | ➖ |
| 11.2 Étiquette pertinente | ➖ | ✅ | ✅ | 🔧 « au » devient « jusqu'au » | 🔧 « Du » et « Au » précisés | ➖ |
| 11.10 Champs obligatoires signalés, aide et erreurs reliées au champ | ➖ | ✅ message générique | 🔧 mention « tous obligatoires » ; aide du mot de passe reliée | 🔧 une seule bordure d'erreur | ✅ | ➖ |
| 11.13 Finalité des champs (`autocomplete`) | ➖ | ✅ | ✅ | ➖ | ➖ | ➖ |
| 3.2 Contraste des textes (4,5:1) | ✅ | 🔧 texte indicatif | 🔧 texte indicatif | ✅ | ✅ | ✅ |
| 3.3 Contraste des éléments d'interface (3:1) | ✅ | 🔧 contour des champs | 🔧 contour des champs | 🔧 contour des champs | 🔧 contour des champs | ✅ |
| 5.4 et 5.7 Tableau titré, en-têtes de colonnes et de lignes | ➖ | ➖ | ➖ | ➖ | ➖ | 🔧 objet en en-tête de ligne |
| 6.1 Lien explicite | ✅ | ✅ | ✅ | ✅ « Voir le détail : nom » | ✅ fil d'Ariane | ✅ |
| 7.5 Message de statut annoncé | ➖ | ✅ `role="alert"` | ✅ | ✅ | ✅ réponse de l'API en `aria-live` | ✅ confirmation en `role="status"` |
| 10.7 Focus visible | ✅ contour `teal-700` | ✅ | ✅ | ✅ | ✅ | ✅ |
| 12.7 Lien d'évitement vers le contenu | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 12.8 Ordre de tabulation (aucun `tabindex` positif) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Navigation : page en cours signalée (`aria-current`) | ➖ | ➖ | ➖ | ✅ | ✅ | 🔧 lien « Mes réservations » |

## 3. Contrastes de la charte

Calculés le 2026-11-24 par [contrastes_charte.py](contrastes_charte.py). Seuils : 4,5:1 pour un texte, 3:1 pour un élément d'interface et le focus.

| Couleur | Couple | Rapport | Seuil | Résultat |
|---|---|---|---|---|
| Texte principal | `slate-900` sur blanc | 17,84:1 | 4,5:1 | Conforme |
| Texte secondaire | `slate-600` sur blanc | 7,56:1 | 4,5:1 | Conforme |
| Texte secondaire sur le fond de page | `slate-600` sur `slate-50` | 7,23:1 | 4,5:1 | Conforme |
| Lien, bouton principal | `teal-700` et blanc | 5,39:1 | 4,5:1 | Conforme |
| Badge Disponible, message de succès | `green-700` sur `green-50` | 4,72:1 | 4,5:1 | Conforme |
| Badge Réservé | `blue-700` sur `blue-50` | 6,26:1 | 4,5:1 | Conforme |
| Badge Sorti, avertissement | `amber-700` sur `amber-50` | 4,87:1 | 4,5:1 | Conforme |
| Badge Hors service, erreur | `red-700` sur `red-50` | 5,88:1 | 4,5:1 | Conforme |
| Texte indicatif des champs, avant | `slate-400` sur blanc | 2,63:1 | 4,5:1 | **Corrigé** : `slate-500`, 4,77:1 |
| Contour des champs, avant | `slate-200` sur blanc | 1,23:1 | 3:1 | **Corrigé** : couleur `champ` (`slate-500`), 4,77:1 |
| Focus clavier | `teal-700` sur blanc | 5,39:1 | 3:1 | Conforme |

Les boutons « Précédent » et « Suivant » désactivés (`slate-400`) ne sont pas soumis au contraste : ce sont des éléments inactifs (WCAG 1.4.3).

## 4. Corrections de J8 (E1 à E5)

| Écart | Correction | Fichier |
|---|---|---|
| Aide du mot de passe (« 12 caractères minimum ») jamais reliée au champ : notre thème remplace le `form_row` de Symfony, qui le faisait | Aide et erreurs reliées par `aria-describedby` dans chaque type de champ ; en erreur, le lecteur d'écran lit l'aide puis l'erreur | `templates/form/theme.html.twig` |
| Contour des champs presque invisible (1,23:1) | Nouvelle couleur de charte `champ` (`slate-500`) pour le contour des champs ; `line` reste pour séparer des blocs | `assets/styles/app.css` |
| Texte indicatif peu lisible (2,63:1) | `placeholder:text-slate-500` | Thème, E1 |
| Liste des catégories de E3 : deux couleurs de bordure contradictoires en erreur | Classes du thème seules | `templates/catalogue/index.html.twig` |
| Étiquettes « Du », « Au » et « au » ambiguës hors de leur contexte visuel | « Du (premier jour) », « Au (dernier jour, inclus) » ; « Disponible du » et « jusqu'au » | `ReservationType`, `FiltreCatalogueType` |
| E2 : champs obligatoires non signalés | Mention « Tous les champs sont obligatoires, sauf mention contraire » ; le téléphone porte déjà « (facultatif) » | `templates/inscription/index.html.twig` |
| E5 : l'objet n'était pas l'en-tête de ligne du tableau | `<th scope="row">` : le lecteur d'écran annonce l'objet avec chaque cellule | `templates/mes_reservations/index.html.twig` |
| Navigation : « Mes réservations » non signalé comme page en cours | `aria-current="page"` | `templates/_partials/navbar.html.twig` |

Preuve : `AccessibiliteTest` (13 tests). En retirant le lien vers l'aide dans le thème, 3 tests échouent.

## 5. Vérifications manuelles

Le certificat local bloque les navigateurs automatisés : ces contrôles se font dans un vrai navigateur. Ce que le code garantit déjà est indiqué ; le résultat reste à confirmer à la main.

| Contrôle | Déjà garanti par le code | Résultat à la main |
|---|---|---|
| Parcours au clavier seul : se connecter, filtrer le catalogue, réserver, annuler | Ordre de tabulation = ordre du HTML, aucun `tabindex` positif, focus visible partout, confirmation d'annulation par `window.confirm` (accessible au clavier) | À faire |
| Lien d'évitement : premier élément atteint par Tab, mène au contenu | Premier lien de la page, visible au focus | À faire |
| VoiceOver sur E4 : message de l'API annoncé après la saisie des dates | Zone `role="status"` et `aria-live="polite"` toujours présente, texte inséré par `textContent` | À faire |
| VoiceOver sur E2 en erreur : erreur et aide lues avec le champ | `aria-describedby` et `aria-invalid` (testés) | À faire |
| Zoom à 200 % : aucun contenu coupé ni masqué | Mises en page en flex et grille qui passent à la ligne | À faire |
| Mobile (375 px) : navigation, cartes du catalogue, formulaire de réservation, tableau de E5 | Balise `viewport`, colonnes empilées sous `sm`, tableau de E5 défilant seul (`overflow-x-auto`) | À faire |

## 6. Grille des écrans de gestion (E6 à E9)

Vérifiée le 2026-11-24 ([#78](https://github.com/IamArayel/MSP2-PretOutil/issues/78)). ✅ conforme · 🔧 corrigé à J8 · ➖ sans objet.

| Critère (RGAA 4.1) | E6 Sorties et retours | E7 Gestion du catalogue | E7 Formulaire d'objet | E8 Gestion des comptes | E9 Journal d'audit |
|---|---|---|---|---|---|
| 8.3 Langue, 8.6 titre de page | ✅ | ✅ | ✅ « Ajouter un objet », « Modifier « nom » » | ✅ | ✅ |
| 9.1 Un `h1`, niveaux de titres sans saut | ✅ un `h2` par tableau | ✅ | ✅ | ✅ | ✅ |
| 11.1 et 11.2 Chaque champ a une étiquette pertinente | ✅ « État de *objet* au retour » (masquée) | ➖ | ✅ | ✅ « Rôle de *nom* » (masquée) | ✅ « Type d'action » ; 🔧 options en clair (« Connexion échouée ») au lieu des codes |
| 11.10 Champs obligatoires signalés, erreurs reliées | ➖ | ➖ | 🔧 mention « Tous les champs sont obligatoires, sauf mention contraire » ; erreurs reliées par le thème | ➖ | ➖ |
| 3.2 Contraste des textes (4,5:1) | ✅ | ✅ ligne d'un objet retiré : 7,23:1 | ✅ | ✅ | ✅ |
| 3.3 Contraste des éléments d'interface (3:1) | 🔧 contour de la liste « État au retour » | ➖ | ✅ thème | ✅ | 🔧 contour de la liste du filtre |
| 5.4 Tableau titré (`caption`) | 🔧 deux légendes ajoutées | ✅ | ➖ | ✅ | ✅ |
| 5.7 En-têtes de colonnes (`scope="col"`) et de lignes (`scope="row"`) | ✅ objet en en-tête de ligne | ✅ | ➖ | ✅ nom en en-tête de ligne | ✅ colonnes |
| 6.1 Lien ou bouton explicite, nom accessible qui contient le texte visible (WCAG 2.5.3) | ✅ « Valider la sortie : *objet*, *adhérent* » | 🔧 « Désactiver » était annoncé « Retirer du catalogue : … » | ✅ « ← Gestion du catalogue » | ✅ « Changer le rôle de *nom* » | ✅ |
| 7.5 Message après une action annoncé | ✅ `role="status"` (succès), `role="alert"` (refus, avertissement RG7) | ✅ | ✅ erreurs du formulaire | ✅ | ✅ filtre inconnu en `role="alert"` |
| 10.7 Focus visible | ✅ contour `teal-700`, y compris le cadre du tableau | ✅ | ✅ | ✅ | ✅ |
| 12.8 Ordre de tabulation (aucun `tabindex` positif) | ✅ | ✅ | ✅ | ✅ | ✅ |
| 10.11 Affichage mobile : le tableau défile dans son cadre, pas la page | 🔧 | 🔧 | ➖ | 🔧 | 🔧 |
| Navigation : page en cours signalée (`aria-current`) | ✅ | ✅ | ✅ | ✅ | ✅ |
| États vides | 🔧 « Aucune sortie à valider aujourd'hui. », « Aucun retour attendu. » | ✅ « Aucun objet dans le catalogue. » | ➖ | ✅ « Aucun compte. » | 🔧 « Aucune action de ce type. » avec un filtre |

## 7. Corrections de J8 (E6 à E9)

| Écart | Correction | Fichier |
|---|---|---|
| Présentation différente d'un écran à l'autre : en-têtes de tableau en majuscules sur fond gris (E6, E9, maquettes) ou non (E7, E8), boutons de tailles différentes, trois copies de la pagination | Composants communs dans la feuille de styles (`cadre-tableau`, `tableau`, `tableau-entete`, `cellule-entete`, `cellule`, `cellule-vide`, `badge`, `bouton-ligne` et ses variantes, `liste-choix`), d'après les maquettes E6 et E9 ; pagination dans une seule inclusion | `assets/styles/app.css`, `templates/_partials/pagination.html.twig` |
| Tableaux de E6 sans légende | `<caption class="sr-only">` sur chacun | `templates/sorties_retours/index.html.twig` |
| Bouton « Désactiver » de E7 annoncé « Retirer du catalogue : … » : un utilisateur de commande vocale qui dit « Désactiver » n'est pas compris | « Désactiver : *objet* (retirer du catalogue) » | `templates/gestion_catalogue/index.html.twig` |
| Contour des listes « État au retour » (E6) et du filtre (E9) à 1,23:1 | `liste-choix` : couleur `champ`, 4,77:1 | E6, E9 |
| Tableaux tassés sur mobile, texte coupé sur plusieurs lignes | Largeur minimale du tableau (768 px, 1024 px pour E8) et défilement dans le cadre ; cadre nommé (`role="region"`) et atteignable au clavier (`tabindex="0"`) pour le faire défiler sans souris | E6 à E9 |
| Liste du filtre de E9 : codes techniques lus lettre à lettre par le lecteur d'écran (« CONNEXION_ECHOUEE ») | Libellés en clair (`ActionAudit::libelle()`) ; le badge garde le code, comme la maquette | `src/Enum/ActionAudit.php`, E9 |
| Formulaire d'objet : champs obligatoires non signalés | Même mention que E2 | `templates/gestion_catalogue/formulaire.html.twig` |
| États vides formulés différemment | « Aucune sortie à valider aujourd'hui. », « Aucun retour attendu. », « Aucune action de ce type. » | E6, E9 |

Preuve : `AccessibiliteTest`, étendu aux écrans de gestion (69 tests). Trois vérifications s'ajoutent pour tous les écrans : nom accessible qui contient le texte visible, contour des listes de choix, tableaux dans un cadre défilant. En réintroduisant un à un l'ancien nom du bouton « Désactiver », l'ancien contour de la liste de E6, la légende manquante et un cadre non atteignable au clavier, un test échoue à chaque fois.

## 8. Vérifications manuelles des écrans de gestion

Même méthode que la section 5, dans un vrai navigateur.

| Parcours au clavier seul | Déjà garanti par le code | Résultat à la main |
|---|---|---|
| Valider une sortie (E6) | Bouton atteint par Tab dans l'ordre de la ligne ; message de succès en `role="status"` | À faire |
| Enregistrer un retour hors service (E6) | Liste « État au retour » puis bouton de la même ligne ; avertissement RG7 en `role="alert"` | À faire |
| Créer puis désactiver un objet (E7) | Formulaire du thème (étiquettes, erreurs reliées) ; « Désactiver » atteint dans la ligne de l'objet | À faire |
| Filtrer le journal (E9) | Liste, puis « Filtrer » ; « Réinitialiser » apparaît ensuite | À faire |
| Mobile (375 px) : E6 à E9 | Tableaux défilants dans leur cadre, page sans défilement horizontal | À faire |
