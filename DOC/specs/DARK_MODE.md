# Spécification — Dark mode dans app4 (admin2)

> Statut : **en cours** — socle + toggle + page `games` + composants partagés faits ; ~23 pages
> et ~26 composants restent à convertir (voir §7 Suivi).
> Cible : **app4** uniquement (Nuxt 4 + Nuxt UI v3 + Tailwind v4). Ne concerne pas app2/app3.
> Branche de travail : `darktheme`.

## 1. Contexte et objectif

app4 était **volontairement bridé en mode clair** (`colorMode.preference: 'light'` + un bloc CSS
qui neutralisait `.dark`). L'objectif est d'offrir un **dark mode complet** activable par
l'utilisateur, avec trois modes : **Clair / Sombre / Système** (le mode Système suit l'OS).

Le choix est **persisté par utilisateur** dans le localStorage (clé `nuxt-color-mode-admin4`).

### Contrainte majeure

Au démarrage : **0 occurrence de `dark:`** et **260 `bg-white` codés en dur** dans le code. La
mécanique du toggle est triviale ; l'essentiel du travail est la **conversion visuelle** page
par page. Une page livrée sans conversion afficherait du texte sombre sur fond sombre (pire que
pas de dark mode) — d'où l'approche incrémentale.

## 2. Mécanique (fait — ne pas refaire)

### 2.1 Configuration
[`nuxt.config.ts`](../../sources/app4/nuxt.config.ts) :
```ts
colorMode: {
  preference: 'system',   // était 'light'
  fallback: 'light',
  classSuffix: '',        // classe = `.dark` (pas `.dark-mode`)
  storageKey: 'nuxt-color-mode-admin4',
}
```
`@nuxtjs/color-mode` est fourni par `@nuxt/ui` ; `useColorMode()` est auto-importé. La classe
`.dark` est posée sur `<html>`.

### 2.2 Sélecteur de thème
Dans [`components/admin/Header.vue`](../../sources/app4/components/admin/Header.vue) :
- tableau `themeOptions` = `light` / `dark` / `system` (icônes `sun` / `moon` / `computer-desktop`) ;
- segmented control (icône **au-dessus** du label, `flex-col`, `min-w-0` + `truncate` pour
  ne pas déborder dans le dropdown `w-64`) ;
