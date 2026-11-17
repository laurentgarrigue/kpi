# Matomo — checklist de mise en service

> **✅ Close le 14/11/2026** : en service (PR #395). Les cases n'ont pas été cochées au fil de l'eau. Contrôle du 14/11 :
> la page d'accueil de `beta.kayak-polo.info` embarque le traceur `matomo.kayak-polo.info`, site **8**. Le § 7
> (« À la bascule de `www` ») est repris dans la phase 5 de
> [PUBLIC_SITE_ROLLOUT_CHECKLIST.md](../../in-progress/PUBLIC_SITE_ROLLOUT_CHECKLIST.md).

**Créée le** : 12 novembre 2026 — **Spec** : [FEATURE_ANALYTICS.md](../../../specs/public/FEATURE_ANALYTICS.md) —
**Doc d'exploitation** : [MATOMO_CONFIG.md](../../infrastructure/MATOMO_CONFIG.md)

> **Convention** (comme le [runbook](../../infrastructure/DEPLOYMENT_RUNBOOK.md)) : 📊 interface Matomo
> (`https://matomo.kayak-polo.info`, compte super-utilisateur) · 🖥 VPS (SSH) · 🌐 interface GitHub ·
> 📝 WordPress (admin) · ✍️ app4 › Éditorial.

**Ce que livre la branche `claude/matomo_tracking_config`** : un traceur sans cookie commun à app2, app3 et app4
(correction du double comptage et des URL sans `/admin2` d'app4, titres vides d'app2), le suivi d'app3 (pages, 404,
recherche interne, « Suivre en direct », agenda, partage, langue), `disableCookies` dans les gabarits legacy.
Un correctif ultérieur (`matomoQueue`, ANA-16) envoie les commandes sur le `window._paq` courant : sans lui, seule la
première page vue d'un chargement partait, les navigations suivantes étaient perdues. Les builds antérieurs à ce
correctif sont à régénérer.
`MATOMO_ENABLED` disparaît : **le suivi est actif dès que l'URL et le numéro de site sont renseignés**.

**Plan des sites Matomo**

| Site | Nom | Reçoit |
|---|---|---|
| 1 | kayak-polo.info | Legacy public + WordPress (inchangé) |
| 2 | KPI back-office | Legacy admin (inchangé) |
| 3 | PREPROD Kayak-polo.info | **Toute la préprod** : legacy, app2, app3, app4 |
| 6 | KPI App | app2 prod |
| 7 | KPI Admin2 | app4 prod |
| **nouveau** (8 a priori) | KPI Site public | app3 prod, dès la beta puis après la bascule |

---

## 1. 📊 Matomo — avant le merge

| # | Action | Détail | ☐ |
|---|---|---|---|
| 1.1 | Créer le site app3 | Administration › Sites web › Gérer › **Ajouter un site** › Site web. Nom `KPI Site public`, URL `https://beta.kayak-polo.info`, fuseau France, devise Euro, recherche sur le site **activée** (paramètre `q`). **Noter l'ID attribué** (8 attendu) : il sert au § 3.1 | ☐ |
| 1.2 | Compléter les URL de la préprod | Site 3 › modifier › URL : ajouter `https://app.preprod.kayak-polo.info` et `https://beta.preprod.kayak-polo.info` (garder les existantes) | ☐ |
| 1.3 | Anonymiser les IP | Administration › Confidentialité › Anonymiser les données : **Anonymiser l'IP des visiteurs = Oui, 2 octets** ; « Utiliser aussi l'IP anonymisée pour l'enrichissement » = Oui | ☐ |
| 1.4 | Forcer le suivi sans cookie | Même page : cocher **« Forcer le suivi sans cookies »** si votre version de Matomo le propose (Matomo 5). Filet de sécurité : couvre aussi WordPress et tout script oublié | ☐ |
| 1.5 | Durée de conservation | Même page › Supprimer les anciennes données brutes : **au-delà de 13 mois** (les rapports agrégés restent) | ☐ |
| 1.6 | Exclure vos IP (facultatif) | Administration › Sites web › Paramètres globaux › IP exclues, si vous ne voulez pas compter vos propres visites | ☐ |
| 1.7 | Site 5 « KPI Old dev » | Inutilisé : à supprimer si vous n'en avez plus besoin (facultatif) | ☐ |

## 2. 🖥 Préprod — avant le merge, puis après le déploiement automatique

