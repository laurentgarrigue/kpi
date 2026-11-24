# Accessibilité app4 — tailles de texte et zones cliquables

## Règles (critères de recette)

1. **Texte** : `text-sm` (14 px) minimum pour tout contenu lisible. `text-xs` (12 px) reste une exception
   (micro-libellés en majuscules, pastilles de comptage). Plus de `text-[9px]` / `[10px]` / `[11px]`.
2. **Zones cliquables** : ≥ 32 px de côté (WCAG 2.2 AA 2.5.8 impose 24 px, on vise plus large pour l'usage tactile
   bord de terrain). Bouton icône seul : icône `w-5 h-5` + `p-2`, ou icône `w-6 h-6` + `p-1.5`.
   Items de menu : `py-3`, icône `w-6 h-6`.
3. Icône seule cliquable : toujours un `title` / `aria-label`.

## Contraste (WCAG 2.2 AA : 4,5:1 texte, 3:1 icônes/bordures d'interface)

Ratios calculés sur la palette `header-*` (oklch → sRGB) :

| Fond | Texte minimal autorisé |
|---|---|
| Clair : blanc / `header-50` | `header-600` (5,1 / 4,7) — `500` = 3,7 ✗ |
| Clair : `header-100` / `header-200` | `header-700` (5,5 / 4,6) |
| Sombre : `header-950` | `header-400` (5,6) |
| Sombre : `header-900` / `header-800` (cartes, panneaux) | `header-300` (5,6 / 4,7) — `600` = 2,0 / 1,7 ✗ |

Règle : `text-header-500` et plus clair interdit en mode clair, `dark:text-header-400` et plus sombre interdit en mode
sombre. Les textes désactivés (`disabled`) sont exemptés par le WCAG mais ne doivent pas servir à porter une information.

### Couleurs hors `header-*` (audit 2026-11-18)

Audit automatisé (palette oklch réelle : `--ui-color-*` d'`admin.css` + `tailwindcss/theme.css`), 271 corrections :
fond supposé blanc/`header-50` en clair, `header-900` en sombre ; seuil 3:1 pour les icônes, 4,5:1 pour le texte.
Pièges de la palette : l'échelle `primary` est décalée (`primary-500` est déjà foncé, `primary-400` clair) ;
`success/warning/orange/amber-500` ne passent pas en clair (→ 700) ; `danger-400` ne passe pas en sombre (→ 300).
Limites de l'audit : le fond est deviné d'après la ligne (un `bg-*-500+` ou `dark:bg-*-50…400` sur la même ligne
exempte), les variantes `hover:`/`focus:` et les couleurs de fond/bordure ne sont pas auditées.

### Score normalisé (`.score-chip`, `admin.css`)

Pastille marine, chiffres blancs tabulaires 18 px gras ; perdant en `header-300` (5,6:1) une fois le match terminé
ou verrouillé (jamais en cours ni à égalité). Utilisée dans `pages/games/index.vue` (tableau + vue carte, cellules
éditables incluses : bordure pointillée conservée) et `pages/rankings/index.vue` (matchs de phase éliminatoire,
perdant grisé seulement si validé).

## Ampleur (relevé 2026-11-18, `pages/`, `components/`, `layouts/`)

~758 `text-xs` et ~45 `text-[10-11px]` dans les `.vue` ; pires : rankings (98), competitions (35), competitions/copy (35),
gamedays (33), presence/team/[teamId] (31), teams (29), users (26). Migration page par page, mêmes remplacements :
`text-xs → text-sm`, puis `text-[9-11px] → text-xs`, puis agrandir les icônes-boutons (`p-0.5`/`p-1` + `w-4`).

## Avancement

- [x] `pages/games/index.vue`
- [x] Menu utilisateur (`components/admin/Header.vue`)
- [x] Autres pages, composants et layouts : `text-xs → text-sm`, `text-[9-11px] → text-xs`, `UButton size="xs" → "sm"`,
  boutons icône seule `w-3/w-4` + `p-0.5…1.5` → `w-5 h-5` + `p-2` (script one-shot, 2026-11-18)
- [x] `components/scoring/` : tailles de texte, contraste, zones cliquables (à valider à l'œil par le propriétaire)
- [ ] **Hors périmètre, à traiter à part** : boutons icône avec texte, `<UButton>`/`<input>` à hauteur fixe, `AdminActionButton size="sm"` (`py-1`),
  contrôle visuel page par page (tableaux élargis par le passage en 14 px)
