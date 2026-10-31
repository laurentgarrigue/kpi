# Page d'accueil

**Phase** : 1 (version minimale) → 4a (version éditoriale) — **Statut** : ✅ Validée (08/10/2026, retours intégrés) ; rendez-vous élargis aux journées hors événement (15/10/2026)
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
2. **À venir** (avant « Récemment ») — *rubrique élargie le 15/10/2026 : « rendez-vous » = événements **et**
   journées de compétition hors événement*
   - Titre « À venir » / « Coming up ».
   - **Rendez-vous** de deux sortes, mêlés dans une seule liste :
     - les **événements** publiés ;
     - les **journées de compétition rattachées à aucun événement publié**, de toutes les sections, regroupées
       par **groupe de compétitions, dates et lieu** (les compétitions ou phases d'un même groupe jouées aux mêmes
       dates et au même lieu forment un seul rendez-vous).
   - Rendez-vous **en cours ou à venir** : date de fin ≥ aujourd'hui (Europe/Paris), triés par date de début
     **croissante** (le plus proche d'abord ; à date égale, l'événement avant le groupe), **9 au plus**.
   - Un rendez-vous en cours (début ≤ aujourd'hui ≤ fin) porte le badge « En cours ».
   - **Aucun** : « Aucun rendez-vous à venir pour le moment. »
3. **Récemment**
   - Titre « Récemment » / « Recently ».
   - Rendez-vous (mêmes deux sortes) **terminés** : date de fin < aujourd'hui, du plus récent au plus ancien
     (date de fin décroissante), **9 au plus**.
   - **Aucun** : « Aucun rendez-vous publié pour le moment. »
4. **Cartes** (même composant pour les deux sections) : logo s'il existe (`{legacy}/img/{logo}`, texte
   alternatif = nom, chargement différé), nom, puis **dates** (« 12–14 juin 2026 », localisées) **avant** le lieu ;
   à droite, un badge de catégorie « Événement » ou « Compétition » (et, dessous, « En cours » le cas échéant).
   - **Événement** : nom de l'événement ; la carte mène à `/events/{id}` (PAGE_EVENT_GROUP.md), même onglet.
   - **Groupe** : libellé du groupe dans la langue de la page (`libelle_en` en anglais, à défaut `libelle`),
     badge « Compétition », lieu (et département) ; logo de la compétition
     s'il est actif ; la carte mène à la page du groupe `/groups/{saison}/{code}`, même onglet.
5. **api2 indisponible** : un seul message « Les rendez-vous sont momentanément indisponibles. » à la place des
   deux sections. Le reste de la page est rendu normalement (statut HTTP 200).

La date du jour (Europe/Paris) et la répartition « à venir » / « récemment » sont calculées **par api2**
(`GET /fixtures/site`) et transmises avec les données : le navigateur ne recalcule rien à l'hydratation.

## 2 bis. Présentation (maquettes du 22/10/2026)

