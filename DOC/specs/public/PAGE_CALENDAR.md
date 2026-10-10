# Calendrier des compétitions et abonnements ICS

**Phase** : 3 — **Statut** : ✅ Validée (09/10/2026) — 🛠 Implémentée (à livrer) — **Routes** : `/calendar[?month=YYYY-MM][&section=][&group=]` (+ `/en`)
— **Remplace** : `kpcalendrier.php`, `json-events.php`, `upload_ics.php` — **Entrée de menu** : `calendar`

## 1. Objectif

Voir d'un coup d'œil quand et où se déroulent les compétitions (France et international), rejoindre leurs
résultats, et ajouter une compétition à son agenda personnel.

## 2. Contenu et comportement

1. `<h1>` « Calendrier des compétitions » ; mois affiché (par défaut le mois courant, Europe/Paris).
2. **Navigation** : flèches « Année précédente » `«` / « Mois précédent » `‹` / « Aujourd'hui » / « Mois suivant » `›` / « Année suivante » `»` (`?month=`), sans JavaScript, filtres conservés.
3. **Filtres** (formulaire GET) : **section** (Toutes, International, National, Régional, Tournoi, Continental, Divers — `kp_groupe.section`) et groupe de compétitions. Le menu des groupes ne propose que ceux de la section choisie (Divers inclus) ; changer de section remet le groupe à « Tous » et soumet le formulaire ; un groupe d'une autre section que celle demandée est ignoré.
4. **Agenda** : une ligne par journée, groupées par date de début (titre de groupe : date longue localisée) :
   dates de la journée/phase (« 12–14 juin », début → fin), **libellé comme dans le legacy : « {nom de la journée/phase} - {lieu} ({département}) »**
   (lien), puis pastille de section (couleur **et** texte) et nom de la compétition (`display_title`). Le libellé mène à la page de la
   compétition (`/competitions/{s}/{c}/games`, avec `?gameday=` pour un championnat) ; si la journée fait partie
   d'un événement, un second lien mène à `/events/{id}`.
5. **Vue mois** (à partir de `lg`, Q-P3-4) : grille lundi → dimanche ; chaque journée est **une barre à fond coloré léger** (couleur de la **section**, pas du niveau)
   **étalée du premier au dernier jour de la journée/phase**, à la ligne sur la semaine suivante si besoin, avec son libellé ;
   la liste reste la vue par défaut sur mobile et pour les lecteurs d'écran.
6. **Mois sans compétition** : « Aucune compétition publiée ce mois-ci. » + lien vers le mois suivant ayant des
   journées (s'il existe dans les 12 mois).
7. **Abonnement ICS** : sur la page de chaque compétition (onglet Infos, PAGE_COMPETITION.md § 3.3), bouton
   « S'abonner au calendrier » (`webcal://…/competition/{s}/{c}/calendar.ics`), lien « Télécharger (.ics) », champ en lecture seule + bouton
   **« Copier le lien d'abonnement »** (confirmation « Lien copié ») et un tutoriel repliable « Comment s'abonner ? » pour Google Agenda,
   Outlook et Apple Calendrier (les mises à jour des journées arrivent ensuite automatiquement). Chaque événement ICS porte le même libellé que le calendrier ;
   sur chaque journée de l'onglet Infos, lien « Ajouter à mon agenda » (`/gameday/{id}.ics`).

## 3. Données et cache

`GET /calendar?start&end[&section][&group]` et `GET /calendar/groups` (groupes à proposer, toutes sections) (API_PUBLIC_TRANSVERSE.md § 3.1), une période = le mois affiché
(semaines complètes en vue mois). `routeRules` : `cache: { maxAge: 300, swr: true }` sur `/calendar`.
Les fichiers ICS sont servis par api2 (URL publique), pas par app3.

## 4. SEO et accessibilité

- Titre « Calendrier {mois année} — kayak-polo.info » ; données structurées `SportsEvent` par journée (vue liste).
- Liste sémantique (`<ol>` par date), dates en `<time datetime>` ; grille du mois en `<table>` avec en-têtes.

## 5. Critères d'acceptation

- **CAL-01** — Sans paramètre, le mois courant (heure de Paris) ; `month=` invalide → mois courant.
- **CAL-02** — Les journées du mois sont listées par date de début, fusionnées par compétition, date et lieu, avec
  libellé legacy, section (couleur et texte) et dates de la journée localisées.
- **CAL-03** — Chaque ligne mène à la compétition (avec la journée pour un championnat) et, s'il existe, à
  l'événement.
- **CAL-04** — Navigation mois précédent / suivant / aujourd'hui et filtres section et groupe fonctionnent sans
  JavaScript (liens et formulaire GET).
- **CAL-05** — Mois vide → message et lien vers le prochain mois ayant des journées.
- **CAL-06** — Onglet Infos d'une compétition : abonnement `webcal://` et téléchargement `.ics` ; chaque journée
  propose son `.ics`.
- **CAL-08** — Des flèches mènent au mois et à l'année précédents / suivants en conservant les filtres.
- **CAL-09** — Le filtre « section » remplace le niveau ; les groupes proposés sont ceux de la section choisie ; un groupe étranger à la section est ignoré.
- **CAL-10** — L'abonnement propose de copier le lien et un tutoriel Google / Outlook / Apple.
- **CAL-07** — L'entrée de menu « Calendrier » devient interne (`ready: true`).

## 6. Décisions (09/10/2026)

- **Q-P3-4 — Vue mois.** ✅ Retenu : liste (agenda) d'abord, grille mensuelle sur grand écran, sans
  bibliothèque (pas de FullCalendar : poids, dépendance et accessibilité).

## 7. Correspondance des anciennes URL (phase 5)

`kpcalendrier.php[?lang=en]` → `/calendar` (`/en/calendar`).
