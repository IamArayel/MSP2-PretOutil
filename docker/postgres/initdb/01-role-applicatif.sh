#!/bin/sh
# Prêt'Outil : crée le rôle applicatif pretoutil_app à la création du volume PostgreSQL.
# Les droits sur les tables (GRANT) sont posés par une migration, une fois les tables créées.
# Le mot de passe vient de .env.local (POSTGRES_APP_PASSWORD), jamais du dépôt.
set -eu

: "${POSTGRES_APP_PASSWORD:?POSTGRES_APP_PASSWORD doit être défini dans .env.local}"

psql -v ON_ERROR_STOP=1 \
    --username "$POSTGRES_USER" \
    --dbname "$POSTGRES_DB" \
    --set mot_de_passe="$POSTGRES_APP_PASSWORD" <<'EOSQL'
CREATE ROLE pretoutil_app LOGIN PASSWORD :'mot_de_passe';
-- Aucun droit par défaut sur le schéma public : seuls les GRANT de la migration s'appliquent
REVOKE ALL ON SCHEMA public FROM PUBLIC;
EOSQL
