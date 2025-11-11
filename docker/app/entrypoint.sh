#!/usr/bin/env bash
set -euo pipefail

DOCROOT="/var/www/html"

# Ensure docroot exists (mounted volume)
mkdir -p "$DOCROOT"

# If PrestaShop project not present, create it (first run)
if [ ! -f "$DOCROOT/composer.json" ]; then
  echo "[entrypoint] Initialisation du projet PrestaShop 9.1 dans $DOCROOT ..."
  composer create-project prestashop/prestashop-project:^9.1 "$DOCROOT" --no-interaction
  # Autoriser l'écriture pour dev
  chown -R www-data:www-data "$DOCROOT"
  find "$DOCROOT" -type d -print0 | xargs -0 chmod 775
  find "$DOCROOT" -type f -print0 | xargs -0 chmod 664
else
  echo "[entrypoint] Projet déjà présent, pas d'initialisation."
fi

# Afficher quelques infos utiles
echo "[entrypoint] DB_HOST=${DB_SERVER:-db} DB_NAME=${DB_NAME:-prestashop}"

exec "$@"
