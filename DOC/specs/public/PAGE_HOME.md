# Page d'accueil

**Phase** : 1 (version minimale) → 4a (version éditoriale) — **Statut** : ✅ Validée (08/10/2026, retours intégrés)
**Route** : `/` (FR), `/en` (EN) — **Remplace** : accueil WordPress (à la bascule)

## 1. Objectif

En phase 1 : une page d'accueil sobre qui présente le site, oriente vers les résultats et le suivi en direct,
et **valide la chaîne technique complète** (rendu serveur → api2 → affichage) avec un premier bloc de données.
En phase 4a, elle accueillera les contenus éditoriaux (à la une, actualités, blocs configurables).

## 2. Contenu et comportement (phase 1)

Le site couvre **essentiellement mais pas seulement** la France : championnats et coupes de France, mais aussi
compétitions européennes et mondiales, World Games, compétitions continentales et tournois internationaux,
en France ou à l'étranger. Les textes de l'accueil le reflètent.

1. **Introduction**
   - `<h1>` : « Le kayak-polo, en France et à l'international » / « Canoe polo, in France and worldwide ».
   - Paragraphe : « Résultats, classements et calendriers des compétitions de kayak-polo — championnats et
     coupes de France, tournois, compétitions européennes, mondiales et continentales — publiés par la
     Commission Nationale d'Activité Kayak-Polo de la FFCK. » (+ EN).
   - Deux boutons : **« Compétitions et résultats »** (même cible que l'entrée de menu, résolue par
     `resolveMenuLink`) et **« Suivre un événement en direct »** (app2, nouvel onglet — SITE_LAYOUT.md § 2.6).
2. **Prochains événements** (avant les événements récents)
   - Titre « Prochains événements » / « Upcoming events ».
   - Événements publiés **en cours ou à venir** : date de fin ≥ aujourd'hui (Europe/Paris), triés par date de
     début **croissante** (le plus proche d'abord), **6 au plus**.
   - Un événement en cours (début ≤ aujourd'hui ≤ fin) porte le badge « En cours ».
   - **Aucun** : « Aucun événement à venir pour le moment. »
3. **Événements récents**
   - Titre « Événements récents » / « Recent events ».
   - Événements publiés **terminés** : date de fin < aujourd'hui, du plus récent au plus ancien, **6 au plus**.
   - **Aucun** : « Aucun événement publié pour le moment. »
4. **Cartes** (même composant pour les deux sections) : logo s'il existe (`{legacy}/img/{logo}`, texte
   alternatif = nom de l'événement, chargement différé), nom, lieu, **dates** (« 12–14 juin 2026 », localisées).
   La carte mène à la page de l'événement du site (`/events/{id}`, PAGE_EVENT_GROUP.md), même onglet ; en phase 1
   (avant cette page), elle menait à `{app2}/event/{id}` dans un nouvel onglet.
5. **api2 indisponible** : un seul message « Les événements sont momentanément indisponibles. » à la place des
   deux sections. Le reste de la page est rendu normalement (statut HTTP 200).

La date du jour est calculée **côté serveur**, dans le fuseau Europe/Paris, et la répartition est transmise au
navigateur avec les données (pas de recalcul à l'hydratation).

## 3. Données

| Besoin | Endpoint | Champs utilisés |
|---|---|---|
| Événements | `GET /events/all` (existant, public) | `id`, `libelle`, `place`, `logo`, `start`, `end` |

- `start` / `end` (`Date_debut` / `Date_fin`, format `YYYY-MM-DD`, `null` possible) sont **ajoutés** à la
  réponse de `/events/{mode}` : ajout de champs seulement, sans effet sur app2 (qui ne les lit pas), couvert par
  un test d'intégration. Un événement sans date de fin est traité avec sa date de début ; sans aucune date, il
  n'apparaît dans aucune section.
- La répartition « à venir » / « récents » est faite **côté app3** par des fonctions pures.
- Cache : `routeRules` `cache: { maxAge: 300, swr: true }` sur `/` et `/en` (5 minutes, stale-while-revalidate).

## 4. SEO et accessibilité

- Titre : « Le kayak-polo, en France et à l'international — kayak-polo.info » (gabarit commun) ; description
  par défaut (SITE_LAYOUT.md § 5).
- Chaque section a un titre `<h2>` ; les cartes forment une liste (`<ul>`) ; chaque carte est un seul lien au
  libellé explicite, avec la mention « (nouvel onglet) ».

## 5. Critères d'acceptation

- **HOME-01** — La page affiche un unique `<h1>` traduit selon la langue.
- **HOME-02** — Le bouton « Compétitions et résultats » a la même cible que l'entrée de menu correspondante.
- **HOME-03** — « Événements récents » : au plus 6 événements terminés, du plus récent au plus ancien.
- **HOME-04** — Chaque carte mène à `/events/{id}` (phase 2), affiche les dates et le logo seulement s'il est
  renseigné.
- **HOME-05** — Sections vides → message propre à chacune ; erreur api2 → message d'indisponibilité, page en 200.
- **HOME-06** — La répartition est faite par des fonctions pures testées avec une date injectée
  (`upcomingEvents(events, today, 6)`, `recentEvents(events, today, 6)`).
- **HOME-07** — « Prochains événements » précède « Événements récents » : au plus 6 événements en cours ou à
  venir, du plus proche au plus lointain ; ceux en cours portent le badge « En cours ».
- **HOME-08** — `/events/{mode}` expose `start` et `end` sans modifier les autres champs (test d'intégration api2).

## 6. Évolutions prévues

- **Phase 2** (fait) : les cartes d'événement mènent à `/events/{id}` (PAGE_EVENT_GROUP.md § 8, HOME-04).
- **Phase 4a** : blocs éditoriaux administrables (à la une, dernières actualités, bandeau d'alerte, partenaires).

Ces évolutions mettront à jour cette spec avant implémentation.
