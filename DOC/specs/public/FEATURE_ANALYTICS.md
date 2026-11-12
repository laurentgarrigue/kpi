# Mesure d'audience (Matomo)

**Phase** : 5 (bascule), en service dès la beta — **Statut** : ✅ Validée (12/11/2026) — 🛠 En cours —
**Applications** : app3 (site public), app2 (résultats), app4 (administration), legacy (Smarty)

Mise en production : [MATOMO_ROLLOUT_CHECKLIST.md](../../developer/in-progress/MATOMO_ROLLOUT_CHECKLIST.md).

## 1. Objectif

Mesurer l'usage du site public (app3), de l'application de résultats (app2) et de l'administration (app4) dans
Matomo (`https://matomo.kayak-polo.info`), **sans cookie** et sans appel à un tiers, de façon à :

- connaître les pages réellement consultées et le parcours des visiteurs ;
- repérer les liens cassés après la bascule (404, en particulier les anciennes URL non redirigées) ;
- comparer, au niveau global, l'audience avant et après la bascule de `www`.

## 2. Constat (12/11/2026)

| Site Matomo | Nom | Alimenté par | État avant correction |
|---|---|---|---|
| 1 | kayak-polo.info | Legacy public (`kp*.php`, Smarty) + WordPress (« Connect Matomo ») | avec cookie |
| 2 | KPI back-office | Legacy admin (Smarty) | avec cookie |
| 3 | PREPROD Kayak-polo.info | Legacy en préprod | app2/app4 coupés (`MATOMO_ENABLED=false`) |
| 4 | DEV Kayak-polo.info | Legacy en dev (optionnel) | — |
| 5 | KPI Old dev | — | inutilisé |
| 6 | KPI App | app2 `app.kayak-polo.info` | avec cookie |
| 7 | KPI Admin2 | app4 `/admin2` | avec cookie, **URL fausses** |
| — | — | app3 `beta.kayak-polo.info` | **aucun suivi** |

Défauts relevés en production sur le plugin commun à app2 et app4 :

1. **app4 compte deux pages vues** quand une redirection a lieu (`/admin2/` → `/admin2/login`), et **toutes les
   navigations internes sont enregistrées sans le préfixe `/admin2`** : `setCustomUrl(to.fullPath)` reçoit `/login`,
   que Matomo résout en `https://www.kayak-polo.info/login`.
2. **Le titre envoyé est celui de la page précédente**, ou vide (`action_name=` sur app2) : `document.title` est lu
   dans `router.afterEach`, avant que la nouvelle page ait rendu son titre.
3. **Cookies déposés** (`cookie=1`) sans bandeau de consentement.
4. `useMatomo()` n'était utilisé nulle part ; les `.env.dist` indiquaient les sites 1 et 2 au lieu de 6 et 7.

> Sur app2, les deux appels identiques de l'onglet Réseau (même `r`, même `pv_id`) sont **une seule requête**
> vue deux fois parce qu'elle traverse le service worker de la PWA. Matomo n'enregistre qu'une page vue.

## 3. Décisions (12/11/2026)

- **app3 a son propre site Matomo** (« KPI Site public »), alimenté dès la beta. Le site 1 garde son historique et
  continue de mesurer le legacy public et WordPress jusqu'à leur retrait. Raisons : URL entièrement différentes
  (rapports de pages non comparables ligne à ligne), changement du comptage des visiteurs uniques avec le passage
  sans cookie, double comptage des visiteurs passant d'un site à l'autre pendant la beta. La comparaison se fait sur
  les indicateurs globaux (tableau de bord « Tous les sites ») avec une **annotation** à la date de la bascule.
- **Sans cookie partout** : app2, app3, app4, legacy Smarty et WordPress.
- **Préprod** : toutes les applications envoient vers le site 3. **Dev** : suivi coupé (valeurs vides).

## 4. Architecture

| Élément | Emplacement | Rôle |
|---|---|---|
| `utils/matomo.ts` | `sources/kpi-layer/app/` | Logique pure (navigateur injecté) : configuration → commandes `_paq`, page vue dédupliquée, 404, recherche, événements. |
| `plugins/matomo.client.ts` | `sources/kpi-layer/app/` | Charge `matomo.js`, envoie une page vue à chaque `page:finish`, écoute les clics sur les éléments `data-analytics-*`. |
| `composables/useMatomo.ts` | `sources/kpi-layer/app/` | `trackNotFound`, `trackSiteSearch`, `trackEvent` pour les pages. Sans effet côté serveur ou si le suivi est coupé. |

app3 les reçoit par le layer. **app2 et app4 utilisent une copie identique** de `utils/matomo.ts` et
`plugins/matomo.client.ts` (vérifiée par un test) jusqu'à leur adoption du layer en phase 6.

Legacy Smarty : chaque gabarit et `utyGetMatomoScript()` envoient `disableCookies` avant la page vue. WordPress : réglage
du module « Connect Matomo » (checklist).

## 5. Configuration

Clés `runtimeConfig.public.matomoUrl` et `matomoSiteId`. **Le suivi est actif si et seulement si les deux sont
renseignés** ; `MATOMO_ENABLED` est supprimé.