`/data/kpi_preprod`. **À faire avant le merge** : dès le déploiement, une application dont l'URL et le site sont
renseignés se met à envoyer des données, même si l'ancien fichier contient encore `MATOMO_ENABLED=false`.

| # | Action | Détail | ☐ |
|---|---|---|---|
| 2.1 | app2 | `sources/app2/.env.preprod` : `MATOMO_URL=https://matomo.kayak-polo.info`, `MATOMO_SITE_ID=3`, supprimer la ligne `MATOMO_ENABLED` | ☐ |
| 2.2 | app4 | `sources/app4/.env` : mêmes valeurs (`MATOMO_SITE_ID=3`), supprimer `MATOMO_ENABLED` | ☐ |
| 2.3 | app3 | `docker/.env` : ajouter `MATOMO_URL=https://matomo.kayak-polo.info` et `APP3_MATOMO_SITE_ID=3` | ☐ |
| 2.4 | Legacy | `sources/commun/MyParams.php` : vérifier `MATOMO_SITE_ID_PUBLIC` = `MATOMO_SITE_ID_ADMIN` = `'3'` et `MATOMO_SERVER_URL` | ☐ |
| 2.5 | 🌐 Merger la PR | Le déploiement automatique reconstruit app2, app3 et app4 (le code a changé) | ☐ |
| 2.6 | Recréer le conteneur app3 | Les variables d'environnement ne sont lues qu'à la création du conteneur (`app3_restart` ne suffit pas) : `make docker_preprod_up` (seul le conteneur app3, dont la configuration a changé, est recréé) | ☐ |
| 2.7 | Vider le cache Smarty | `rm -f sources/smarty/templates_c/*.php` (prise en compte de `disableCookies`) | ☐ |

