-- Prêt'Outil : jeu d'essai des contraintes du MPD (ticket #2)
-- Prérequis : mpd.sql exécuté. Tout est annulé en fin de script (ROLLBACK).
-- Exécution : psql -v ON_ERROR_STOP=1 -d pretoutil -f docs/conception/mpd_tests.sql

BEGIN;

CREATE TEMP TABLE resultat (
    num      SERIAL,
    cas      TEXT,
    attendu  TEXT,
    obtenu   TEXT
);

-- Exécute une requête et note si la base l'accepte ou la refuse (avec la contrainte en cause).
CREATE FUNCTION pg_temp.essai(p_cas TEXT, p_sql TEXT, p_attendu TEXT) RETURNS VOID AS $$
DECLARE
    v_contrainte TEXT;
BEGIN
    BEGIN
        EXECUTE p_sql;
        INSERT INTO resultat (cas, attendu, obtenu) VALUES (p_cas, p_attendu, 'accepté');
    EXCEPTION WHEN OTHERS THEN
        GET STACKED DIAGNOSTICS v_contrainte = CONSTRAINT_NAME;
        INSERT INTO resultat (cas, attendu, obtenu)
        VALUES (p_cas, p_attendu, 'refusé (' || COALESCE(NULLIF(v_contrainte, ''), SQLSTATE) || ')');
    END;
END;
$$ LANGUAGE plpgsql;

-- Données de départ
INSERT INTO categorie (id, libelle) VALUES (1, 'Outillage');
INSERT INTO utilisateur (id, email, mot_de_passe, nom, prenom, roles) VALUES
    (1, 'camille.martin@exemple.fr', '$2y$13$empreinte', 'Martin', 'Camille', '["ROLE_USER"]'),
    (2, 'lea.bernard@exemple.fr', '$2y$13$empreinte', 'Bernard', 'Léa', '["ROLE_MANAGER"]');
INSERT INTO objet (id, categorie_id, nom, description) VALUES
    (1, 1, 'Perceuse-visseuse 18 V', 'Avec 2 batteries'),
    (2, 1, 'Scie sauteuse', 'Lames bois et métal');

-- Les id explicites ci-dessus ne font pas avancer les séquences d'identité :
-- sans resynchronisation, le prochain INSERT sans id réutiliserait l'id 1 (conflit de clé primaire).
SELECT setval(pg_get_serial_sequence('categorie', 'id'), (SELECT MAX(id) FROM categorie));
SELECT setval(pg_get_serial_sequence('utilisateur', 'id'), (SELECT MAX(id) FROM utilisateur));
SELECT setval(pg_get_serial_sequence('objet', 'id'), (SELECT MAX(id) FROM objet));

-- RG2 : chevauchement
SELECT pg_temp.essai('Réservation valide, objet 1, 12/12 au 14/12',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin) VALUES (1, 1, '2026-12-12', '2026-12-14')$q$,
    'accepté');
SELECT pg_temp.essai('Chevauchement, objet 1, 13/12 au 15/12',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin) VALUES (1, 1, '2026-12-13', '2026-12-15')$q$,
    'refusé (ex_reservation_chevauchement)');
SELECT pg_temp.essai('Début le jour de fin d''une autre, objet 1, 14/12 au 16/12',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin) VALUES (1, 1, '2026-12-14', '2026-12-16')$q$,
    'refusé (ex_reservation_chevauchement)');
SELECT pg_temp.essai('Période suivante, objet 1, 15/12 au 16/12',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin) VALUES (1, 1, '2026-12-15', '2026-12-16')$q$,
    'accepté');
SELECT pg_temp.essai('Même période sur un autre objet, objet 2, 12/12 au 14/12',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin) VALUES (1, 2, '2026-12-12', '2026-12-14')$q$,
    'accepté');
SELECT pg_temp.essai('Réservation annulée, objet 1, 20/12 au 22/12',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin, statut) VALUES (1, 1, '2026-12-20', '2026-12-22', 'annulee')$q$,
    'accepté');
SELECT pg_temp.essai('Période d''une réservation annulée, objet 1, 21/12 au 23/12',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin) VALUES (1, 1, '2026-12-21', '2026-12-23')$q$,
    'accepté');

-- RG1 et valeurs contrôlées
SELECT pg_temp.essai('Date de fin avant la date de début',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin) VALUES (1, 2, '2027-01-10', '2027-01-08')$q$,
    'refusé (ck_reservation_dates)');
