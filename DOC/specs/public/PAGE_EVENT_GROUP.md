# Vues agrégées « événement » et « groupe »

**Phase** : 2 — **Statut** : 📝 Proposée
**Routes** :
- `/events/{id}` → `/events/{id}/{tab}`, `tab` ∈ `games` · `pitches` (+ `/en/…`)
- `/groups/{season}/{code}` → `/groups/{season}/{code}/{tab}`, mêmes onglets (+ `/en/…`)

**Remplace** : `kpmatchs.php` / `kpterrains.php` en mode `event=E` ou `Compet=*&Group=G` (choix « Tous »)

## 1. Objectif

Voir **tous les matchs** d'un événement (un tournoi réunissant plusieurs compétitions sur un même lieu) ou
d'un groupe de compétitions (par exemple toutes les poules d'une Nationale), et rejoindre la page de chaque
compétition. Les deux vues ne diffèrent que par leur **portée** (cf. `ResultsScope`, API_PUBLIC_RESULTS.md § 3) :
une seule implémentation de page, paramétrée.

## 2. Structure commune

```
Fil d'Ariane : Accueil › {événement}            (vue événement)
               Accueil › Compétitions {saison} › {groupe}   (vue groupe)
┌─────────────────────────────────────────────────────────────┐
│ [logo]  {libellé}   {lieu} · {dates}                         │  en-tête (ScopeHeader)
│ Suivre en direct (app2) ↗                                    │
├─────────────────────────────────────────────────────────────┤
│ Compétitions : [Toutes] [N1H Poule A] [N1H Poule B] …        │  puces (ScopeCompetitions)
├─────────────────────────────────────────────────────────────┤
│ Matchs | Terrains                                            │  onglets (ResultsTabs, réutilisé)
├─────────────────────────────────────────────────────────────┤
│ contenu de l'onglet (composants de PAGE_COMPETITION, colonne « compétition » en plus) │
└─────────────────────────────────────────────────────────────┘
```

- **`/events/{id}`** et **`/groups/{season}/{code}`** redirigent (302) vers l'onglet `games`.
- **En-tête** :
  - événement : `libelle`, `place`, `logo`, dates de `GET /event/{id}/competitions` ;
    « Suivre en direct » → `{app2}/event/{id}` ;
  - groupe : libellé (`libelle_en` sous `/en` s'il existe) issu de `GET /groups/{season}`, saison ;
    « Suivre en direct » → `{app2}/group/{season}/{code}`.
- **Puces des compétitions** : « Toutes » (active sur cette vue), puis une puce par compétition publiée
  (libellé `soustitre2`, sinon `display_title`), dans l'ordre d'api2. Une puce mène à la page de la compétition
  sur le **même onglet** :
  - vue événement : `/competitions/{season}/{code}/{tab}?event={id}` (contexte événement, PAGE_COMPETITION.md § 2) ;
  - vue groupe : `/competitions/{season}/{code}/{tab}`.
- **Onglets** : seulement **Matchs** et **Terrains** (comme le legacy : les classements, phases et stats n'ont de
  sens que par compétition). Mêmes règles que PAGE_COMPETITION.md § 2 (liens, `aria-current`, mobile).

## 3. Onglets

Les onglets **réutilisent les composants** de PAGE_COMPETITION.md § 3.1 et § 3.2 (liste de matchs, grille des
terrains, filtres, statuts, score provisoire, liens `PAGE_LINKS`, rafraîchissement 60 s), avec deux différences :

1. chaque match affiche sa **compétition** (code court + libellé au survol / texte accessible), lien vers la page
   de la compétition (avec `?event=` en vue événement) ;
2. le filtre `gameday` n'existe pas (les journées sont propres à une compétition) ; les filtres `day` et
   `upcoming` s'appliquent à l'identique.

Ordre des matchs : celui d'api2 (date, heure, terrain), groupés par date.

## 4. Données

| Besoin | Endpoint |
|---|---|
| Événement : en-tête + compétitions | `GET /event/{id}/competitions` *(nouveau)* |
| Événement : matchs | `GET /event/{id}/games` *(existant, app2)* |
| Groupe : libellé | `GET /groups/{season}` *(existant, app2)* |
| Groupe : compétitions | `GET /group/{season}/{code}/competitions` *(nouveau ; le classement compact n'est pas affiché ici)* |
| Groupe : matchs | `GET /group/{season}/{code}/games` *(existant, app2)* |

Cache : `routeRules` `cache: { maxAge: 60, swr: true }` sur `/events/**` et `/groups/**`.

## 5. SEO et accessibilité

- Titre : « {onglet} — {libellé} — kayak-polo.info » ; description : « Matchs et résultats de {libellé}. »
- Vue événement : données structurées `SportsEvent` (nom, lieu, dates).
- Puces : `<nav aria-label="Compétitions">`, `aria-current="page"` sur « Toutes ».
- Mêmes exigences de tableaux et de statuts que PAGE_COMPETITION.md § 5.

## 6. Critères d'acceptation

- **EVT-01** — `/events/{id}` redirige (302) vers `/events/{id}/games` (préfixe `/en` conservé).
- **EVT-02** — L'en-tête affiche libellé, lieu, dates, logo s'il existe, et le lien app2 `{app2}/event/{id}`.
- **EVT-03** — Une puce par compétition de l'événement, menant à `/competitions/{s}/{c}/{onglet}?event={id}`.
- **EVT-04** — Les matchs de toutes les compétitions de l'événement sont listés, chacun avec sa compétition.
- **EVT-05** — Événement inconnu ou sans compétition publiée → page 404 du site.
- **GRP-01** — `/groups/{s}/{c}` redirige (302) vers `/groups/{s}/{c}/games`.
- **GRP-02** — L'en-tête affiche le libellé du groupe (anglais sous `/en` s'il existe), la saison et le lien app2 `{app2}/group/{s}/{c}`.
- **GRP-03** — Une puce par compétition du groupe, menant à `/competitions/{s}/{c}/{onglet}` (sans paramètre `event`).
- **GRP-04** — Groupe inconnu ou sans compétition publiée pour la saison → page 404 du site.
- **AGG-01** — Les vues événement et groupe partagent une seule page paramétrée par la portée et réutilisent les composants Matchs / Terrains de la page compétition (aucune copie).
- **AGG-02** — Les filtres `day` et `upcoming` fonctionnent comme sur la page compétition ; `gameday` est ignoré.
- **AGG-03** — Grille de parité : en préprod, mêmes matchs que `kpmatchs.php?…&event=E` et `kpmatchs.php?…&Compet=*&Group=G` sur un événement et un groupe réels.

## 7. Correspondance des anciennes URL (pour la phase 5)

| Ancienne URL | Nouvelle URL |
|---|---|
| `kpmatchs.php?…&event=E` (toutes compétitions) | `/events/E/games` |
| `kpterrains.php?…&event=E` | `/events/E/pitches` |
| `kpmatchs.php?Saison=S&Compet=*&Group=G` | `/groups/S/G/games` |
| `kpterrains.php?Saison=S&Compet=*&Group=G` | `/groups/S/G/pitches` |

## 8. Évolutions induites

- **Accueil** (PAGE_HOME.md, HOME-04) : à la livraison de cette page, les cartes d'événement mènent à
  `/events/{id}` au lieu de `{app2}/event/{id}` (critère HOME-04 mis à jour dans la même PR).

## 9. Hors périmètre

- Classement agrégé d'un événement : non prévu (le legacy ne l'offre pas).
- Liste des événements (`/events`) : couverte par le calendrier (phase 3).