- présent **deux fois** : dans le dropdown utilisateur (desktop) **et** dans le menu mobile ;
- entouré de `<ClientOnly>` (évite le flash d'hydratation SSR≠client) ;
- écrit dans `colorMode.preference`.
- i18n : clés `theme.label / theme.light / theme.dark / theme.system` (fr + en).

## 3. Palette et conventions de conversion

app4 n'utilise **pas** la palette Tailwind neutre (gray/zinc) mais une échelle custom **`header`**
(bleu marine `#20265b`, définie via `@theme` dans [`admin.css`](../../sources/app4/assets/css/admin.css)),
plus les couleurs sémantiques Nuxt UI (`primary`, `success`, `warning`, `danger`/`error`, `info`).

### 3.1 Règle d'or
**Ne pas inverser globalement l'échelle `header`** : `header-950` sert de fond au bandeau ET
`header-900` sert de texte ailleurs — une inversion casserait le header. On ajoute des **variantes
`dark:` explicites** à chaque usage.

### 3.2 Table de correspondance (à réappliquer telle quelle)

| Classe claire | Variante dark à ajouter |
|---|---|
| `text-header-900` | `dark:text-header-50` |
| `text-header-800` | `dark:text-header-100` |
| `text-header-700` | `dark:text-header-200` |
| `text-header-600` | `dark:text-header-300` |
| `text-header-500` | `dark:text-header-400` |
| `text-header-400` | `dark:text-header-500` |
| `bg-header-50` | `dark:bg-header-900` |
| `bg-header-100` | `dark:bg-header-800` |
| `bg-header-200` | `dark:bg-header-700` |
| `bg-white` | `dark:bg-header-900` |
| `border-header-100` | `dark:border-header-800` |
| `border-header-200` | `dark:border-header-700` |
| `border-header-300` | `dark:border-header-700` |
| `divide-header-200` | `dark:divide-header-700` |
| `hover:bg-header-50` | `dark:hover:bg-header-800` |
| `hover:bg-header-100` | `dark:hover:bg-header-800` |
| `hover:text-header-900` | `dark:hover:text-header-50` |

**Fonds sémantiques pâles** (états actifs, surlignages, badges) : `bg-<couleur>-50/100` →
`dark:bg-<couleur>-950/900`, et repasser le texte en clair si besoin
(ex. `bg-success-100` cellule → `bg-success-100 dark:bg-success-900 text-header-900 dark:text-header-50`).
Le bloc `bg-primary-600 text-white` (boutons pleins) fonctionne dans les deux modes sans variante.

**Icônes / textes colorés (contraste)** : les `text-<couleur>-600/…/900` (violet, rouge/danger,
primary sur boutons d'action, liens) restent **trop sombres** sur le fond bleu marine en dark et
sont peu lisibles. Toujours ajouter une variante claire : `dark:text-<couleur>-300` (ou `-400`
pour danger), en miroir des hovers (`hover:text-<c>-800` → `dark:hover:text-<c>-200`).
La page `games` sert de référence (`dark:text-primary-300`).

### 3.3 Script d'application (boundary-safe)
La conversion a été faite avec un script Python qui ajoute la variante `dark:` uniquement là où
la classe de base apparaît comme **token complet** (respect des limites de mot, pas de double
application, traitement des préfixes `hover:`/`focus:` avant les versions nues). Réutilisable :

```python
import re
# mapping = liste ordonnée (base, dark) — voir §3.2, préfixes hover: en premier
def boundary_sub(text, base, dark):
    pat = re.compile(r'(?<![\w:/.\-])' + re.escape(base) + r'(?![\w:/.\-])')
    out, last = [], 0
    for m in pat.finditer(text):
        if text[max(0,m.start()-5):m.start()] == 'dark:':  # déjà en variante dark
            continue
        out.append(text[last:m.end()]); out.append(' ' + dark); last = m.end()
    out.append(text[last:]); return ''.join(out)
```
⚠️ Ne s'applique **que** aux classes statiques. Les ternaires `:class` (états actifs) doivent
être édités à la main (voir §5). Vérifier après : `grep bg-white | grep -v dark:` doit être vide.

## 4. Socle CSS (fait — [`assets/css/admin.css`](../../sources/app4/assets/css/admin.css))

### 4.1 Tokens Nuxt UI en dark
Le bloc `.dark` qui **neutralisait** le dark mode a été remplacé par un vrai mapping des tokens
`--ui-*` sur l'échelle `header` (fond/texte/bordure) → `UButton`, `UInput`, `UCard`, `UModal`…
s'accordent au chrome bleu marine au lieu du zinc par défaut. `color-scheme: dark` ajouté.

### 4.2 Pièges CSS globaux résolus (⚠️ à connaître)
Des **règles CSS globales** avaient une spécificité supérieure aux utilitaires `dark:` et
gagnaient la cascade même en dark. Corrigées :

- **`:root:not(.dark)`** : la règle `.bg-white { color: noir }` était scopée `:root`, mais
  `.dark` vit sur `<html>` (= `:root`) → elle s'appliquait aussi en dark et forçait du texte noir
  sur les surfaces sombres. **Toute règle « light-only » doit utiliser `:root:not(.dark)`**, pas `:root`.
- `table thead`, `table tbody tr`, `tr:hover` : fonds clairs codés en dur → bloc `.dark table …` ajouté.
- `.dark table tbody { color: header-50 }` : les `<td>` sans couleur de texte héritaient du noir
  (numéro, terrain, code, score illisibles) → couleur par défaut posée sur `tbody`.
- `.editable-cell`, `.link-value`, `input[type=date]`, `select:not([data-ui])`, autofill
  webkit : équivalents `.dark …` ajoutés.

## 5. Cas particuliers rencontrés (à reproduire sur les autres pages)

- **Champs de recherche / autocomplete** ([`RefereeAutocomplete.vue`](../../sources/app4/components/admin/RefereeAutocomplete.vue)) :
  input en `bg-white` sans couleur de texte → **blanc sur blanc**. Toujours ajouter à la fois
  `dark:bg-header-900` **et** `text-header-900 dark:text-header-50` sur les inputs. Idem pour les
  dropdowns téléportés dans `<body>` (ils sont hors modale mais dans `html.dark`, donc `dark:` marche).
- **Selects natifs** : les règles `.dark select` couvrent le contrôle, mais un état actif en
  `bg-warning-50` (utilitaire) l'emporte → ajouter `dark:bg-warning-950` sur ces ternaires.
- **Modales** : converties via [`Modal.vue`](../../sources/app4/components/admin/Modal.vue) et
  [`ConfirmModal.vue`](../../sources/app4/components/admin/ConfirmModal.vue). Le **corps** de modale
  reçoit `dark:text-header-100` pour que le texte non coloré (labels radio, etc.) reste lisible.
- **Surlignages de lignes** (sélection, verrouillé, conflit, repos) : ternaires `:class` édités à
  la main avec variantes foncées.
- **Barres de titre à couleur fixe `bg-<c>-300`/`bg-<c>-200`** (en-têtes de poule/phase, bandeaux
  de section) avec texte `text-header-900 dark:text-header-50` : le script passe le texte en clair
  mais laisse le fond clair fixe → **texte clair sur fond clair**. Ajouter le `dark:` du fond pour
  l'assombrir (ex. rankings : `bg-primary-300 dark:bg-primary-800`, `bg-success-300 dark:bg-success-800`).
  Ces fonds `-300`/`-200` sont hors mapping §3.2, donc jamais traités automatiquement.
- **Inputs/selects natifs sans classe `bg`** : beaucoup de champs (dates, `type="text"`, selects de
  filtre) n'ont que `border … rounded-lg` et héritent du fond blanc navigateur → **blanc sur blanc**
  en dark. Ajouter `bg-white dark:bg-header-900 text-header-900 dark:text-header-50`. Le script
  n'ajoute pas de `bg` là où il n'y en a pas.

## 6. Fichiers déjà convertis (référence)

**Socle** : `nuxt.config.ts`, `assets/css/admin.css`, `layouts/admin.vue`, i18n `fr.json`/`en.json`.

**Composants partagés** (donc dark OK sur toute l'app) : `Header`, `PageHeader`, `Toolbar`,
`Pagination`, `CardList`, `Card`, `ContextBadge`, `WorkContextSelector`, `WorkContextSummary`,
`EventGroupSelect`, `CompetitionGroupedSelect`, `CompetitionMultiSelect`, `CompetitionSingleSelect`,
`UsersCompetitionFilter`, `Modal`, `ConfirmModal`, `RefereeAutocomplete`, `TextAutocomplete`,
`AthleteAutocomplete`.

**Page pilote (complète, à prendre comme modèle)** :
[`pages/games/index.vue`](../../sources/app4/pages/games/index.vue) — tableau, toolbar, filtres,
dropdowns, modale de formulaire, autocompletes.

**Pages converties ensuite** : `documents/index`, `competitions/index`, `teams/index` (tableaux
sectionnés/par poule, badges de niveau/statut, bannières verrou/erreur, modales de formulaire,
dropdowns téléportés, recherches club/historique), puis `gamedays/index`, `rankings/index`,
`stats/index` (édition inline, colonnes calendrier vert, en-têtes de poule/phase à couleur fixe,
export XLSX/PDF, modales calendrier/officiels/transfert, autocompletes commune/athlète).

## 7. Suivi — reste à faire

### 7.1 Pages non converties (contiennent des `bg-white` sans `dark:`)
`users/index`, `presence/team/[teamId]`, `journal/index`, `index` (home),
`athletes/index`, `presence/match/[matchId]/team/[teamCode]`, `referees-pool/index`, `rc/index`,
`gamedays/schema`, `rankings/initial`, `live/cache-manager`, `groups/index`, `clubs/index`,
`events/[id]/gamedays`, `clubs/team/[numero]`, `select-mandate`, `events/index`,
`competitions/copy`, `reset-password`, `operations/index`, `login`, `forgot-password`,
`access-request`.

### 7.2 Composants non convertis
`tv/*` (ConditionalParams, ScenarioEditor, GlobalBar, LabelsModal, PresentationSelector,
PlayerNumberGrid, ChannelSelector, ChannelPanel), `UserMandateForm`, `UserEditModal`,
`AthleteEditModal`, `operations/*` (TeamsTab, SeasonsTab, PlayersTab, ImagesTab, SystemTab,
ImportExportTab), `PlayerAutocomplete`,
`ClubAutocomplete`,
`ActionButton`, `PointsGridEditor`, `schema/*`.

**Convertis** : `documents/DocumentsCompetitionSummary`, `CompetitionAutocomplete`,
`CompetitionImagePicker` (fait en même temps que les pages documents/competitions/teams),
`TextAutocomplete`, `AthleteAutocomplete` (fait avec gamedays/rankings/stats — input + dropdown +
bouton clear).

> Priorité suggérée : les **autocompletes** (`*Autocomplete.vue`) et **modales de formulaire**
> (`UserEditModal`, `AthleteEditModal`, `UserMandateForm`) d'abord — mêmes pièges blanc-sur-blanc
> que RefereeAutocomplete, visibles sur beaucoup de pages.

### 7.3 Méthode par page (checklist)
1. Appliquer le script §3.3 avec le mapping §3.2 sur la page.
2. `grep -nE 'bg-white' <page> | grep -v dark:` → doit être vide. Vérifier aussi l'absence de
   `dark:` dupliqué (`(dark:\S+)\s+\1`) que le script peut produire.
3. Éditer à la main les ternaires `:class` d'états actifs (`bg-*-50/100`) → variantes §3.2.
4. **Inputs/selects sans classe `bg`** (piège blanc/blanc) : ajouter
   `bg-white dark:bg-header-900 text-header-900 dark:text-header-50`. Idem autocompletes (composant partagé).
5. **Barres de titre à couleur fixe** `bg-<c>-300`/`-200` (hors mapping) : ajouter le `dark:` du fond
   (`dark:bg-<c>-800`) sinon texte clair sur fond clair.
6. Contrôle visuel en dark **et** clair. (⚠️ `npx eslint` échoue dans le conteneur `kpi_node_app4` —
   `Object.groupBy is not a function`, Node trop ancien — utiliser les greps ci-dessus à la place.)

## 8. Vérification manuelle
Pas de tests auto sur le rendu couleur. Vérifier chaque page dans les deux modes (toggle via
avatar → Thème). Points sensibles : contraste texte, en-têtes de tableau, dropdowns téléportés,
champs de saisie (blanc/blanc), états actifs de filtres.
