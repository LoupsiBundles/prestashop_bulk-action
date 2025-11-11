# prestashop_bulk-action
Actions de masse utiles pour l’admin PrestaShop.

Environnement de développement basé sur PrestaShop Flashlight (recommandé pour le développement de modules).

Prérequis
- Docker et Docker Compose installés

Démarrage rapide (Flashlight)
1. Lancer: docker compose -f docker-compose.flashlight.yml up -d
2. Ouvrir la boutique: http://localhost:8080
3. Votre module est monté depuis ./src vers /var/www/html/modules/prestashop_bulk_action

Développement du module
- Placez le code du module dans ./src (mappé dans le conteneur sous /var/www/html/modules/prestashop_bulk_action)
- Le code du shop peut être persisté localement dans ./prestashop (volume monté par défaut)
- La base MySQL 8.0 est fournie par le service db (identifiants: prestashop / prestashop)

Commandes utiles (Makefile)
- Démarrer: make up
- Arrêter: make down
- Logs: make logs
- Rebuild (maj image et recréation): make rebuild
- Réinitialiser (ATTENTION, supprime DB et code PrestaShop): make reset

Notes
- Image utilisée: prestashop/prestashop:9.0.1 (modifiable dans docker-compose.flashlight.yml)
- Variables DB par défaut: DB_SERVER=db, DB_NAME=prestashop, DB_USER=prestashop, DB_PASSWORD=prestashop, DB_PREFIX=ps_
- Vous pouvez changer la balise d’image pour cibler une autre version de PrestaShop.
