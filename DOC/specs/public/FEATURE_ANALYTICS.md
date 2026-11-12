# Mesure d'audience (Matomo)

**Phase** : 5 (bascule), préparée pendant la beta — **Statut** : 📝 Brouillon — **Applications** : app3 (site
public), app2 (résultats), app4 (administration)

## 1. Objectif

Mesurer l'usage du site public (app3), de l'application de résultats (app2) et de l'administration (app4) dans
Matomo (`https://matomo.kayak-polo.info`), **sans cookie** et sans appel à un tiers, de façon à :

- connaître les pages réellement consultées et le parcours des visiteurs ;
- repérer les liens cassés après la bascule (404, en particulier les anciennes URL non redirigées) ;
- comparer, au niveau global, l'audience avant et après la bascule de `www`.

## 2. Constat (12/11/2026)

| Application | Site Matomo (prod) | État |
|---|---|---|
| Legacy public (`kp*.php`) | 1 | Smarty (`MATOMO_SITE_ID_PUBLIC`), avec cookie |
| Legacy admin | 2 | Smarty (`MATOMO_SITE_ID_ADMIN`), avec cookie |
| app2 `app.kayak-polo.info` | 6 | Plugin `app2/plugins/matomo.client.ts`, avec cookie |
| app4 `/admin2` | 7 | Plugin `app4/plugins/matomo.client.ts` (copie de celui d'app2), avec cookie |
| app3 `beta.kayak-polo.info` | — | **Aucun suivi** |

Défauts relevés en production sur le plugin app2/app4 :

1. **app4 compte deux pages vues par visite de page** quand une redirection a lieu (ex. `/admin2/` → `/admin2/login`),
   et **toutes les navigations internes sont enregistrées sans le préfixe `/admin2`** :
   `setCustomUrl(to.fullPath)` reçoit `/login`, que Matomo résout en `https://www.kayak-polo.info/login`.
   Les rapports de pages d'app4 sont donc faux et se mélangent avec des URL du site public.
2. **Le titre envoyé est celui de la page précédente**, ou vide : `document.title` est lu dans
   `router.afterEach`, avant que la nouvelle page ait mis son titre à jour (`action_name=` vide sur app2).
3. **Cookies déposés** (`cookie=1`) sans bandeau de consentement, alors que la stratégie (§ 11) prévoit un mode
   sans cookie.
4. Le composable `useMatomo()` n'est utilisé nulle part ; les `.env.dist` indiquent les sites 1 et 2 alors que la
   production utilise 6 et 7.

> Sur app2, les deux appels identiques visibles dans l'onglet Réseau (même `r`, même `pv_id`) sont **une seule
> requête** : elle apparaît deux fois parce qu'elle traverse le service worker de la PWA (ligne marquée ⚙ dans
> les outils de développement). Matomo n'enregistre qu'une page vue.

## 3. Architecture

Un seul traceur, dans le layer partagé :

| Élément | Emplacement | Rôle |
|---|---|---|
| `utils/matomo.ts` | `sources/kpi-layer/app/` | Fonctions pures : configuration → commandes `_paq` (initialisation, page vue, 404, recherche). Testées unitairement. |
| `plugins/matomo.client.ts` | `sources/kpi-layer/app/` | Charge `matomo.js`, envoie une page vue par page affichée. Inactif si l'URL ou le site n'est pas configuré. |
| `composables/useMatomo.ts` | `sources/kpi-layer/app/` | `trackEvent`, `trackSiteSearch`, `trackNotFound` pour les pages. Sans effet si le suivi est inactif ou côté serveur. |

app3 le reçoit par le layer. **app2 et app4 sont corrigés dès maintenant** en remplaçant leur plugin par une copie
du code du layer (mêmes fonctions, mêmes tests) ; ils passeront au layer en phase 6 avec le reste.

## 4. Configuration

Toute la configuration passe par `runtimeConfig.public.matomo` (`url`, `siteId`). **Le suivi est actif si et
seulement si les deux sont renseignés** : pas de troisième variable « enabled ».

| Application | Variables | Dev | Préprod | Prod |
|---|---|---|---|---|
| app3 (exécution, conteneur) | `NUXT_PUBLIC_MATOMO_URL`, `NUXT_PUBLIC_MATOMO_SITE_ID` | vides (inactif) | site préprod (3) | **nouveau site** (voir § 8) |
| app2 (build, `.env.*`) | `MATOMO_URL`, `MATOMO_SITE_ID` | vides | 3 | 6 |
| app4 (build, `.env`) | `MATOMO_URL`, `MATOMO_SITE_ID` | vides | 3 | 7 |

Pour app3, les deux valeurs viennent de `docker/.env` (`MATOMO_URL`, `APP3_MATOMO_SITE_ID`, vides par défaut dans
`docker/.env.dist`) et sont passées au conteneur par les fichiers compose préprod et prod. C'est une exception à la
règle « aucune nouvelle variable » de SITE_PLATFORM.md § 3 : le numéro de site ne se déduit pas du domaine.
`MATOMO_ENABLED` disparaît d'app2 et app4.

## 5. Comportement

### 5.1 Initialisation (toutes les applications)
- Commandes envoyées avant toute page vue : `disableCookies`, `setTrackerUrl`, `setSiteId`, `enableLinkTracking`,
  `enableHeartBeatTimer` (temps passé sur la dernière page), extensions de téléchargement + `ics`.
- `matomo.js` est chargé une seule fois, en asynchrone, depuis `{url}/matomo.js` ; s'il est bloqué (bloqueur de
  publicité), le site fonctionne normalement.
- Rien n'est exécuté côté serveur (SSR) : aucune requête vers Matomo depuis Nitro.

