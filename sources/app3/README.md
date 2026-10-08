# app3 — site public kayak-polo.info

Nuxt 4 en rendu serveur (SSR), servi sur `beta.*` jusqu'à la bascule de `www`.
Specs : [DOC/specs/public/](../../DOC/specs/public/README.md) · principes : [CLEAN_CODE.md](../../DOC/developer/guides/CLEAN_CODE.md).

## Démarrer

```bash
make app3_npm_ci          # dépendances (conteneur temporaire ; 1er démarrage)
make dev                  # tout l'environnement ; app3 sur https://beta.kpi.localhost
make app3_logs
```

Hors Docker : `npm ci && npm run dev` (port 3003).

## Organisation

| Dossier | Contenu | Règle |
|---|---|---|
| `app/utils/` | fonctions pures (`navigation.ts`, `events.ts`) | testées unitairement, sans Nuxt |
| `app/composables/` | état et données (`useMainMenu`, `useSiteSeo`) | pas de rendu |
| `app/components/` | `site/` (template), `nav/` (menus), `home/` | affichage seul, aucun appel réseau, aucun texte en dur |
| `app/pages/`, `app/layouts/` | assemblage | |
| `server/` | `/healthz`, `/robots.txt`, en-tête `X-Robots-Tag` | |
| `shared/` | code commun app/serveur (`locales.ts`, `utils/robots.ts`) | |
| `i18n/locales/` | `fr.json`, `en.json` | mêmes clés (vérifié par un test) |

Le thème, les polices et le client api2 viennent de [`../kpi-layer`](../kpi-layer/README.md).

## Livrer une page

1. Spec dans `DOC/specs/public/` (critères `XXX-nn`).
2. Tests d'abord, nommés d'après les critères.
3. Implémentation, puis `ready: true` sur l'entrée de `MAIN_MENU` (`app/utils/navigation.ts`).

## Tests et qualité

```bash
npm test                         # tous les projets Vitest
npx vitest run --project unit    # fonctions pures (+ kpi-layer), < 1 s
npx vitest run --project nuxt    # composants, environnement Nuxt
npx vitest run --project e2e     # construit et sert l'app : routes, en-têtes, SSR
npm run lint && npm run typecheck
```

Les tests de composants échouent si un composant n'est pas résolu (nom d'auto-import erroné).

## Configuration (runtime, `NUXT_*`)

Un même build sert la préprod et la prod ; les valeurs sont fournies par les fichiers compose
([SITE_PLATFORM.md § 3](../../DOC/specs/public/SITE_PLATFORM.md)).

| Variable | Rôle |
|---|---|
| `NUXT_PUBLIC_I18N_BASE_URL` | URL canonique du site (canonical, hreflang, robots.txt) |
| `NUXT_PUBLIC_BETA` | `true` : bandeau beta + non-indexation |
| `NUXT_PUBLIC_API2_BASE_URL` / `NUXT_API2_INTERNAL_URL` | api2 côté navigateur / côté serveur |
| `NUXT_PUBLIC_LEGACY_BASE_URL` | site actuel (liens de repli, images `/img/...`) |
| `NUXT_PUBLIC_APP2_BASE_URL` | application de suivi d'événement |

## Déploiement

`make app3_generate_preprod` / `make app3_generate_production` construisent `.output/` dans un conteneur
temporaire puis redémarrent le conteneur `app3`. Procédure et prérequis :
[SITE_PLATFORM.md § 6](../../DOC/specs/public/SITE_PLATFORM.md).
