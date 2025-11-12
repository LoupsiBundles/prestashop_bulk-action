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

Fonctionnalité du module (initiale)
- Ajoute une action groupée dans le BO sur la page Produits (/sell/catalog/products): « Rendre disponible à la vente ».
- Cette action met à jour le champ "Disponible à la vente" (available_for_order) à Oui pour tous les produits sélectionnés, sur la boutique courante.

Installation et test
1. Démarrer l’environnement (voir ci-dessus) et accéder au Back‑Office.
2. Aller dans Modules > Module Manager, chercher « Bulk actions produits » et installer le module.
3. Aller dans Catalogue > Produits, sélectionner quelques produits, ouvrir le menu d’actions groupées et cliquer « Rendre disponible à la vente ».
4. Un message de confirmation doit apparaître; vous pouvez vérifier en base (table ps_product_shop.available_for_order = 1) ou dans l’édition du produit.

Commandes utiles (Makefile)
- Démarrer: make up
- Arrêter: make down
- Logs: make logs
- Rebuild (maj image et recréation): make rebuild
- Réinitialiser (ATTENTION, supprime DB et code PrestaShop): make reset
- Vider le cache PrestaShop/Symfony: make cc
- Lancer les tests unitaires: make test

Notes
- Image utilisée: prestashop/prestashop:9.0.1 (modifiable dans docker-compose.flashlight.yml)
- Variables DB par défaut: DB_SERVER=db, DB_NAME=prestashop, DB_USER=prestashop, DB_PASSWORD=prestashop, DB_PREFIX=ps_
- Vous pouvez changer la balise d’image pour cibler une autre version de PrestaShop.

Conformité et guidelines
- Le module suit la structure officielle des modules PrestaShop (PS 9): fichiers du module sous src/ avec les répertoires standard (config/, controllers/, translations/, upgrade/) et des fichiers index.php de garde. Voir: https://devdocs.prestashop-project.org/9/modules/creation/module-file-structure/
- Les services et routes sont déclarés via src/prestashop.json (sections services et routes) pointant vers src/config/services.yml et src/config/routes.yml.
- Composer est configuré selon les recommandations: type prestashop-module, autoload PSR-4, licence AFL-3.0, contrainte PHP >=7.2.5, options d’autoloader optimisé (voir src/composer.json). Réf: https://devdocs.prestashop-project.org/9/modules/concepts/composer/
- Guidelines locales du dépôt: voir ./.junie/guidelines.md et ./guidelines.md.

Tests
- Les tests unitaires sont basés sur PHPUnit et couvrent les fonctions utilitaires du module.
- Pour exécuter les tests dans l’environnement Docker Flashlight:
  1. Démarrer l’environnement: make up
  2. Lancer les tests: make test
- Vous pouvez aussi les exécuter localement depuis src/ si vous avez PHP/Composer:
  1. cd src && composer install
  2. ./vendor/bin/phpunit -c phpunit.xml.dist


## Comment est généré create_product.bundle.js et pourquoi il peut ne pas être à jour ?

Dans votre instance, vous avez pointé le fichier:

- prestashop/adminXXXX/themes/new-theme/public/create_product.bundle.js

Ce fichier est un bundle JavaScript du Back Office, généré par le build front BO (Webpack Encore) à partir des sources TypeScript/JavaScript situées dans:

- prestashop/adminXXXX/themes/new-theme/js/** (TS/JS sources)
- prestashop/adminXXXX/themes/new-theme/ (configuration Webpack, package.json, etc.)

En pratique, le bundle est produit par les scripts NPM/Yarn définis dans le thème BO et non par le module. Si vous voyez qu’il « n’est pas à jour », cela signifie généralement que les sources ont changé (ts/js) mais que le build n’a pas été relancé, ou que le cache navigateur/BO sert encore une ancienne version.

### Re-générer les bundles BO

Prérequis: Node.js + Yarn (ou PNPM) dans votre environnement (ou dans votre conteneur Docker si vous en utilisez un).

1. Se placer dans le répertoire du thème BO de votre instance:
   - cd prestashop/adminXXXX/themes/new-theme
2. Installer les dépendances front:
   - yarn install
     (ou: npm ci)
3. Lancer la compilation des assets:
   - yarn build
     (ou: npm run build)

Les bundles seront générés/rafraîchis dans prestashop/adminXXXX/themes/new-theme/public/*.bundle.js (dont create_product.bundle.js).

Astuce: si vous utilisez une version dev avec « watch »:
- yarn watch

### Fichier des routes JS (fos_js_routes.json)

Le BO génère les URLs dynamiquement via FOSJsRouting. Le fichier chargé est:

- prestashop/adminXXXX/themes/new-theme/js/fos_js_routes.json

Il doit contenir toutes les routes « exposées » (options.expose: true) dont celles de ce module. Vous pouvez le régénérer via:

```
php bin/console fos:js-routing:dump --format=json \
  --target=adminXXXX/themes/new-theme/js/fos_js_routes.json
```

Remplacez adminXXXX par le dossier admin réel de votre instance. Notre module tente déjà d’automatiser ce dump lors de l’installation/activation, mais l’exécution manuelle peut s’avérer utile en développement.

### Nettoyage des caches et vérifications

Après un build/dump:
- Back Office > Paramètres avancés > Performances > Vider le cache
- Hard refresh du navigateur (Ctrl/Cmd + Shift + R)
- Vérifier dans l’onglet Réseau que le navigateur charge bien le nouveau create_product.bundle.js et le fos_js_routes.json mis à jour.

## Ciblage de ce module (rappel)

Ce module ajoute des actions groupées AJAX sur la grille Produits et expose ses routes côté BO. Il ne modifie pas les sources front core (themes/new-theme/js), mais s’appuie sur:
- des noms de routes exposées à FOSJsRouting,
- le composant JS du BO (ajax-bulk-action-extension) qui lit les attributs data-* des boutons.

Si vous constatez un comportement différent d’une instance à l’autre, pensez à:
- régénérer fos_js_routes.json,
- reconstruire les assets du BO,
- vider le cache BO et celui du navigateur.
