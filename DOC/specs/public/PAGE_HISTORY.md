# Historique et palmarès

**Phase** : 3 — **Statut** : ✅ Validée (09/10/2026) — 🛠 En cours — **Routes** : `/history` → `/history/{group}` (+ `/en`)
— **Remplace** : `kphistorique.php` — **Entrée de menu** : `history`

## 1. Objectif

Le palmarès d'une compétition au fil des saisons (« qui a gagné la Nationale 1 en 2019 ? »).

## 2. Contenu et comportement

1. `/history` : sélecteur de groupe (sections et libellés comme la page Compétitions) ; sans choix, redirige (302)
   vers le premier groupe de la section nationale (règle CPL-02).
2. `/history/{group}` : `<h1>` « Palmarès — {groupe} » ; une section par saison (décroissante), et dans chaque
   saison une carte par compétition finale terminée : titre (`display_title`, `soustitre2`), **podium** (rangs 1 à
   3 avec médaille, couleur et texte), puis les autres équipes classées, repliées (« Voir le classement complet »,
   `<details>`).
3. Chaque carte mène au classement de la compétition (`/competitions/{s}/{c}/ranking`) ; chaque équipe à sa fiche
   (`/teams/{number}`).
4. Navigation rapide par saison : liste d'ancres (`#saison-2019`) en tête de page.
5. Groupe sans palmarès : 404 (le sélecteur ne le propose pas).

## 3. Données et cache

`GET /history`, `GET /history/{group}` (API_PUBLIC_TRANSVERSE.md § 3.3). Cache `maxAge: 3600, swr: true` : le
palmarès ne change qu'à la fin d'une compétition.

## 4. SEO et accessibilité

Titre « Palmarès {groupe} — kayak-polo.info » ; description « Palmarès du kayak-polo {groupe}, saison par
saison. » ; une page indexable par groupe.

## 5. Critères d'acceptation

- **HIS-01** — `/history` redirige vers le groupe par défaut ; le sélecteur fonctionne sans JavaScript.
- **HIS-02** — Saisons décroissantes ; seules les compétitions publiées, terminées et du tour final apparaissent.
- **HIS-03** — Podium avec médailles (visuel et texte), puis classement complet repliable.
- **HIS-04** — Liens vers le classement de chaque compétition et la fiche de chaque équipe.
- **HIS-05** — Groupe inconnu ou sans palmarès → 404 ; l'entrée de menu « Historique et palmarès » devient interne.

## 6. Correspondance des anciennes URL (phase 5)

`kphistorique.php?Group=G` → `/history/G`.
