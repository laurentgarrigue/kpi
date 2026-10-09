# Vues agrégées « événement » et « groupe »

**Phase** : 2 — **Statut** : ✅ Validée (08/10/2026, retours intégrés)
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
  - vue événement : **reste dans l'événement** : `/events/{id}/{tab}?competition={code}` (la vue est filtrée sur
    cette compétition ; la compétition de la puce est marquée courante) ;
  - vue groupe : `/competitions/{season}/{code}/{tab}`.
- **Onglets de la vue groupe** : seulement **Matchs** et **Terrains** (comme le legacy). Mêmes règles que
  PAGE_COMPETITION.md § 2 (liens, `aria-current`, mobile).
- **Onglets de la vue événement** : les six onglets d'une compétition — Matchs, Terrains, Infos, Déroulement,
  Classement, Stats — **sans quitter l'événement** (`/events/{id}/{tab}`). Matchs et Terrains couvrent tout
  l'événement, ou la compétition choisie (`?competition=`). Infos, Déroulement, Classement et Stats exigent une
  compétition : celle du paramètre, sinon **la première de l'événement** ; la puce « Toutes » est alors
  **grisée** (non cliquable, `aria-disabled`) car inapplicable. Les onglets conservent la compétition choisie
  explicitement. Le lien de compétition d'un match (colonne « Compétition ») mène lui aussi à
  `/events/{id}/games?competition={code}`.

### 2.1 Accès à ces pages

L'accès à l'événement doit être **direct** depuis les pages de résultats, sans passer par l'accueil :

| Depuis | Règle | Spec |
|---|---|---|
| Compétitions (groupe choisi) | événement couvrant **toutes** les journées du groupe → encart + bouton « Voir l'événement » ; sinon liste discrète des événements liés | PAGE_COMPETITIONS.md § 2.4, CPL-11 |
| Page d'une compétition | événement couvrant **≥ 75 %** de ses journées (ou `?event=`) → encart + fil d'Ariane ; autres événements listés | PAGE_COMPETITION.md § 2.3, CMP-16 |
| Vue groupe (cette page) | même règle que la page Compétitions, encart sous l'en-tête | GRP-05 |
| Accueil | cartes « Prochains » / « Récents » → `/events/{id}` | PAGE_HOME.md, § 8 ci-dessous |

La vue groupe est atteinte par « Tous les matchs du groupe » (PAGE_COMPETITIONS.md) ; la vue événement depuis
l'accueil et ces encarts. Les règles « couvre » s'appuient sur `events[].share` (API_PUBLIC_RESULTS.md § 5.6).

## 3. Onglets

Les onglets **réutilisent les composants** de PAGE_COMPETITION.md § 3.1 et § 3.2 (liste de matchs, grille des
terrains, filtres, statuts, score provisoire, liens `PAGE_LINKS`, rafraîchissement 60 s), avec deux différences :

1. chaque match affiche sa **compétition** (code court + libellé au survol / texte accessible), lien vers la page
   de la compétition (avec `?event=` en vue événement) ;
2. le filtre `gameday` n'existe pas (les journées sont propres à une compétition) ; les filtres `day` et
   `upcoming` s'appliquent à l'identique.

Ordre des matchs : celui d'api2 (date, heure, terrain), groupés par date.

Lien « Liste des matchs (PDF) » (API_PUBLIC_RESULTS.md D-P2-2) : `PdfListeMatchs.php?idEvenement={id}` pour un
événement, `PdfListeMatchs.php?S={season}&Compet={codes séparés par des virgules}` pour un groupe (`…EN.php` en
anglais).

## 4. Données

| Besoin | Endpoint |
|---|---|
| Événement : en-tête + compétitions | `GET /event/{id}/competitions` *(nouveau)* |
| Événement : matchs | `GET /event/{id}/games` *(existant, app2)* |
| Groupe : libellé | `GET /groups/{season}` *(existant, app2)* |
| Groupe : compétitions, événements liés | `GET /group/{season}/{code}/competitions` *(nouveau ; le classement compact n'est pas affiché ici)* |
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
- **EVT-03** — Une puce par compétition de l'événement, menant à `/events/{id}/{onglet}?competition={c}` (sans quitter l'événement).
- **EVT-06** — Avec `?competition=`, seuls les matchs de cette compétition sont listés (Matchs et Terrains), sa puce est courante, le PDF des matchs est celui de cette compétition ; un code inconnu est ignoré.
- **EVT-07** — La vue événement propose les onglets Infos, Déroulement, Classement et Stats d'une compétition (la première par défaut), « Toutes » grisée sur ces onglets.
- **EVT-04** — Les matchs de toutes les compétitions de l'événement sont listés, chacun avec sa compétition.
- **EVT-05** — Événement inconnu ou sans compétition publiée → page 404 du site.
- **GRP-01** — `/groups/{s}/{c}` redirige (302) vers `/groups/{s}/{c}/games`.
- **GRP-02** — L'en-tête affiche le libellé du groupe (anglais sous `/en` s'il existe), la saison et le lien app2 `{app2}/group/{s}/{c}`.
- **GRP-03** — Une puce par compétition du groupe, menant à `/competitions/{s}/{c}/{onglet}` (sans paramètre `event`).
- **GRP-04** — Groupe inconnu ou sans compétition publiée pour la saison → page 404 du site.
- **GRP-05** — Un événement couvrant tout le groupe est mis en avant sous l'en-tête (même composant et même règle que CPL-11).
- **AGG-01** — Les vues événement et groupe partagent une seule page paramétrée par la portée et réutilisent les composants Matchs / Terrains de la page compétition (aucune copie).
- **AGG-02** — Les filtres `day` et `upcoming` fonctionnent comme sur la page compétition ; `gameday` est ignoré.
- **AGG-04** — Le lien PDF de la liste des matchs utilise `idEvenement` (événement) ou la liste des codes de compétition (groupe).
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
