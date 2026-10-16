# Équipes : recherche et fiche équipe

**Phase** : 3 — **Statut** : ✅ Validée (09/10/2026) — 🛠 Implémentée (à livrer) — **Routes** : `/teams[?q=]`, `/teams/{number}[?season=&competition=]`
(+ `/en`) — **Remplace** : `kpequipes.php`, `searchEquipes.php` — **Entrée de menu** : `teams`

## 1. Objectif

Retrouver une équipe, son palmarès et sa composition dans chaque compétition.

## 2. Contenu et comportement

### 2.1 `/teams`
- Champ de recherche (formulaire GET `q`, 2 caractères minimum ; avec JavaScript, suggestions au fil de la frappe
  après 300 ms, accessibles au clavier — motif « combobox » ARIA).
- Résultats : nom de l'équipe et club, lien vers la fiche. Aucun résultat → message.

### 2.2 `/teams/{number}`
- En-tête : nom de l'équipe, logo du club (règle § 3.5 de l'API), lien vers la fiche du club (`/clubs/{code}`),
  couleurs de l'équipe et photo d'équipe s'il y en a (avec la saison, texte alternatif « Photo de l'équipe {nom}
  ({saison}) », Q-P3-3). Le logo du club et les couleurs **s'agrandissent au survol** et au focus clavier
  (×3, comme la photo d'équipe affichée en grand), sans animation si `prefers-reduced-motion`.
- **Palmarès** : tableau saison / compétition / rang ; chaque ligne mène au classement de la compétition.
  **Une cellule de saison par saison** (lignes regroupées, `rowspan`). Pour un podium d'un tour final, **la médaille
  remplace le rang** (pas de rang à côté). Les classements des **tours intermédiaires** (`Code_tour` ≠ 10 :
  qualification, poule…) sont en **italique** et grisés, expliqués par une légende ; ils ne portent jamais de médaille.
- **Composition** : sélecteur saison + compétition (par défaut la plus récente) ; tableau : numéro, NOM Prénom,
  catégorie, rôle (capitaine, entraîneur), buts, cartons verts / jaunes / rouges (icônes **et** texte).
  Aucune licence, date de naissance ni photo individuelle (stratégie § 11).
- Équipe inconnue → 404.

### 2.3 Liens entrants
Les noms d'équipes des pages de résultats (PAGE_COMPETITION.md § 2.1, `PAGE_LINKS.team`) mènent désormais à
`/teams/{number}` au lieu de `kpequipes.php` ; idem pour l'historique et les clubs.

## 3. Données et cache

`GET /teams?q=`, `GET /team/{number}`, `GET /team/{number}/roster/{season}/{code}` (API_PUBLIC_TRANSVERSE.md
§ 3.4). Cache `maxAge: 300, swr: true`.

## 4. SEO et accessibilité

Titre « {équipe} — kayak-polo.info » ; données structurées `SportsTeam` (nom, sport, club). Tableaux avec
`<caption>`.

## 5. Critères d'acceptation

- **TEA-01** — `/teams?q=` fonctionne sans JavaScript ; avec JavaScript, suggestions accessibles au clavier ;
  moins de 2 caractères → aucune requête.
- **TEA-02** — La fiche affiche club (lien), couleurs et photo d'équipe quand elles existent, palmarès avec
  médailles et liens vers les classements.
- **TEA-03** — La composition par saison et compétition n'expose que nom, prénom, numéro, catégorie, rôle et
  statistiques de match ; la plus récente est sélectionnée par défaut.
- **TEA-04** — `PAGE_LINKS.team` passe à `/teams/{number}` : tous les liens d'équipe du site sont internes.
- **TEA-06** — Le logo du club et les couleurs s'agrandissent au survol et au focus clavier.
- **TEA-07** — Palmarès : saisons regroupées (une cellule par saison), médaille à la place du rang, tours intermédiaires en italique avec légende.
- **TEA-05** — Équipe inconnue → 404 ; l'entrée de menu « Équipes » devient interne.

## 6. Décisions (09/10/2026)

- **Q-P3-3 — Photo d'équipe et couleurs.** ✅ Retenu : les **couleurs et la photo d'équipe sont maintenues**,
  comme sur `kpequipes.php` (`img/KIP/colors`, `img/KIP/teams`, même règle d'année), **jusqu'à l'étude RGPD**
  (stratégie § 11), qui décidera de leur sort. Les photos **individuelles** restent exclues.

## 7. Correspondance des anciennes URL (phase 5)

`kpequipes.php?Equipe=N[&Compet=C&Saison=S]` → `/teams/N[?competition=C&season=S]`.
