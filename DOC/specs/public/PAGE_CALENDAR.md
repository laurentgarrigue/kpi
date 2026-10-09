# Calendrier des compétitions et abonnements ICS

**Phase** : 3 — **Statut** : ✅ Validée (09/10/2026) — 🛠 Implémentée (à livrer) — **Routes** : `/calendar[?month=YYYY-MM][&level=][&group=]` (+ `/en`)
— **Remplace** : `kpcalendrier.php`, `json-events.php`, `upload_ics.php` — **Entrée de menu** : `calendar`

## 1. Objectif

Voir d'un coup d'œil quand et où se déroulent les compétitions (France et international), rejoindre leurs
résultats, et ajouter une compétition à son agenda personnel.

## 2. Contenu et comportement

1. `<h1>` « Calendrier des compétitions » ; mois affiché (par défaut le mois courant, Europe/Paris).
2. **Navigation** : liens « Mois précédent » / « Mois suivant » / « Aujourd'hui » (`?month=`), sans JavaScript.
3. **Filtres** (formulaire GET) : niveau (Tous, International, National, Régional) et groupe de compétitions.
4. **Agenda** : une ligne par journée, groupées par date de début (titre de groupe : date longue localisée) :
   dates (« 12–14 juin »), nom de la compétition (`display_title`), journée, lieu (département), pastille de
   niveau (couleur **et** texte : International / National / Régional). La ligne mène à la page de la
   compétition (`/competitions/{s}/{c}/games`, avec `?gameday=` pour un championnat) ; si la journée fait partie
   d'un événement, un second lien mène à `/events/{id}`.
5. **Vue mois** (à partir de `lg`, Q-P3-4) : grille lundi → dimanche ; chaque journée est **une barre à fond coloré léger** (couleur du niveau)
   **étalée sur autant de jours que nécessaire**, à la ligne sur la semaine suivante si besoin, avec son titre ;
   la liste reste la vue par défaut sur mobile et pour les lecteurs d'écran.
6. **Mois sans compétition** : « Aucune compétition publiée ce mois-ci. » + lien vers le mois suivant ayant des
   journées (s'il existe dans les 12 mois).
7. **Abonnement ICS** : sur la page de chaque compétition (onglet Infos, PAGE_COMPETITION.md § 3.3), bouton
   « S'abonner au calendrier » (`webcal://…/competition/{s}/{c}/calendar.ics`) et lien « Télécharger (.ics) » ;
   sur chaque journée de l'onglet Infos, lien « Ajouter à mon agenda » (`/gameday/{id}.ics`).

## 3. Données et cache

`GET /calendar?start&end[&level][&group]` (API_PUBLIC_TRANSVERSE.md § 3.1), une période = le mois affiché
(semaines complètes en vue mois). `routeRules` : `cache: { maxAge: 300, swr: true }` sur `/calendar`.
Les fichiers ICS sont servis par api2 (URL publique), pas par app3.

## 4. SEO et accessibilité

- Titre « Calendrier {mois année} — kayak-polo.info » ; données structurées `SportsEvent` par journée (vue liste).
- Liste sémantique (`<ol>` par date), dates en `<time datetime>` ; grille du mois en `<table>` avec en-têtes.

## 5. Critères d'acceptation

- **CAL-01** — Sans paramètre, le mois courant (heure de Paris) ; `month=` invalide → mois courant.
- **CAL-02** — Les journées du mois sont listées par date de début, fusionnées par compétition, date et lieu, avec
  niveau (couleur et texte), lieu et dates localisées.
- **CAL-03** — Chaque ligne mène à la compétition (avec la journée pour un championnat) et, s'il existe, à
  l'événement.
- **CAL-04** — Navigation mois précédent / suivant / aujourd'hui et filtres niveau et groupe fonctionnent sans
  JavaScript (liens et formulaire GET).
- **CAL-05** — Mois vide → message et lien vers le prochain mois ayant des journées.
- **CAL-06** — Onglet Infos d'une compétition : abonnement `webcal://` et téléchargement `.ics` ; chaque journée
  propose son `.ics`.
- **CAL-07** — L'entrée de menu « Calendrier » devient interne (`ready: true`).

## 6. Décisions (09/10/2026)

- **Q-P3-4 — Vue mois.** ✅ Retenu : liste (agenda) d'abord, grille mensuelle sur grand écran, sans
  bibliothèque (pas de FullCalendar : poids, dépendance et accessibilité).

## 7. Correspondance des anciennes URL (phase 5)

`kpcalendrier.php[?lang=en]` → `/calendar` (`/en/calendar`).
