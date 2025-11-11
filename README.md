# prestashop_bulk-action
Actions de masse utiles pour l’admin PrestaShop.

Environnement de développement Docker pour PrestaShop 9.1 inclus.

Prérequis
- Docker et Docker Compose installés

Démarrage rapide
1. Construire et lancer:
   - docker compose up -d --build
2. Attendre l’initialisation (le conteneur app télécharge le projet PrestaShop 9.1 au premier démarrage).
3. Ouvrir la boutique: http://localhost:8080
   - L’installation Web vous guidera (hôte DB: db, base: prestashop, user: prestashop, pass: prestashop, préfixe: ps_).
4. phpMyAdmin: http://localhost:8081 (user: prestashop / pass: prestashop)

Développement du module
- Le dossier ./src est monté dans le conteneur sous: /var/www/html/modules/prestashop_bulk_action
- Le code du shop est persistant dans ./prestashop
- Base de données persistante via un volume nommé

Commandes utiles
- Démarrer: docker compose up -d
- Arrêter: docker compose down
- Logs: docker compose logs -f app
- Rebuild: docker compose build --no-cache app && docker compose up -d
- Réinitialiser (ATTENTION, supprime DB et code PrestaShop):
  - docker compose down -v
  - rm -rf ./prestashop

Notes
- L’image PHP inclut les extensions nécessaires (pdo_mysql, intl, gd, zip, etc.) et Composer.
- Au premier démarrage, le projet PrestaShop 9.1 est créé dans ./prestashop.
- Vous pouvez adapter les versions de PHP/MySQL au besoin dans docker/app/Dockerfile et docker-compose.yml.
