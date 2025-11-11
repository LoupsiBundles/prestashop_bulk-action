SHELL := /bin/bash

.PHONY: up down logs rebuild reset

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
