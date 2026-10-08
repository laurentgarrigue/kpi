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
| `app/utils/` | fonctions pures (`navigation.ts`, `events.ts`, `competitions.ts`, `page-links.ts`) | testées unitairement, sans Nuxt |
| `app/composables/` | état et données (`useMainMenu`, `useSiteSeo`, `useApiResource`, `useAutoRefresh`) | pas de rendu |
| `app/components/` | `site/` (template), `nav/` (menus), `home/`, `results/` (résultats) | affichage, aucun texte en dur ; appels api2 seulement via `useApiResource` |
| `app/pages/`, `app/layouts/` | assemblage | |
| `server/` | `/healthz`, `/robots.txt`, en-tête `X-Robots-Tag` | |
| `shared/` | code commun app/serveur (`locales.ts`, `utils/robots.ts`) | |
| `i18n/locales/` | `fr.json`, `en.json` | mêmes clés (vérifié par un test) |

Le thème, les polices, le client api2 et la logique des résultats (`#kpi-layer/utils/results/*`, types api2 et
fonctions pures) viennent de [`../kpi-layer`](../kpi-layer/README.md).

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

### Données de test api2

Les pages de résultats sont testées sur de **vraies réponses d'api2**, capturées sur le jeu de fixtures
`SQL/fixtures/` et rangées dans `tests/fixtures/api2/` (liste des requêtes : `paths.mjs`). Après un changement
d'api2 ou des fixtures, les régénérer :

```bash
make api2_test_fixtures          # charge SQL/fixtures dans la base kpi_fixtures_test
# api2 sur cette base, port 8099 du conteneur (DATABASE_URL de la base de test, cf. SQL/fixtures/README.md) :
docker exec -d -e APP_ENV=test -e DATABASE_URL='mysql://…/kpi_fixtures?…' kpi_api2 \
  php -d variables_order=EGPCS -S 0.0.0.0:8099 -t /app/public
docker exec kpi_node_app3 node scripts/capture-api2-fixtures.mjs http://kpi_api2:8099
```

Relire le diff des JSON : il montre ce que le changement d'api2 modifie pour le site.

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
