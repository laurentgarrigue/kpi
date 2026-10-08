# Specs du site public (app3)

Specs fonctionnelles et techniques du nouveau site public `kayak-polo.info` (app3, Nuxt 4 SSR), écrites
**avant** l'implémentation. Stratégie d'ensemble :
[PUBLIC_SITE_REDESIGN_STRATEGY.md](../../developer/in-progress/plans/PUBLIC_SITE_REDESIGN_STRATEGY.md).
Principes de code et *Definition of Done* : [CLEAN_CODE.md](../../developer/guides/CLEAN_CODE.md).

## Processus

1. **Rédiger la spec** à partir du modèle ci-dessous, statut `📝 Brouillon`.
2. **Valider** : relecture par le porteur du projet → statut `✅ Validée`. Les questions ouvertes sont
   tranchées avant de coder.
3. **Implémenter en TDD** : chaque critère d'acceptation (`XXX-nn`) a au moins un test qui le cite dans son
   nom. Statut `🛠 En cours`.
4. **Livrer** sur `beta.*` (préprod puis prod) → statut `🚀 Livrée (beta)`. À la bascule → `🌐 En ligne`.

Une évolution d'une page livrée commence par la mise à jour de sa spec (critères ajoutés ou modifiés).

## Index

| Spec | Objet | Phase | Statut |
|---|---|---|---|
| [SITE_PLATFORM.md](SITE_PLATFORM.md) | Socle technique : app3 SSR, `kpi-layer`, environnements, domaines beta, non-indexation, santé, déploiement | 1 | 📝 Proposée — 🛠 Implémentée (validation à la revue de PR) |
| [SITE_LAYOUT.md](SITE_LAYOUT.md) | Template général : en-tête, bandeau beta, contenu, pied de page, thème, accessibilité, SEO par défaut | 1 | 📝 Proposée — 🛠 Implémentée (validation à la revue de PR) |
| [SITE_NAVIGATION.md](SITE_NAVIGATION.md) | Menus et sous-menus, repli vers le legacy, navigation mobile, langue | 1 | 📝 Proposée — 🛠 Implémentée (validation à la revue de PR) |
| [PAGE_HOME.md](PAGE_HOME.md) | Page d'accueil (version phase 1, évolutions phase 4a) | 1 | 📝 Proposée — 🛠 Implémentée (validation à la revue de PR) |
| [API_PUBLIC_RESULTS.md](API_PUBLIC_RESULTS.md) | api2 : endpoints publics des résultats, refactorisation sans régression pour app2 | 2 | 📝 Proposée |
| [PAGE_COMPETITIONS.md](PAGE_COMPETITIONS.md) | Liste des compétitions d'une saison et d'un groupe, classements compacts | 2 | 📝 Proposée |
| [PAGE_COMPETITION.md](PAGE_COMPETITION.md) | Page compétition et ses onglets (games, pitches, info, progress, phases, ranking, stats) | 2 | 📝 Proposée |
| [PAGE_EVENT_GROUP.md](PAGE_EVENT_GROUP.md) | Vues agrégées événement et groupe (matchs, terrains) | 2 | 📝 Proposée |
| `PAGE_CALENDAR.md` | Calendrier + ICS | 3 | ⏳ À rédiger |
| `PAGE_HISTORY.md`, `PAGE_TEAM.md`, `PAGE_CLUBS.md` | Historique, fiche équipe, clubs (+ carte, logos) | 3 | ⏳ À rédiger |
| `FEATURE_SEARCH.md` | Recherche globale | 3 | ⏳ À rédiger |
| `PAGE_NEWS.md`, `PAGE_CONTENT.md`, `FEATURE_CMS.md` | Articles, pages éditoriales, module éditorial (app4 + api2) | 4a | ⏳ À rédiger |
| `FEATURE_FORMS.md` | Formulaires d'inscription | 4b | ⏳ À rédiger |
| `SITE_REDIRECTS.md` | Table des redirections 301 (legacy, WordPress) | 5 | ⏳ À rédiger |

> Les specs de l'**administration** (app4) restent dans [DOC/specs/](../) (`PAGE_*.md`). Les specs des
> écrans d'édition du futur module éditorial y seront ajoutées en phase 4a.

## Modèle de spec

```markdown
# <Titre>

**Phase** : n — **Statut** : 📝 Brouillon — **Route(s)** : `/…` — **Remplace** : `kp….php`

## 1. Objectif
Une ou deux phrases : à quoi sert la page, pour qui.

## 2. Contenu et comportement
Ce qui est affiché, dans quel ordre, avec quelles interactions. Cas vides et erreurs.

## 3. Données
Endpoints api2 (existants / à créer), champs utilisés, règles de publication, cache (`routeRules`).

## 4. SEO et accessibilité
Titre, description, données structurées, points d'accessibilité spécifiques.

## 5. Critères d'acceptation
- **XXX-01** — Étant donné …, quand …, alors … (testable)

## 6. Hors périmètre / questions ouvertes
```
