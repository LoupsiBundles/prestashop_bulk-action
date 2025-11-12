SHELL := /bin/bash

# Admin folder name of your PrestaShop Back Office (override with: make <target> ADMIN=admin057xyvfahjeptmievrs)
ADMIN ?= admin-dev

.PHONY: up down logs rebuild reset cc test reinstall

up:
	docker compose -f docker-compose.flashlight.yml up -d

down:
	docker compose -f docker-compose.flashlight.yml down

logs:
	docker compose -f docker-compose.flashlight.yml logs -f prestashop

rebuild:
	docker compose -f docker-compose.flashlight.yml pull prestashop && \
	docker compose -f docker-compose.flashlight.yml up -d --force-recreate

reset:
	@read -p "Cette action va supprimer la DB et le code PrestaShop généré. Continuer ? (y/N) " ans; \
	if [[ "$$ans" == "y" || "$$ans" == "Y" ]]; then \
		docker compose -f docker-compose.flashlight.yml down -v; \
		rm -rf ./prestashop; \
		echo "Réinitialisation terminée"; \
	else \
		echo "Annulé"; \
	fi

# Vider le cache PrestaShop/Symfony dans le conteneur
cc:
	docker exec -it ps-flashlight php bin/console cache:clear

test:
	docker exec -it ps-flashlight bash -c "cd /var/www/html/modules/prestashop_bulk_action && ./vendor/bin/phpunit --colors=always"

reinstall:
	@echo "🔄 Désinstallation du module..."
	docker exec -it ps-flashlight php bin/console prestashop:module uninstall prestashop_bulk_action || true
	@echo "📦 Installation du module..."
	docker exec -it ps-flashlight php bin/console prestashop:module install prestashop_bulk_action
	@echo "✅ Module réinstallé avec succès!"

# --- Helpers BO JS routing & assets ---
.PHONY: js-routes-dump admin-assets-build

# Dump des routes JS (FOSJsRouting) vers le fichier chargé par le BO
js-routes-dump:
	@echo "🗺  Dump FOSJsRouting vers $${ADMIN}/themes/new-theme/js/fos_js_routes.json"
	- docker exec -it ps-flashlight php bin/console fos:js-routing:dump --format=json --target=$${ADMIN}/themes/new-theme/js/fos_js_routes.json || true
	@echo "ℹ️  Si votre dossier admin diffère, relancez avec: make js-routes-dump ADMIN=adminXXXX"

# Build des assets BO (Webpack Encore) – nécessite Node/Yarn dans le conteneur
admin-assets-build:
	@echo "🧱 Build des assets BO dans $${ADMIN}/themes/new-theme (yarn install && yarn build)"
	- docker exec -it ps-flashlight bash -lc "cd $$ADMIN/themes/new-theme && yarn install && yarn build" || (echo "❗️Échec du build. Vérifiez que Node/Yarn sont installés dans le conteneur." && exit 1)
	@echo "✅ Build terminé. Pensez à vider le cache BO et à hard‑refresh le navigateur."
