# Enchaînement des écrans

Version éditable dans FigJam : voir le lien dans le [README](../../README.md#maquettes-et-diagrammes).

Chaque rôle a accès aux écrans de son groupe et à ceux des groupes précédents (hiérarchie des rôles). Les liens « Menu » passent par la barre de navigation, qui n'affiche que les entrées autorisées pour le rôle connecté.

```mermaid
flowchart LR
  subgraph Public["Visiteur"]
    E3["E3 Catalogue (accueil)"]
    E4["E4 Détail et réservation"]
    E1["E1 Connexion"]
    E2["E2 Inscription"]
  end
  subgraph Adh["Adhérent"]
    E5["E5 Mes réservations"]
  end
  subgraph Gest["Gestionnaire"]
    E6["E6 Sorties et retours"]
    E7["E7 Gestion du catalogue"]
  end
  subgraph Adm["Administrateur"]
    E8["E8 Gestion des comptes"]
    E9["E9 Journal d'audit"]
  end
  E3 -->|"Voir le détail"| E4
  E4 -->|"Réserver sans être connecté"| E1
  E1 -->|"Créer un compte"| E2
  E2 -->|"Compte créé"| E1
  E1 -->|"Connexion réussie"| E3
  E4 -->|"Période indisponible : message d'erreur"| E4
  E4 -->|"Réservation confirmée"| E5
  E5 -->|"Annuler une réservation"| E5
  E3 -->|"Menu : Mes réservations"| E5
  E3 -->|"Menu : Sorties et retours"| E6
  E3 -->|"Menu : Gestion du catalogue"| E7
  E3 -->|"Menu : Comptes"| E8
  E3 -->|"Menu : Journal d'audit"| E9
```

| Écran | Maquetté | Remarque |
|---|---|---|
| E1 Connexion | Oui | |
| E2 Inscription | Non | Même structure que E1, avec les champs nom, email, téléphone, mot de passe |
| E3 Catalogue | Oui | |
| E4 Détail et réservation | Oui | Montre le message d'erreur de RG2 |
| E5 Mes réservations | Oui | |
| E6 Sorties et retours | Oui | |
| E7 Gestion du catalogue | Non | Tableau + formulaire, mêmes composants que E5 et E1 |
| E8 Gestion des comptes | Non | Tableau, mêmes composants que E9 |
| E9 Journal d'audit | Oui | |