### 5.2 Pages vues
- **Une seule page vue par page affichée**, y compris au premier chargement et après une redirection.
- Déclenchement à la fin du rendu de la page (`page:finish`), après la mise à jour de l'en-tête, pour que le
  **titre soit celui de la nouvelle page**.
- URL envoyée : `window.location.href` **complète** (avec le préfixe de base, `/admin2` pour app4, et la langue
  `/en` pour app3) ; référent : l'URL de la page précédente pour une navigation interne.
- Les changements limités à la query ou au hash d'une même page (onglets, filtres) **ne** génèrent **pas** de
  page vue. Exception : la page de recherche d'app3 (§ 5.4).

### 5.3 Pages introuvables (app3)
- La page d'erreur 404 envoie une page vue au titre conventionnel Matomo
  `404/URL = {chemin encodé}/From = {référent encodé}`, qui alimente le rapport « Pages introuvables ».
- Les autres erreurs (500…) envoient un événement `Erreur` / `{code}` / `{chemin}`.

### 5.4 Recherche (app3)
- La page `/search?q=` envoie `trackSiteSearch(q, false, {nombre total de résultats})` à la place de la page vue :
  rapport « Recherche interne » (mots-clés, recherches sans résultat).

### 5.5 Événements (app3)
Limités à ce qui n'est pas déjà couvert par les liens et téléchargements :

| Catégorie | Action | Nom |
|---|---|---|
| `Calendrier` | `Abonnement ICS` | URL du flux (copie du lien d'abonnement) |
| `Partage` | réseau (`facebook`, `copier`…) | chemin de l'article |
| `Langue` | `fr` / `en` | page de départ |

## 6. Vie privée

- Aucun cookie (`disableCookies`), aucun identifiant utilisateur (`setUserId` non utilisé, y compris dans app4).
- Côté serveur Matomo (configuration, hors code) : anonymisation de l'IP sur 2 octets, suppression des journaux
  bruts au-delà de 13 mois, respect de « Do Not Track » désactivé (sans objet sans cookie), exclusion des IP de
  l'équipe si souhaité. Ces réglages conditionnent l'exemption de consentement de la CNIL.
- La page « Mentions légales / Données personnelles » d'app3 mentionne la mesure d'audience et propose le
  désabonnement (iframe d'opt-out Matomo ou lien équivalent).

## 7. Critères d'acceptation

| ID | Critère |
|---|---|
| ANA-01 | Sans URL ou sans numéro de site, aucun script Matomo n'est chargé et aucune commande n'est envoyée. |
| ANA-02 | Avec la configuration, `matomo.js` est chargé une seule fois et `disableCookies`, `setTrackerUrl`, `setSiteId` précèdent la première page vue. |
| ANA-03 | Le premier chargement d'une page produit exactement une page vue. |
| ANA-04 | Une navigation suivie d'une redirection (ex. route protégée → connexion) produit exactement une page vue, celle de la page finale. |
| ANA-05 | L'URL envoyée contient le préfixe de base de l'application (`/admin2/login` pour app4). |
| ANA-06 | Le titre envoyé est celui de la page affichée, pas celui de la précédente. |
| ANA-07 | Après une navigation interne, le référent est l'URL de la page précédente. |
| ANA-08 | Un changement de query ou de hash sur la même page ne produit pas de page vue. |
| ANA-09 | Le rendu serveur (SSR) d'app3 ne contient aucun appel à Matomo et fonctionne si `window` est absent. |
| ANA-10 | La page 404 d'app3 envoie le titre `404/URL = …/From = …`. |
| ANA-11 | La page de recherche envoie une recherche interne avec le mot-clé et le nombre de résultats, sans page vue en double. |
| ANA-12 | Les fichiers `.ics` sont comptés comme téléchargements. |
| ANA-13 | Les événements du § 5.5 sont envoyés avec les catégories, actions et noms indiqués. |
| ANA-14 | app2 et app4 utilisent le même code (copie du layer) et satisfont ANA-01 à ANA-08 (tests dans app4 ; app2 n'a pas de lanceur de tests, sa copie est vérifiée identique à celle du layer). |

## 8. Questions ouvertes

1. **Site Matomo d'app3 : réutiliser le site 1 (legacy public) ou en créer un nouveau ?**
   Recommandation : **nouveau site**, à alimenter dès maintenant depuis `beta.kayak-polo.info` :
   - les URL changent complètement (`/competitions/2026/N1H` au lieu de `kpresultats.php?…`) : les rapports de
     pages ne sont de toute façon pas comparables ligne à ligne ;
   - le passage sans cookie change le comptage des visiteurs uniques : mêler les deux modes dans un même site
     fausserait la courbe au lieu de la rendre continue ;
   - pendant la beta, les deux sites coexistent : un même site compterait deux fois les visiteurs qui passent de
     l'un à l'autre ;
   - la comparaison se fait sur les indicateurs globaux (visites, actions, durée, pages par visite) dans le tableau
     de bord « Tous les sites », avec une **annotation** à la date de la bascule sur les deux sites. Le site 1 garde
     son historique et continue de mesurer les pages legacy jusqu'à leur retrait (phase 6).
2. WordPress (« Connect Matomo ») envoie-t-il aussi vers le site 1 ? À vérifier : si oui, l'historique du site 1
   couvre déjà « www » dans son ensemble, ce qui renforce l'intérêt d'un site séparé pour app3.
3. Événements du § 5.5 : la liste convient-elle, ou faut-il en ajouter (ex. clic sur « Suivre en direct » vers app2) ?

## 9. Hors périmètre

- Tableau de bord Matomo intégré à app4.
- Mesure côté serveur (journaux Traefik) et suivi de l'API api2.
- Legacy Smarty : inchangé jusqu'à son retrait (phase 6).
