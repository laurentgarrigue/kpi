# Texte de contenu encore en `text-xs` (12 px)

Status: needs-triage
Blocked by: —

Spec : [ACCESSIBILITY.md § Règles, 1](../../../DOC/specs/ACCESSIBILITY.md) — `text-sm` (14 px) minimum pour tout
contenu lisible ; `text-xs` réservé aux micro-libellés en majuscules et aux pastilles de comptage.

## Constat (relevé du 20/11/2026, branche `feature/2fa`)

Après la migration du 18/11, il reste **46 `text-xs`** dans `pages/`, `components/` et `layouts/` d'app4. La plupart
sont des exceptions permises ; une douzaine sont du contenu lisible, à passer en `text-sm`. Les écrans 2FA (profil,
second facteur, enrôlement) ont été corrigés sur `feature/2fa`.

## À corriger (`text-xs` → `text-sm`)

Priorité aux messages d'erreur et aux consignes, lus au moment où l'utilisateur est en difficulté.

| Fichier | Lignes | Contenu |
|---|---|---|
| `pages/renew-password.vue` | 92, 109 | Règles du mot de passe ; erreur « les mots de passe ne correspondent pas » |
| `components/admin/UserMandateForm.vue` | 341, 345, 360 | « Chargement », intitulés de groupe de compétitions, liste vide |
| `components/games/PitchView.vue` | 104, 125 | En-tête et détail d'un terrain |
| `pages/games/index.vue` | 3506, 3528 | Libellés « équipe arbitre » des formulaires |
| `pages/games/index.vue` | 3038 | Coefficients A/B d'un match |
| `pages/games/index.vue` | 2381, 2870 | Code d'une entité dans la liste de sélection ; ligne horaire/score de la carte de match |

Les numéros de ligne datent du relevé : rechercher `text-xs` dans le fichier.

## Exceptions conformes, à laisser

- Intitulés de section en majuscules (`uppercase tracking-wider`) : menus et listes de `games/index.vue` (×13),
  `gamedays/index.vue` (×2), `RefereeAutocomplete.vue`, `rankings/initial.vue`, `admin/Header.vue` (×4).
- Pastilles et badges : compteurs `rounded-full` de `games/index.vue` (2278, 2340, 2361, 2459, 2876, 2882),
  `CompetitionRulesBadges.vue` (×4), `UserMandateForm.vue` 399, `clubs/team/[numero].vue` (89, 156),
  `teams/index.vue` 1426 (carré de couleur w-6).

## Validation

- `grep -rn 'text-xs' sources/app4/{pages,components,layouts}` ne renvoie plus que les exceptions listées ci-dessus.
- Contrôle visuel des tableaux concernés (`games`, `renew-password`, modale de mandat) : pas de retour à la ligne
  qui casse la mise en page, en clair comme en sombre.