Si vous modifiez plus tard un `.env` d'app2 ou d'app4 sans changer le code : `make app2_generate_preprod` ou
`make app4_generate_preprod` (les valeurs sont figées au build, voir l'encadré ci-dessous).

> **Les `.env` d'app2 et d'app4 sont lus au build** (`nuxt generate`), pas au chargement de la page. Éditer le fichier
> **après** le build ne change rien : régénérer. app2 lit `.env.preprod` / `.env.production` (via `dotenv-cli`),
> app4 lit `.env`. Vérifier que le build contient bien Matomo :
> `grep -rl "matomo.kayak-polo.info" sources/app2/.output/public sources/app4/.output/public | head`
> (aucun résultat = build sans Matomo).

## 3. 🖥 Production — avant le déploiement manuel

`/data/kpi`. Même principe : préparer les fichiers, puis déployer le tag.

| # | Action | Détail | ☐ |
|---|---|---|---|
| 3.1 | app3 | `docker/.env` : `MATOMO_URL=https://matomo.kayak-polo.info`, `APP3_MATOMO_SITE_ID=<ID du § 1.1>` | ☐ |
| 3.2 | app2 | `sources/app2/.env.production` : `MATOMO_URL=https://matomo.kayak-polo.info`, `MATOMO_SITE_ID=6`, supprimer `MATOMO_ENABLED` | ☐ |
| 3.3 | app4 | `sources/app4/.env` : `MATOMO_URL=https://matomo.kayak-polo.info`, `MATOMO_SITE_ID=7`, supprimer `MATOMO_ENABLED` | ☐ |
| 3.4 | Legacy | `sources/commun/MyParams.php` : vérifier `'1'` (public), `'2'` (admin) | ☐ |
| 3.5 | 🌐 Déployer | Actions › « Deploy production » › tag de la release ([runbook § 1.2](../../infrastructure/DEPLOYMENT_RUNBOOK.md)) | ☐ |
| 3.6 | Recréer le conteneur app3 | `make docker_prod_up` (seul le conteneur app3 est recréé) | ☐ |
| 3.7 | Vider le cache Smarty | `rm -f sources/smarty/templates_c/*.php` | ☐ |
| 3.8 | Contrôler les builds app2 et app4 | Si le déploiement a eu lieu avant l'édition des `.env` (3.2, 3.3) : `make app2_generate_production && make app4_generate_prod`, puis le `grep` de l'encadré du § 2 : il doit lister des fichiers. Navigateur : désinscrire le service worker d'app2 (outils de développement › Application) ou tester en navigation privée | ☐ |

## 4. 📝 WordPress (site 1)

| # | Action | Détail | ☐ |
|---|---|---|---|
| 4.1 | Désactiver les cookies | Réglages › Connect Matomo › onglet Suivi : cocher **« Désactiver les cookies »**, enregistrer | ☐ |
| 4.2 | Vider le cache | Si WP Fastest Cache est actif : supprimer le cache pour que les pages servies contiennent le nouveau code | ☐ |

## 5. ✍️ Information des visiteurs (app3)

| # | Action | Détail | ☐ |
|---|---|---|---|
| 5.1 | Mentions légales / données personnelles | Ajouter un paragraphe « Mesure d'audience » : Matomo auto-hébergé, sans cookie, IP anonymisée, données conservées 13 mois, non partagées | ☐ |
| 5.2 | Droit d'opposition | Indiquer comment s'opposer (réglage « Do Not Track » du navigateur, ou contact). Le module d'opt-out Matomo (📊 Confidentialité › Désinscription des utilisateurs) repose sur un cookie : à n'ajouter que si l'éditeur accepte un `<iframe>` | ☐ |

## 6. Vérifier (préprod puis prod)

Dans le navigateur : outils de développement › Réseau › filtre `matomo.php` (type « Tout », pas seulement Fetch/XHR),
en navigation privée **sans extension** : uBlock, Brave et d'autres bloquent `matomo.php` tout en laissant passer
`matomo.js`. Un hit qui manque : console, `Matomo.getAsyncTrackers().map(t => [t.getTrackerUrl(), t.getSiteId()])`
(URL et site attendus), puis `_paq.push(['trackPageView'])` à la main (un hit part = le traceur est bon, le défaut est
dans l'envoi automatique, par exemple un build antérieur à ANA-16).

| # | Où | Attendu | ☐ |
|---|---|---|---|
| 6.1 | app4 : ouvrir `/admin2/` | **Une** requête `matomo.php`, `url=…/admin2/login`, `idsite=7` (3 en préprod), `cookie=0` | ☐ |
| 6.2 | app4 : naviguer vers une autre page | Une requête par page, URL avec `/admin2`, `action_name` = titre de la page | ☐ |
| 6.3 | app2 : ouvrir l'accueil | `idsite=6` (3), `cookie=0`, `action_name` non vide. Deux lignes identiques (même `pv_id`) = service worker, normal | ☐ |
| 6.4 | app3 : accueil puis un menu | Une requête par page, `idsite=<nouveau>` (3), `urlref` = page précédente | ☐ |
| 6.5 | app3 : `/search?q=acigne` | Requête avec `search=acigne` et `search_count=…` | ☐ |
| 6.6 | app3 : `/nimporte-quoi` | `action_name=404/URL = …` | ☐ |
| 6.7 | app3 : « Suivre en direct » | Requête avec `e_c=Direct`, `e_a=Suivre en direct` | ☐ |
| 6.8 | Legacy et WordPress | `cookie=0` ; onglet Stockage : aucun cookie `_pk_id` / `_pk_ses` | ☐ |
| 6.9 | 📊 Visites › Journal des visites | Les visites apparaissent dans le bon site (quelques minutes de délai si l'archivage est par cron) | ☐ |

> Le nombre de visiteurs uniques des sites 1, 2, 6 et 7 baisse après le passage sans cookie : c'est attendu (un
> visiteur qui revient un autre jour n'est plus reconnu). 📊 Ajouter une **annotation** « Passage sans cookie » à la
> date du déploiement sur ces sites.

## 7. À la bascule de `www` (phase 5)

| # | Action | Détail | ☐ |
|---|---|---|---|
| 7.1 | 📊 URL du site app3 | Ajouter `https://www.kayak-polo.info` et `https://kayak-polo.info` | ☐ |
| 7.2 | 📊 Annotations | « Bascule www → nouveau site » à la date de la bascule, sur le site 1 et sur le site app3 | ☐ |
| 7.3 | 📊 Suivi des 404 | Comportement › Titres de pages › ligne `404` (ou rapport « Pages introuvables ») : les anciennes URL fréquentes sans redirection sont à ajouter aux 301 | ☐ |
| 7.4 | 📊 Tableau de bord comparatif | Tous les sites : visites, actions, durée moyenne, avant/après, sites 1 + app3 | ☐ |
| 7.5 | Phase 6 | Après retrait du legacy public et de WordPress, le site 1 ne reçoit plus rien : le garder pour l'historique | ☐ |