-- Un statut inconnu viole aussi ck_reservation_cycle_de_vie ; PostgreSQL évalue les
-- contraintes CHECK dans l'ordre alphabétique de leur nom et signale la première violée.
SELECT pg_temp.essai('Statut inconnu',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin, statut) VALUES (1, 2, '2027-02-01', '2027-02-02', 'perdue')$q$,
    'refusé (ck_reservation_cycle_de_vie)');
SELECT pg_temp.essai('État d''objet inconnu',
    $q$INSERT INTO objet (categorie_id, nom, description, etat) VALUES (1, 'Ponceuse', 'Orbitale', 'cassé')$q$,
    'refusé (ck_objet_etat)');

-- Cycle de vie
SELECT pg_temp.essai('Statut en cours sans sortie',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin, statut) VALUES (1, 2, '2027-03-01', '2027-03-02', 'en_cours')$q$,
    'refusé (ck_reservation_cycle_de_vie)');
SELECT pg_temp.essai('Sortie sans gestionnaire',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin, statut, sortie_le) VALUES (1, 2, '2027-03-01', '2027-03-02', 'en_cours', '2027-03-01 09:00')$q$,
    'refusé (ck_reservation_sortie)');
SELECT pg_temp.essai('Sortie complète (date + gestionnaire)',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin, statut, sortie_le, sortie_par_id) VALUES (1, 2, '2027-03-01', '2027-03-02', 'en_cours', '2027-03-01 09:00', 2)$q$,
    'accepté');
SELECT pg_temp.essai('Retour sans état',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin, statut, sortie_le, sortie_par_id, retour_le, retour_par_id) VALUES (1, 2, '2027-04-01', '2027-04-02', 'terminee', '2027-04-01 09:00', 2, '2027-04-02 18:00', 2)$q$,
    'refusé (ck_reservation_retour)');
SELECT pg_temp.essai('Retour avant la sortie',
    $q$INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin, statut, sortie_le, sortie_par_id, retour_le, retour_par_id, etat_retour) VALUES (1, 2, '2027-04-01', '2027-04-02', 'terminee', '2027-04-02 09:00', 2, '2027-04-01 18:00', 2, 'bon')$q$,
    'refusé (ck_reservation_retour_apres_sortie)');

-- Utilisateurs
SELECT pg_temp.essai('Email en majuscules',
    $q$INSERT INTO utilisateur (email, mot_de_passe, nom, prenom) VALUES ('Noah.Durand@exemple.fr', 'x', 'Durand', 'Noah')$q$,
    'refusé (ck_utilisateur_email_minuscules)');
SELECT pg_temp.essai('Email déjà utilisé',
    $q$INSERT INTO utilisateur (email, mot_de_passe, nom, prenom) VALUES ('camille.martin@exemple.fr', 'x', 'Martin', 'Camille')$q$,
    'refusé (uq_utilisateur_email)');

-- Intégrité référentielle
SELECT pg_temp.essai('Supprimer une catégorie qui contient des objets',
    $q$DELETE FROM categorie WHERE id = 1$q$,
    'refusé (fk_objet_categorie)');
SELECT pg_temp.essai('Supprimer un objet déjà réservé',
    $q$DELETE FROM objet WHERE id = 1$q$,
    'refusé (fk_reservation_objet)');

-- Droits de l'utilisateur applicatif
INSERT INTO resultat (cas, attendu, obtenu)
SELECT 'Droit DELETE de pretoutil_app sur reservation', 'refusé',
       CASE WHEN has_table_privilege('pretoutil_app', 'reservation', 'DELETE') THEN 'accepté' ELSE 'refusé' END
UNION ALL
SELECT 'Droit INSERT de pretoutil_app sur reservation', 'accepté',
       CASE WHEN has_table_privilege('pretoutil_app', 'reservation', 'INSERT') THEN 'accepté' ELSE 'refusé' END;

SELECT num AS "n°", cas, attendu, obtenu,
       CASE WHEN attendu = obtenu THEN 'OK' ELSE 'ÉCART' END AS bilan
FROM resultat ORDER BY num;

SELECT COUNT(*) FILTER (WHERE attendu = obtenu) AS ok,
       COUNT(*) FILTER (WHERE attendu <> obtenu) AS ecarts
FROM resultat;

ROLLBACK;