| Application | Où | Variables | Dev | Préprod | Prod |
|---|---|---|---|---|---|
| app3 | `docker/.env` → conteneur (exécution) | `MATOMO_URL`, `APP3_MATOMO_SITE_ID` (→ `NUXT_PUBLIC_MATOMO_URL`, `NUXT_PUBLIC_MATOMO_SITE_ID`) | vides | 3 | nouveau site |
| app2 | `sources/app2/.env.preprod`, `.env.production` (build) | `MATOMO_URL`, `MATOMO_SITE_ID` | vides | 3 | 6 |
| app4 | `sources/app4/.env` (build) | `MATOMO_URL`, `MATOMO_SITE_ID` | vides | 3 | 7 |
| Legacy | `sources/commun/MyParams.php` | `MATOMO_SERVER_URL`, `MATOMO_SITE_ID_PUBLIC`, `MATOMO_SITE_ID_ADMIN` | 4 | 3 | 1, 2 |

`MATOMO_URL` vaut `https://matomo.kayak-polo.info`. Pour app3, c'est une exception à la règle « aucune nouvelle
variable » de SITE_PLATFORM.md § 3 : le numéro de site ne se déduit pas du domaine.

## 6. Comportement

### 6.1 Initialisation
- Avant toute page vue : `disableCookies`, `setTrackerUrl`, `setSiteId`, `addDownloadExtensions` (`ics`),
  `enableLinkTracking` (liens sortants et téléchargements), `enableHeartBeatTimer` (temps passé sur la dernière page).
- `matomo.js` est chargé une fois, en asynchrone ; s'il est bloqué, l'application fonctionne normalement.
- Rien n'est exécuté côté serveur (SSR).

### 6.2 Pages vues
- **Une page vue par page affichée**, envoyée à la fin du rendu (`page:finish`) puis après le rendu de l'en-tête :
  le titre est celui de la nouvelle page. Une redirection ne produit que la page finale.
- URL : `window.location.href` **complète** (préfixe `/admin2`, langue `/en`). Référent : la page précédente pour une
  navigation interne, `document.referrer` pour la première.
- Même origine et même chemin = même page : un changement de query ou de hash (onglet, filtre) n'est pas une page vue.

### 6.3 Erreurs (app3)
- 404 : page vue au titre `404/URL = {chemin encodé}/From = {référent encodé}` (rapport « Pages introuvables »).
- Autres erreurs : événement `Erreur` / `{code}` / `{chemin}`.

### 6.4 Recherche (app3)
- `/search?q=` envoie `trackSiteSearch(q, catégorie filtrée ou false, nombre de résultats)` **à la place** de la page
  vue (`definePageMeta({ analytics: 'manual' })`), une fois par mot-clé et par filtre.

### 6.5 Événements (app3)
Déclarés sur l'élément cliqué par `data-analytics-category`, `-action`, `-name` :

| Catégorie | Action | Nom | Élément |
|---|---|---|---|
| `Direct` | `Suivre en direct` | URL app2 visée | Boutons « Suivre en direct » (accueil, compétition, événement, groupe) |
| `Calendrier` | `Abonnement` / `Copie du lien` | URL du flux ICS | Bloc « Dans votre agenda » (le téléchargement `.ics` est compté comme téléchargement) |
| `Partage` | `facebook`, `email`… / `copie` / `natif` | URL de l'article | Bouton « Partager » |
| `Langue` | `fr` / `en` | — | Sélecteur de langue |

## 7. Vie privée

- Aucun cookie, aucun identifiant utilisateur (`setUserId` jamais utilisé, y compris dans app4).
- Côté Matomo (configuration) : IP anonymisée sur 2 octets, suppression des journaux bruts au-delà de 13 mois. Avec
  l'absence de cookie, ces réglages permettent l'exemption de consentement de la CNIL.
- La page « Données personnelles » d'app3 mentionne la mesure d'audience et propose l'opposition (iframe d'opt-out).

## 8. Critères d'acceptation

| ID | Critère |
|---|---|
| ANA-01 | Sans URL ou sans numéro de site, aucun script Matomo n'est chargé et aucune commande n'est envoyée. |
| ANA-02 | Avec la configuration, `matomo.js` est chargé une fois et `disableCookies`, `setTrackerUrl`, `setSiteId` précèdent la première page vue. |
| ANA-03 | Le premier chargement d'une page produit exactement une page vue. |
| ANA-04 | Une navigation suivie d'une redirection produit exactement une page vue, celle de la page finale. |
| ANA-05 | L'URL envoyée contient le préfixe de base de l'application (`/admin2/login` pour app4). |
| ANA-06 | Le titre envoyé est celui de la page affichée, pas celui de la précédente. |
| ANA-07 | Après une navigation interne, le référent est l'URL de la page précédente. |
| ANA-08 | Un changement de query ou de hash sur la même page ne produit pas de page vue. |
| ANA-09 | Le rendu serveur (SSR) d'app3 ne contient aucun appel à Matomo. |
| ANA-10 | La page 404 d'app3 envoie le titre `404/URL = …/From = …`. |
| ANA-11 | La page de recherche envoie une recherche interne avec le mot-clé, le filtre et le nombre de résultats, sans page vue en double. |
| ANA-12 | Les fichiers `.ics` sont comptés comme téléchargements. |
| ANA-13 | Les événements du § 6.5 sont envoyés avec les catégories, actions et noms indiqués. |
| ANA-14 | app2 et app4 utilisent une copie identique du code du layer. |
| ANA-15 | Les gabarits legacy envoient `disableCookies` avant la page vue. |

Vérification en navigateur (12/11/2026, builds locaux, Matomo simulé) : app4 `/admin2/` → `/admin2/login` = 1 page vue
avec `/admin2` ; app3 accueil → navigation → redirection, recherche, 404 et rendu serveur conformes.

## 9. Hors périmètre

- Tableau de bord Matomo intégré à app4.
- Mesure côté serveur (journaux Traefik) et suivi de l'API api2.
- Événements dans app2 et app4 (pages vues seulement).
