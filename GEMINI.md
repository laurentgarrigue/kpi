# Contexte projet

## 📚 Documentation étendue

Voir le dossier **[WORKFLOW_AI/](WORKFLOW_AI/)** pour la documentation technique détaillée :
- Guides de migration (PHP 8, FPDF → mPDF)
- Corrections et optimisations
- Audits de code
- Configuration Docker et infrastructure

Index complet : [WORKFLOW_AI/README.md](WORKFLOW_AI/README.md)

## Architecture

- Backend en **PHP 7.4/8.x**, architecture MVC maison.
- Templates avec **Smarty** (`.tpl`) dans `sources/smarty/templates`
- Base de données MySQL, requêtes préparées uniquement.
- Suivre la convention **PSR-12** pour le code PHP.
- Ne jamais exposer de credentials dans les fichiers versionnés.
- Api maison dans **sources/api**
- Les 3 applications **Vue 3** legacy (`sources/app_dev`, `sources/app_live_dev`, `sources/app_wsm_dev`) ont été supprimées le 28/11/2026 (tag git `archive/vue-cli-sources`) ; leurs builds (`sources/app`, `sources/app_live`, `sources/app_wsm`, non versionnés) restent sur les serveurs.
- un wordpress sert pour la page d'accueil du site, dossier `wordpress/`
- Les fichiers générés (`vendor/`, `node_modules/`, `dist/`) ne doivent pas être modifiés.
- Les fichiers `sources/commun/MyParams.php` ne doivent jamais être modifiés ni commités.
- Toujours utiliser des requêtes préparées et pdo
- Un dossier `sources/app2` dans lequel je tente de migrer le fontend app_dev vers du nuxt