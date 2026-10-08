# kpi-layer

Nuxt Layer partagé des applications KPI : charte FFCK (univers Compétition), polices et client api2.
Specs : [SITE_PLATFORM.md](../../DOC/specs/public/SITE_PLATFORM.md), [SITE_LAYOUT.md](../../DOC/specs/public/SITE_LAYOUT.md).

> **Consommé par app3 uniquement** jusqu'à la phase 6 de la refonte : app2 et app4 ne doivent pas être
> impactés avant la bascule ([stratégie § 4.3](../../DOC/developer/in-progress/plans/PUBLIC_SITE_REDESIGN_STRATEGY.md)).

## Contenu

| Élément | Fichier | Rôle |
|---|---|---|
| Jetons de la charte | `app/assets/css/kpi-theme.css` | `@font-face` (Raleway, Agency FB), `@theme static` : couleurs nommées (`navy`, `ink`, `line`, `sky`) et palettes `kpi-blue/red/green/gold` 50→950 |
| Couleurs Nuxt UI | `app/app.config.ts` | `primary` → `kpi-blue`, `error` → `kpi-red`, `success` → `kpi-green`, `warning` → `kpi-gold` |
| Client api2 | `app/composables/useApi2.ts`, `app/utils/api2.ts` | `$fetch` lié à l'URL interne (serveur) ou publique (navigateur) |
| Résultats | `app/utils/results/` (`types`, `games`, `charts`, `ranking`, `events`) | types des réponses publiques d'api2 et règles d'affichage en fonctions pures (tri, filtres, « prochains matchs », grille des terrains, poules, vainqueur, marques de classement, événement principal) ; import explicite `#kpi-layer/utils/results/…` |
| Configuration | `nuxt.config.ts` | `runtimeConfig.api2InternalUrl` (`NUXT_API2_INTERNAL_URL`), `public.api2BaseUrl` (`NUXT_PUBLIC_API2_BASE_URL`) |
| Polices | `public/fonts/` | Raleway variable (OFL, `raleway/OFL.txt`), Agency FB (licence FFCK) |
| Logos | `public/img/brand/` | Logo FFCK et logo CNA Kayak-Polo, fichiers existants **non modifiés** (charte) |

## Utilisation

```ts
// nuxt.config.ts de l'application
export default defineNuxtConfig({ extends: ['../kpi-layer'], modules: ['@nuxt/ui'] })
```

```css
/* feuille principale de l'application : ordre obligatoire */
@import "tailwindcss";
@import "@nuxt/ui";
@import "../../../../kpi-layer/app/assets/css/kpi-theme.css";
```

L'application doit désactiver `@nuxt/fonts` (`ui: { fonts: false }`) : les polices sont auto-hébergées ici.

## Palette

Les palettes sont **générées** à partir des ancres de la charte : ne pas les modifier à la main.

```bash
python3 scripts/generate-palette.py   # puis remplacer la section « Generated palette » de kpi-theme.css
```

## Tests

Les tests du layer (`tests/*.spec.ts`) sont exécutés par le projet `unit` de Vitest d'app3 (`npm test` dans `sources/app3`).

## Duplications temporaires (résorbées en phase 6)

| Élément | Copie dans | Pourquoi |
|---|---|---|
| `agencyfb.ttf` | `sources/app4/public/fonts/` | app4 n'utilise pas encore le layer |
| Palette FFCK (valeurs proches) | `sources/app4/assets/css/admin.css` | idem |
| Logique des tableaux (`utils/results/charts.ts`, `games.ts`) | `sources/app2/components/Chart*.vue` | réécrite en fonctions pures pour app3 ; app2 l'adoptera en phase 6 |
