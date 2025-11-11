SHELL := /bin/bash

.PHONY: up down logs rebuild reset pma

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f app

rebuild:
	docker compose build --no-cache app && docker compose up -d

reset:
	@read -p "Cette action va supprimer la DB et le code PrestaShop généré. Continuer ? (y/N) " ans; \
	if [[ "$$ans" == "y" || "$$ans" == "Y" ]]; then \
		docker compose down -v; \
		rm -rf ./prestashop; \
		echo "Réinitialisation terminée"; \
	else \
		echo "Annulé"; \
	fi

pma:
	open http://localhost:8081 || xdg-open http://localhost:8081 || true
