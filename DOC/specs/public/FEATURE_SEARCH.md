# Recherche globale

**Phase** : 3 — **Statut** : 📝 Proposée — **Route** : `/search?q=` (+ `/en`) — **Nouveau** (pas d'équivalent
legacy)

## 1. Objectif

Trouver en un seul champ une compétition, un événement, une équipe ou un club. Les personnes ne sont **pas**
cherchables (stratégie § 11).

## 2. Contenu et comportement

1. **Champ de recherche dans l'en-tête** (SITE_LAYOUT.md § 2.2) : icône loupe + champ (replié en icône sous
   `md`) ; formulaire GET vers `/search`.
2. **Suggestions** (avec JavaScript, après 2 caractères et 300 ms) : panneau sous le champ, résultats groupés par
   catégorie, navigation clavier (motif « combobox » ARIA, Échap ferme) ; « Voir tous les résultats » → `/search`.
3. **Page `/search?q=`** : `<h1>` « Résultats pour « q » » ; une section par catégorie non vide (compétitions,
   événements, équipes, clubs), 8 résultats au plus chacune ; chaque résultat mène à sa page :
   compétition → `/competitions/{s}/{c}`, événement → `/events/{id}`, équipe → `/teams/{number}`,
   club → `/clubs/{code}`.
4. `q` absent ou < 2 caractères : page avec le champ seul et un rappel du minimum. Aucun résultat : message et
   suggestions (« Vérifiez l'orthographe… »). Trop de requêtes (429) : message dédié.
5. En phase 4a, les articles et pages éditoriales s'ajoutent comme nouvelles catégories (sans modifier les
   autres).

## 3. Données et cache

`GET /search?q=` (API_PUBLIC_TRANSVERSE.md § 3.6). Pas de cache serveur des pages de recherche
(`cache: false`) ; api2 répond avec `Cache-Control: public, max-age=300`.

## 4. SEO et accessibilité

Pages de recherche **non indexées** (`noindex` même après la bascule) ; balise `<search>` / rôle `search` ;
suggestions annoncées par une région `aria-live`.

## 5. Critères d'acceptation

- **SRC-01** — Le champ de l'en-tête est présent sur toutes les pages et soumet vers `/search` sans JavaScript.
- **SRC-02** — Suggestions après 2 caractères, groupées par catégorie, utilisables au clavier ; Échap ferme.
- **SRC-03** — La page affiche une section par catégorie non vide, 8 résultats au plus, liens vers les pages du
  site.
- **SRC-04** — Aucune personne n'apparaît dans les résultats.
- **SRC-05** — `q` trop court, aucun résultat et 429 ont chacun leur message ; la page n'est jamais indexée.