Remplace la disposition en cartes du § 2 ; les données et les règles (répartition par api2, 9 au plus, messages vides
ou d'indisponibilité, cartes événement / groupe) sont inchangées.

1. **Phrase de présentation** : reste l'unique `<h1>`, **en petit** (texte gris, graisse 600) ; plus de paragraphe ni
   de bouton « Compétitions et résultats » (l'entrée de menu suffit).
2. **Le prochain rendez-vous ouvre la page** (premier de « À venir ») : mention relative (« En ce moment » en rouge
   avec point clignotant, « Demain », « Ce week-end » si le rendez-vous commence un samedi ou un dimanche dans les 6
   jours, « Dans {n} jours »), nom en très grand (Agency FB), lieu et dates ; **dates en très grand** à droite
   (« 24–25 » sur « octobre », au-dessus du nom sur mobile). Les **journées de groupe aux mêmes dates** qu'un événement
   sont listées dessous (« Au programme : … », liens vers le groupe) et ne sont pas répétées dans l'agenda. Actions :
   en cours → « Suivre en direct » (app2, nouvel onglet) et « Matchs et résultats » ; sinon « Voir le programme » et,
   pour un événement, « Ajouter à mon agenda » (`/event/{id}/calendar.ics`).
3. **Deux colonnes** (une sur mobile) : à gauche **Actualités** — une à la une (image 2:1, titre, chapeau, date et
   catégorie) puis deux en vignettes — lien « Toutes les actualités » ; l'article à la une est le premier du bloc
   « à la une », sinon la dernière actualité. À droite **Agenda** : « Ensuite » (les autres rendez-vous à venir) et
   « Derniers résultats » (7 au plus), une ligne par rendez-vous (dates courtes en Agency FB, nom, lieu), lien
   « Calendrier complet ».
4. Les partenaires ferment la page ; l'alerte reste en tête.

## 3. Données

| Besoin | Endpoint | Champs utilisés |
|---|---|---|
| Rendez-vous | `GET /fixtures/site` (API_PUBLIC_TRANSVERSE.md § 3.7) | `today`, `upcoming`, `recent` |

- Un rendez-vous sans date de fin est traité avec sa date de début ; sans aucune date, il n'apparaît dans aucune
  section.
- La sélection, la fusion et la répartition sont faites **par api2**, une seule fois pour app3 et app2
  (§ 7) ; la fusion des deux sortes de rendez-vous est une fonction pure testée.
- `/events/{mode}` garde `start` / `end` (HOME-08) : la page « Événements » les utilise toujours.
- Cache : `routeRules` `cache: { maxAge: 300, swr: true }` sur `/` et `/en` (5 minutes, stale-while-revalidate).

## 4. SEO et accessibilité

- Titre : « Le kayak-polo, en France et à l'international — kayak-polo.info » (gabarit commun) ; description
  par défaut (SITE_LAYOUT.md § 5).
- Chaque section a un titre `<h2>` ; les cartes forment une liste (`<ul>`) ; chaque carte est un seul lien au
  libellé explicite, avec la mention « (nouvel onglet) ».

## 5. Critères d'acceptation

- **HOME-01** — La page affiche un unique `<h1>` traduit selon la langue.
- **HOME-02** — *Retiré le 22/10/2026 (maquettes) : « Compétitions et résultats » reste dans le menu.*
- **HOME-03** — « Récemment » : au plus 9 rendez-vous terminés, du plus récent au plus ancien.
- **HOME-04** — Une carte d'événement mène à `/events/{id}` (phase 2), affiche les dates et le logo seulement
  s'il est renseigné.
- **HOME-05** — Sections vides → message propre à chacune ; erreur api2 → message d'indisponibilité, page en 200.
- **HOME-06** — La répartition est faite par api2 avec la date du jour de son horloge (testée avec une date
  injectée) ; la fusion événements / groupes est une fonction pure testée.
- **HOME-07** — « À venir » précède « Récemment » : au plus 9 rendez-vous en cours ou à venir, du plus proche au
  plus lointain ; ceux en cours portent le badge « En cours ».
- **HOME-08** — `/events/{mode}` expose `start` et `end` sans modifier les autres champs (test d'intégration api2).
- **HOME-09** — Les journées publiées rattachées à aucun événement publié apparaissent, une carte par groupe,
  dates et lieu, avec le libellé du groupe dans la langue de la page et le badge « Compétition » ; la carte
  mène à `/groups/{saison}/{code}`. Une journée d'un événement publié n'apparaît que par son événement.
- **HOME-10** — app2 : sous un QR code réduit, la rubrique « À venir » (`GET /fixtures/app`) mène à
  `/event/{id}` ou `/group/{saison}/{code}` d'app2 (§ 7).

- **HOME-11** — Le prochain rendez-vous ouvre la page : mention relative (en cours, demain, ce week-end, dans n jours), nom, lieu, dates en grand ; en cours → « Suivre en direct » (app2, nouvel onglet).
- **HOME-12** — Les journées de groupe aux mêmes dates que le prochain événement sont listées sous lui (« Au programme ») et absentes de « Ensuite ».
- **HOME-13** — Actualités à gauche (une à la une puis deux), agenda à droite (« Ensuite », « Derniers résultats » 7 au plus, « Calendrier complet ») ; partenaires en dernier.

## 6. Évolutions prévues

- **Phase 2** (fait) : les cartes d'événement mènent à `/events/{id}` (PAGE_EVENT_GROUP.md § 8, HOME-04).
- **Phase 4a** : blocs éditoriaux administrables (à la une, dernières actualités, bandeau d'alerte, partenaires).

Ces évolutions mettront à jour cette spec avant implémentation.

## 7. Accueil d'app2 (15/10/2026)

Rubriques, dans l'ordre, sur une colonne centrée (6xl au plus), toutes en **cartes** au même style (fond `surface`,
bordure, ombre légère) et sous un **titre de rubrique unique** (icône + libellé, composant `HomeSection`) :
1. **Suivi** : carte de l'événement ou du groupe suivi (« Événement suivi » / « Compétition suivie », logo servi
   par le backend legacy `{backendBaseUrl}/img/{logo}`, nom, lieu ou saison, bouton « Changer ») ; sans
   sélection, « Que voulez-vous suivre ? » et le bouton de sélection. Le formulaire de sélection s'ouvre dans
   cette carte.
2. **Accès rapide** (si sélection) : trois tuiles Matchs / Progression / Équipe, avec une ligne d'explication.
3. **À venir** (même sans sélection, elle sert à en choisir une) : `GET /fixtures/app`, mêmes règles que le site
   mais avec les événements **activés pour app2** (`app = 'O'`, comme le sélecteur d'app2) ; une journée d'un
   événement non activé pour app2 compte comme hors événement ; seuls les groupes proposés par app2 (sections
   publiques, hors « Divers ») sont retenus. Grille de 1 à 3 colonnes ; mêmes cartes que le site (badge de
   catégorie à droite, dates avant le lieu) ; chaque carte mène à `/event/{id}` ou `/group/{saison}/{code}`
   d'app2, qui sélectionnent l'événement ou le groupe ; api2 indisponible ou liste vide : rubrique masquée.
4. **Partager** (1/3) et **Comment naviguer** (2/3) côte à côte sur grand écran (si sélection) : QR code de
   180 px cliquable (copie du lien) ; capture d'exemple (version bureau ou mobile) suivie de ses deux conseils.
