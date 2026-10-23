# Recherche globale

**Phase** : 3 — **Statut** : ✅ Validée (09/10/2026) — 🛠 Implémentée (à livrer) — **Route** : `/search?q=` (+ `/en`) — **Nouveau** (pas d'équivalent
legacy)

## 1. Objectif

Trouver en un seul champ une compétition, un événement, une équipe ou un club. Les personnes ne sont **pas**
cherchables (stratégie § 11).

## 2. Contenu et comportement

1. **Recherche depuis l'en-tête** (SITE_LAYOUT.md § 2.2), **sur tous les écrans** (18/10/2026) : l'en-tête
   n'affiche qu'une **icône loupe** (bouton bascule, `aria-expanded`). Elle **ouvre un bloc de recherche** inséré
   entre la barre de navigation et le contenu de la page courante, qui reste affiché dessous : on ne quitte pas la
   page. Le bloc contient le champ (formulaire GET vers `/search`, suggestions, focus placé dans le champ à
   l'ouverture) et un bouton **« Fermer la recherche »** (icône croix). Il se ferme par ce bouton, en cliquant de
   nouveau sur la loupe, ou par une navigation (choix d'une suggestion, soumission). Sans JavaScript, la loupe
   reste un lien vers la page `/search`, qui porte son propre champ.
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

- **SRC-01** — La loupe de l'en-tête est présente sur toutes les pages et mène à `/search` sans JavaScript ; le champ
  du bloc de recherche soumet vers `/search`.
- **SRC-06** — Sur tous les écrans, la loupe ouvre un bloc de recherche entre la navigation et `main`, sans quitter la
  page ; le bloc se ferme par son bouton « Fermer la recherche » ou par un nouveau clic sur la loupe.
- **SRC-02** — Suggestions après 2 caractères, groupées par catégorie, utilisables au clavier ; Échap ferme.
- **SRC-03** — La page affiche une section par catégorie non vide, 8 résultats au plus, liens vers les pages du
  site.
- **SRC-04** — Aucune personne n'apparaît dans les résultats.
- **SRC-07** — Page de résultats (maquettes du 22/10/2026) : grand champ de recherche ; filtres par type en liens (`kind=`) avec leur nombre, « Tout » d'abord ; sans filtre, le **premier club** est le meilleur résultat, en tête (logo, « Club, {code} », nom, département, « Voir sur la carte » → `/clubs?club={code}`) et n'est pas répété ; une ligne par résultat avec une information à droite (saison, dates, code, mois de publication).
- **SRC-05** — `q` trop court, aucun résultat et 429 ont chacun leur message ; la page n'est jamais indexée.
