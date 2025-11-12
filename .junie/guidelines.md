Guidelines Junie

Ce document complète le fichier guidelines.md à la racine du dépôt et sert de mémo rapide pour les contributeurs et l’assistant Junie.

1. Objet
- Rappeler les bonnes pratiques locales et pointer vers les règles complètes.

2. Référence principale
- Pour les directives détaillées, consultez le fichier racine: ../guidelines.md
- Structure de module PrestaShop: suivez la documentation officielle « Module file structure » (PS 9)
  https://devdocs.prestashop-project.org/9/modules/creation/module-file-structure/

3. Démarrage rapide
- Cloner le dépôt et suivre la section « Mise en place rapide » de ../guidelines.md
- Le module vit dans src/ et s’intègre à prestashop/modules/prestashop_bulk_action

4. Conventions
- Code: PSR‑12 (voir ../guidelines.md)
- Langue des messages/PR: français de préférence
- Branches: main (stable), feature/*, fix/*, hotfix/*
- Commits: messages concis; référencer l’issue (ex: refs #123 ou close #123)
 - Structure du module: respecter la structure officielle PrestaShop (fichiers racine du module, répertoires config/, controllers/, translations/, upgrade/, vendor/, etc.) telle que décrite ici:
   https://devdocs.prestashop-project.org/9/modules/creation/module-file-structure/

5. Sécurité & secrets
- Ne pas committer de clés, tokens ou mots de passe
- Valider/échapper toute donnée utilisateur

6. Checklist PR (rappel)
- Pourquoi du changement, étapes de test, impacts éventuels
- Tests sur au moins une version de PrestaShop supportée
- Installation/désinstallation et hooks vérifiés
 - Structure du module conforme à la doc officielle PrestaShop (voir « Module file structure »)

Dernière mise à jour: 2025‑11‑11
