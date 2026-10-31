# Historique et palmarès

**Phase** : 3 — **Statut** : ✅ Validée (09/10/2026) — 🛠 Implémentée (à livrer) — **Routes** : `/history` → `/history/{group}` (+ `/en`)
— **Remplace** : `kphistorique.php` — **Entrée de menu** : `history`

## 1. Objectif

Le palmarès d'une compétition au fil des saisons (« qui a gagné la Nationale 1 en 2019 ? »).

## 2. Contenu et comportement

1. `/history` : sélecteur de groupe (sections et libellés comme la page Compétitions) ; sans choix, redirige (302)
   vers le premier groupe de la section nationale (règle CPL-02).
2. `/history/{group}` : `<h1>` « Palmarès — {groupe} » ; une section par saison (décroissante), et dans chaque
   saison une carte par compétition finale terminée : titre (`display_title`, `soustitre2`), **podium** (rangs 1 à
   3 : la **médaille remplace le numéro de rang**, couleur et texte), puis les autres équipes classées, repliées (« Voir le classement complet »,
   `<details>`).
3. Chaque carte mène au classement de la compétition (`/competitions/{s}/{c}/ranking`) ; chaque équipe à sa fiche
   (`/teams/{number}`).
4. Navigation rapide par saison : liste d'ancres (`#saison-2019`) en tête de page.
5. Groupe sans palmarès : 404 (le sélecteur ne le propose pas).

### 2 bis. Présentation (maquettes du 22/10/2026)

Pas de classement historique des titres (il mettrait en avant des équipes disparues). De haut en bas :

1. **Tenant du titre** (bandeau marine) : le champion de la compétition finale de la saison la plus récente (la
   première compétition de la saison qui attribue une médaille d'or), son logo, « Champion {saison} », sa **série en
   cours** (« 3e titre d'affilée, depuis 2024 ») ou, sans série, son nombre de titres (« 4e titre ») ; à droite les
   2e et 3e de cette saison.
2. **Frise des dix dernières saisons** : saison, logo et nom du champion (défilement horizontal sur mobile).
3. **Toutes les saisons** : raccourcis par décennie (« Années 2010 », ancre de la saison la plus récente de la
   décennie), puis un tableau saison (fusionnée sur ses compétitions) / compétition (lien vers son classement,
   masquée sur mobile) / champion / 2e / 3e, médaille de couleur **et** textuelle. **Toucher une équipe surligne
   ses podiums** (tableau et frise, JavaScript ; sans lui, rien ne change) ; « Tout afficher normalement » l'annule.
   Les équipes mènent à leur fiche depuis le bandeau et la frise.

Remplace le § 2 points 2 et 4 (cartes par saison, classement complet replié, ancres par saison).

## 3. Données et cache

`GET /history`, `GET /history/{group}` (API_PUBLIC_TRANSVERSE.md § 3.3). Cache `maxAge: 3600, swr: true` : le
palmarès ne change qu'à la fin d'une compétition.

## 4. SEO et accessibilité

Titre « Palmarès {groupe} — kayak-polo.info » ; description « Palmarès du kayak-polo {groupe}, saison par
saison. » ; une page indexable par groupe.

## 5. Critères d'acceptation

- **HIS-01** — `/history` redirige vers le groupe par défaut ; le sélecteur fonctionne sans JavaScript ; des ancres par décennie mènent aux saisons.
- **HIS-02** — Saisons décroissantes ; seules les compétitions publiées, terminées et du tour final apparaissent.
- **HIS-03** — Podium : la médaille (visuelle et textuelle) remplace le rang ; le classement complet reste sur la page de la compétition (lien).
- **HIS-06** — Le tenant du titre ouvre la page : champion de la saison la plus récente, série en cours (ou nombre de titres), 2e et 3e.
- **HIS-07** — Frise des dix dernières saisons ; tableau de toutes les saisons avec raccourcis par décennie ; toucher une équipe surligne ses podiums.
- **HIS-04** — Liens vers le classement de chaque compétition et la fiche de chaque équipe.
- **HIS-05** — Groupe inconnu ou sans palmarès → 404 ; l'entrée de menu « Historique et palmarès » devient interne.

## 6. Correspondance des anciennes URL (phase 5)

`kphistorique.php?Group=G` → `/history/G`.
