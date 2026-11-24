-- Prêt'Outil : plan d'exécution des requêtes du catalogue sur un volume réaliste (ticket #33).
-- Ajoute 10 000 objets et 200 000 réservations dans une transaction annulée à la fin : la base n'est pas modifiée.
-- Exécution (compte propriétaire) :
--   docker compose exec -T database sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < docs/conception/requetes_catalogue_explain.sql
-- Les insertions annulées laissent des lignes mortes : VACUUM avant et après garde des mesures comparables.
VACUUM ANALYZE objet, reservation;
BEGIN;
-- Volume de test : 10 000 objets, 20 réservations par objet (200 000), annulé en fin de script.
INSERT INTO objet (categorie_id, nom, description, etat)
SELECT (SELECT min(id) FROM categorie) + (g % 6), 'Objet ' || lpad(g::text, 5, '0'), 'Volume de test', CASE WHEN g % 50 = 0 THEN 'hors_service' ELSE 'bon' END
FROM generate_series(1, 10000) g;
INSERT INTO reservation (utilisateur_id, objet_id, date_debut, date_fin, statut, sortie_le, sortie_par_id, retour_le, retour_par_id, etat_retour)
SELECT u.id, o.id, d.debut, d.debut + 2,
       CASE WHEN d.debut > current_date THEN 'confirmee' ELSE 'terminee' END,
       CASE WHEN d.debut > current_date THEN NULL ELSE d.debut::timestamp END,
       CASE WHEN d.debut > current_date THEN NULL ELSE g.id END,
       CASE WHEN d.debut > current_date THEN NULL ELSE (d.debut + 2)::timestamp END,
       CASE WHEN d.debut > current_date THEN NULL ELSE g.id END,
       CASE WHEN d.debut > current_date THEN NULL ELSE 'bon' END
FROM objet o
CROSS JOIN LATERAL (SELECT current_date - 150 + n * 15 + (o.id % 5) AS debut FROM generate_series(0, 19) n) d
CROSS JOIN (SELECT id FROM utilisateur WHERE email = 'adherent@pretoutil.test') u
CROSS JOIN (SELECT id FROM utilisateur WHERE email = 'gestionnaire@pretoutil.test') g
WHERE o.description = 'Volume de test';
ANALYZE objet; ANALYZE reservation;
SELECT count(*) AS objets FROM objet; SELECT count(*) AS reservations FROM reservation;
EXPLAIN (ANALYZE, COSTS OFF, TIMING ON, SUMMARY ON)
SELECT o0_.id, o0_.nom, c1_.libelle FROM objet o0_ INNER JOIN categorie c1_ ON o0_.categorie_id = c1_.id
WHERE o0_.actif = true AND o0_.etat <> 'hors_service'
  AND NOT EXISTS (SELECT 1 FROM reservation r2_ WHERE r2_.objet_id = o0_.id AND r2_.statut IN ('confirmee','en_cours')
                  AND r2_.date_debut <= current_date + 9 AND r2_.date_fin >= current_date + 7)
  AND o0_.id NOT IN (SELECT r3_.objet_id FROM reservation r3_ WHERE r3_.statut = 'en_cours' AND r3_.date_fin < current_date)
ORDER BY o0_.nom ASC, o0_.id ASC LIMIT 20;
EXPLAIN (ANALYZE, COSTS OFF, TIMING ON, SUMMARY ON)
SELECT r0_.date_debut, r0_.date_fin FROM reservation r0_
WHERE r0_.objet_id = (SELECT max(id) FROM objet) AND r0_.statut IN ('confirmee','en_cours') AND r0_.date_fin >= current_date ORDER BY r0_.date_debut;
ROLLBACK;
VACUUM ANALYZE objet, reservation;
