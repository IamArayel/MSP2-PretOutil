#!/usr/bin/env bash
# Prêt'Outil : restauration des bases PostgreSQL et MongoDB (ticket #23).
#
# Usage : scripts/restauration.sh <dossier_de_sauvegarde> [--oui]
#
# Remplace les données actuelles par celles de la sauvegarde. Les conteneurs doivent être
# démarrés (docker compose up -d --wait) : à la création d'un volume, les scripts de docker/
# créent les rôles et utilisateurs applicatifs, que la sauvegarde ne contient pas.
# --oui : pas de demande de confirmation (usage dans un script).
set -euo pipefail

cd "$(dirname "$0")/.."

dossier="${1:?Usage : scripts/restauration.sh <dossier_de_sauvegarde> [--oui]}"
confirmation="${2:-}"

for fichier in postgresql.dump mongodb.archive.gz SHA256SUMS; do
    [ -f "$dossier/$fichier" ] || { echo "Fichier manquant : $dossier/$fichier" >&2; exit 1; }
done

echo "Vérification de l'intégrité..."
(cd "$dossier" && shasum -a 256 --check --quiet SHA256SUMS)

if [ "$confirmation" != "--oui" ]; then
    read -r -p "Les données actuelles seront remplacées par $dossier. Continuer ? [o/N] " reponse
    [ "$reponse" = "o" ] || { echo "Restauration annulée."; exit 1; }
fi

echo "PostgreSQL..."
# Une seule transaction : en cas d'erreur, la base reste dans son état précédent.
docker compose exec -T database sh -c \
    'pg_restore --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --clean --if-exists --no-owner --exit-on-error --single-transaction' \
    < "$dossier/postgresql.dump"

echo "MongoDB..."
# --drop : chaque collection est remplacée, avec son validateur et ses index.
docker compose exec -T mongodb sh -c \
    'mongorestore --quiet --username="$MONGO_INITDB_ROOT_USERNAME" --password="$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase=admin --nsInclude="$MONGO_INITDB_DATABASE.*" --drop --archive --gzip' \
    < "$dossier/mongodb.archive.gz"

echo "Restauration terminée depuis : $dossier"
