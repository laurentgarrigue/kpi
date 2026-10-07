# Socle technique du site public (app3 + kpi-layer)

**Phase** : 1 — **Statut** : 📝 Proposée — **Routes techniques** : `/healthz`, `/robots.txt`

## 1. Objectif

Fournir le socle sur lequel toutes les pages publiques seront construites : une application Nuxt 4 rendue
côté serveur (SSR), déployable **en continu** sur des domaines `beta.*` sans aucun impact sur le legacy,
app2, app4 ou api2, jusqu'à la bascule de `www`.

## 2. Architecture

| Élément | Emplacement | Rôle |
|---|---|---|
| **app3** | `sources/app3/` | Site public, Nuxt 4 en SSR (serveur Nitro Node). |
| **kpi-layer** | `sources/kpi-layer/` | Nuxt Layer partagé : jetons de la charte (couleurs, typographie), polices, client api2 (`useApi2`), utilitaires génériques. **Consommé par app3 uniquement** jusqu'à la phase 6 (adoption par app2/app4). |
| **api2** | `sources/api2/` | Source unique des données (endpoints publics GET). |

Règles de découpage (cf. [CLEAN_CODE.md](../../developer/guides/CLEAN_CODE.md)) :
- `utils/` : fonctions pures, testées unitairement ;
- `composables/` : état et accès aux données ;
- `components/` : affichage, sans appel réseau ;
- `pages/` : assemblage ; `server/` : routes techniques Nitro.
- Ce qui est propre au site public reste dans app3 ; ce qui est réutilisable par app2/app4 (charte, client
  api2, composants de résultats) va dans le layer.

## 3. Configuration (runtime)

Toute la configuration passe par `runtimeConfig`, surchargée à l'exécution par les variables `NUXT_*` du
conteneur. **Un même build sert la préprod et la prod**.

| Variable | Côté | Rôle | Dev | Préprod | Prod |
|---|---|---|---|---|---|
| `NUXT_PUBLIC_SITE_URL` | public | URL canonique du site | `https://beta.kpi.localhost` | `https://beta.preprod.kayak-polo.info` | `https://beta.kayak-polo.info` |
| `NUXT_PUBLIC_BETA` | public | `true` tant que le site n'est pas officiel : bandeau beta + non-indexation | `true` | `true` | `true` (→ `false` à la bascule) |
| `NUXT_PUBLIC_API2_BASE_URL` | public | api2 vue du navigateur | `https://kpi.localhost/api2` | `https://preprod.kayak-polo.info/api2` | `https://kayak-polo.info/api2` |
| `NUXT_API2_INTERNAL_URL` | serveur | api2 vue du serveur Nitro (réseau Docker, sans Traefik ni préfixe `/api2`) | `http://kpi_api2` | `http://kpi_preprod_api2` | `http://kpi_api2` |
| `NUXT_PUBLIC_LEGACY_BASE_URL` | public | Site actuel : liens de repli des menus, images `/img/...`, PDF | `https://kpi.localhost` | `https://preprod.kayak-polo.info` | `https://www.kayak-polo.info` |
| `NUXT_PUBLIC_APP2_BASE_URL` | public | Application de suivi d'événement | `https://app.kpi.localhost` | `https://app.preprod.kayak-polo.info` | `https://app.kayak-polo.info` |

Les valeurs sont **dérivées de `KPI_DOMAIN_NAME` et `APPLICATION_NAME`** dans les fichiers compose : aucune
nouvelle variable n'est ajoutée à `docker/.env`.

## 4. Comportement

### 4.1 Rendu et cache
- SSR pour toutes les pages. Les règles de cache (`routeRules`, SWR) sont fixées **par la spec de chaque
  page** ; le socle n'en impose aucune.
- Les appels à api2 passent par `useApi2` (layer) : URL interne côté serveur, URL publique côté navigateur.
- Une erreur d'api2 ne fait **jamais** tomber la page entière : le bloc concerné affiche un état
  « données indisponibles » et le reste de la page est rendu.

### 4.2 Non-indexation tant que le site est en beta
Quand `NUXT_PUBLIC_BETA` vaut `true` :
- `/robots.txt` renvoie `User-agent: *` / `Disallow: /` ;
- chaque réponse HTML porte l'en-tête `X-Robots-Tag: noindex, nofollow` ;
- la balise `<meta name="robots" content="noindex, nofollow">` est présente.

