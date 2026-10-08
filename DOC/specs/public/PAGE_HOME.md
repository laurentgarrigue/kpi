# Page d'accueil

**Phase** : 1 (version minimale) → 4a (version éditoriale) — **Statut** : 📝 Proposée
**Route** : `/` (FR), `/en` (EN) — **Remplace** : accueil WordPress (à la bascule)

## 1. Objectif

En phase 1 : une page d'accueil sobre qui présente le site, oriente vers les résultats et le suivi en direct,
et **valide la chaîne technique complète** (rendu serveur → api2 → affichage) avec un premier bloc de données.
En phase 4a, elle accueillera les contenus éditoriaux (à la une, actualités, blocs configurables).

## 2. Contenu et comportement (phase 1)

1. **Introduction**
   - `<h1>` : « Le kayak-polo en France » / « Canoe polo in France ».
   - Paragraphe : « Résultats, classements et calendriers des compétitions de kayak-polo, publiés par la
     Commission Nationale d'Activité Kayak-Polo de la FFCK. » (+ EN).
   - Deux boutons : **« Compétitions et résultats »** (même cible que l'entrée de menu, résolue par
     `resolveMenuLink`, donc legacy tant que la phase 2 n'est pas livrée) et **« Suivre un événement en
     direct »** (app2).
2. **Événements récents**
   - Titre de section « Événements récents » / « Recent events ».
   - Les **6 premiers** événements publiés renvoyés par l'endpoint existant `GET /events/all` (déjà triés
     du plus récent au plus ancien).
   - Une carte par événement : logo s'il existe (`{legacy}/img/{logo}`, texte alternatif = nom de
     l'événement, chargement différé), nom, lieu, année. La carte mène à la page de l'événement dans app2
     (`{app2}/event/{id}`), en attendant la page événement d'app3 (phase 2).
   - **Aucun événement** : message « Aucun événement publié pour le moment. »
   - **api2 indisponible** : message « Les événements sont momentanément indisponibles. » Le reste de la
     page est rendu normalement (statut HTTP 200).

## 3. Données

| Besoin | Endpoint | Champs utilisés |
|---|---|---|
| Événements récents | `GET /events/all` (existant, public) | `id`, `libelle`, `place`, `logo`, `year` |

- Les 6 premiers éléments sont sélectionnés **côté app3** : l'endpoint n'est pas modifié (aucun impact sur app2).
- Cache : `routeRules` `cache: { maxAge: 300, swr: true }` sur `/` et `/en` (5 minutes, stale-while-revalidate).

## 4. SEO et accessibilité

- Titre : « Le kayak-polo en France — kayak-polo.info » (gabarit commun) ; description par défaut (SITE_LAYOUT.md § 5).
- Les cartes d'événements forment une liste (`<ul>`) ; chaque carte est un seul lien au libellé explicite.

## 5. Critères d'acceptation

- **HOME-01** — La page affiche un unique `<h1>` traduit selon la langue.
- **HOME-02** — Le bouton « Compétitions et résultats » a la même cible que l'entrée de menu correspondante.
- **HOME-03** — Au plus 6 événements sont affichés, dans l'ordre renvoyé par api2.
- **HOME-04** — Chaque carte mène à `{app2}/event/{id}` et affiche le logo seulement s'il est renseigné.
- **HOME-05** — Liste vide → message « aucun événement » ; erreur api2 → message d'indisponibilité, page en 200.
- **HOME-06** — La sélection des événements est une fonction pure testée (`latestEvents(events, 6)`).

## 6. Évolutions prévues

- **Phase 2** : les cartes d'événement mènent à `/events/{id}` (PAGE_EVENT_GROUP.md § 8) ; HOME-04 sera mis à
  jour dans la PR qui livre la vue événement.
- **Phase 4a** : blocs éditoriaux administrables (à la une, dernières actualités, bandeau d'alerte, partenaires).

Ces évolutions mettront à jour cette spec avant implémentation.
