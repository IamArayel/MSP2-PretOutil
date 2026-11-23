#!/usr/bin/env bash
# Prêt'Outil : sauvegarde des bases PostgreSQL et MongoDB (ticket #23).
#
# Usage : scripts/sauvegarde.sh [dossier]   (par défaut : sauvegardes/, non versionné)
#
# Crée <dossier>/<AAAAMMJJ-HHMMSS>/ contenant :
#   postgresql.dump      pg_dump au format custom (schéma, données, droits)
#   mongodb.archive.gz   mongodump compressé de la base de l'application
#   SHA256SUMS           empreintes, vérifiées avant toute restauration
#
# Les identifiants sont lus dans les conteneurs (variables issues de .env.local) :
# aucun mot de passe dans ce script. Les rôles et utilisateurs applicatifs ne sont pas
# sauvegardés : ils sont recréés par les scripts d'initialisation de docker/.
set -euo pipefail

cd "$(dirname "$0")/.."

racine="${1:-sauvegardes}"
dossier="$racine/$(date -u +%Y%m%d-%H%M%S)"

# Données personnelles (RGPD) : fichiers lisibles par le seul utilisateur courant.
umask 077
mkdir -p "$dossier"
trap 'echo "Échec : sauvegarde incomplète supprimée." >&2; rm -rf "$dossier"' ERR

echo "PostgreSQL..."
docker compose exec -T database sh -c \
    'pg_dump --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --format=custom' \
    > "$dossier/postgresql.dump"

echo "MongoDB..."
docker compose exec -T mongodb sh -c \
    'mongodump --quiet --username="$MONGO_INITDB_ROOT_USERNAME" --password="$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase=admin --db="$MONGO_INITDB_DATABASE" --archive --gzip' \
    > "$dossier/mongodb.archive.gz"

(cd "$dossier" && shasum -a 256 postgresql.dump mongodb.archive.gz > SHA256SUMS)

echo "Sauvegarde terminée : $dossier"