Quand il vaut `false` : `robots.txt` autorise l'exploration et référence `/sitemap.xml` (sitemap livré en
phase 5), aucun en-tête ni balise `noindex`.

### 4.3 Santé
`GET /healthz` renvoie `200` et `{"status":"ok"}` sans appeler api2 (vérifie que le serveur Nitro répond).
Utilisé par le healthcheck Docker et, après la bascule, par les smoke tests du déploiement.

## 5. Environnements et domaines

| Env | Domaine | Service Docker | Construction |
|---|---|---|---|
| Dev | `beta.kpi.localhost` (certificat mkcert `*.kpi.localhost` existant) | `node_app3` : `nuxt dev`, rechargement à chaud | — |
| Préprod | `beta.preprod.kayak-polo.info` | `app3` : `node .output/server/index.mjs` | `make app3_generate_preprod` |
| Prod | `beta.kayak-polo.info` | `app3` : idem | `make app3_generate_production` |

- Routeur Traefik **dédié** : `Host(beta.${KPI_DOMAIN_NAME})`, certificat via le `certresolver` existant.
  **Aucun routeur existant n'est modifié.**
- `make app3_generate_<env>` construit `.output/` dans un conteneur Node temporaire (montage de `sources/`
  pour inclure `kpi-layer`), puis redémarre le conteneur `app3`. Si `.output/` est absent, le conteneur
  reste démarré et l'indique dans ses logs, sans boucle de redémarrage.
- Logs : `make app3_logs`.

## 6. Déploiement continu

Le code d'app3 suit le flux normal : PR → `main` → préprod automatique ; tag → prod manuelle
([GIT_WORKFLOW.md](../../developer/guides/GIT_WORKFLOW.md)).

**Prérequis, à faire une fois** (hors dépôt) :
1. **DNS** : enregistrements `beta.preprod.kayak-polo.info` et `beta.kayak-polo.info` vers le VPS.
2. **`deploy-wrapper.sh`** (dépôt privé `vps-manager`) : quand le diff touche `sources/app3/` **ou**
   `sources/kpi-layer/`, appeler `make app3_generate_${ENV}` (même mécanique que app2/app4).
3. **Premier démarrage** : `make docker_<env>_up` (crée le conteneur `app3`), puis
   `make app3_generate_<env>`.

**Smoke tests** : l'URL beta n'est **pas** ajoutée aux smoke tests bloquants avant la bascule (une panne du
beta ne doit pas provoquer le rollback du reste). Elle le sera en phase 5 (`/healthz`).

## 7. Qualité

- Vitest (unitaires et composants via `@nuxt/test-utils`), ESLint (`@nuxt/eslint`), `nuxi typecheck`.
- CI : jobs lint, tests et build pour `app3` dès qu'un fichier de `sources/app3/` ou `sources/kpi-layer/`
  change.

## 8. Critères d'acceptation

- **PLT-01** — `GET /healthz` renvoie `200` avec `{"status":"ok"}`.
- **PLT-02** — En beta, `GET /robots.txt` contient `Disallow: /` ; hors beta, il ne l'interdit pas et cite le
  sitemap.
- **PLT-03** — En beta, les pages HTML portent l'en-tête `X-Robots-Tag: noindex, nofollow` et la balise
  `meta robots` correspondante ; hors beta, ni l'un ni l'autre.
- **PLT-04** — `useApi2` utilise `NUXT_API2_INTERNAL_URL` côté serveur et `NUXT_PUBLIC_API2_BASE_URL` côté
  navigateur.
- **PLT-05** — Les fichiers compose déclarent pour app3 un routeur limité à `Host(beta.${KPI_DOMAIN_NAME})`
  et ne modifient aucun autre routeur (vérifié par `docker compose config` en revue).
- **PLT-06** — `make app3_generate_preprod` / `make app3_generate_production` produisent
  `sources/app3/.output/server/index.mjs` et redémarrent le conteneur `app3`.

## 9. Hors périmètre / questions ouvertes

- Mesure d'audience (Matomo) : ajoutée à la bascule, en mode sans cookie (cf. stratégie § 11).
- Sitemap, redirections 301 : phase 5.
- Invalidation du cache Nitro à la publication d'un article : phase 4a.
