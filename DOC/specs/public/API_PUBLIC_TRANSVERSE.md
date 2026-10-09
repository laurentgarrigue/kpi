# api2 — endpoints publics transverses (phase 3)

**Phase** : 3 — **Statut** : ✅ Validée (09/10/2026) — 🛠 En cours — **Consommé par** : app3 (calendrier, historique, équipes, clubs,
recherche) — **Remplace** : `json-events.php`, `upload_ics.php`, requêtes de `kphistorique.php`,
`kpequipes.php`, `searchEquipes.php`, `kpclubs.php`, `json-clubs.php`, `searchClubs.php`, `kplogos.php`

## 1. Principes

Mêmes règles que la phase 2 ([API_PUBLIC_RESULTS.md](API_PUBLIC_RESULTS.md) § 4–5) :
- GET publics en lecture seule, dans des contrôleurs `Public*Controller` (tag OpenAPI « 7. Site public ») ;
- **publié seulement** (compétition, journée, match, événement `Publication = 'O'`) ; inconnu ou non publié → `404
  {"error":"not_found"}` ; paramètre invalide → `400 {"error":"invalid_parameter"}` ;
- **DTO publics** avec test de liste de champs ; règles legacy en un seul endroit (`CompetitionRules` : titre,
  médailles, rang par type) ; requêtes réutilisant `SqlFilters` ;
- données personnelles limitées à la stratégie § 11 : **jamais** de numéro de licence, date de naissance, sexe
  (hors libellé de catégorie), photo individuelle ni coordonnées personnelles ;
- `Cache-Control: public` : 300 s (calendrier, équipes, recherche), 3600 s (historique, clubs, ICS).

## 2. Endpoints

| Endpoint | Réponse | Remplace |
|---|---|---|
| `GET /calendar?start=&end=[&level=][&group=]` | journées publiées chevauchant la période (§ 3.1) ; `start`/`end` au format `YYYY-MM-DD`, période ≤ 400 jours | `json-events.php` |
| `GET /competition/{s}/{c}/calendar.ics` | abonnement iCalendar de la compétition : un événement par journée publiée (§ 3.2) | — *(nouveau)* |
| `GET /gameday/{id}.ics` | une journée publiée, en iCalendar | `upload_ics.php` |
| `GET /history` | groupes ayant au moins une compétition terminée du tour final, par section (format de `/groups/{season}`) | sélecteur de `kphistorique.php` |
| `GET /history/{group}` | palmarès multi-saisons du groupe (§ 3.3) | `kphistorique.php` |
| `GET /teams?q=` | recherche d'équipes, `q` ≥ 2 caractères, 20 résultats au plus (§ 3.4) | `searchEquipes.php` |
| `GET /team/{number}` | fiche d'une équipe : club, palmarès, saisons (§ 3.4) | `kpequipes.php` |
| `GET /team/{number}/roster/{season}/{code}` | composition d'une équipe dans une compétition, avec buts et cartons (§ 3.4) | `kpequipes.php` |
| `GET /clubs[?q=]` | clubs ayant au moins une équipe, avec logo et position (§ 3.5) | `json-clubs.php`, `searchClubs.php`, `kplogos.php` |
| `GET /club/{code}` | fiche d'un club : comités, site, adresse, position, équipes (§ 3.5) | `kpclubs.php` |
| `GET /search?q=` | recherche globale (§ 3.6) | — *(nouveau)* |

## 3. Formats

### 3.1 Calendrier
`[{ id, competition: { season, code, display_title, type, level, group }, name, place, department, start, end,
event }]` : une ligne par journée publiée de compétition publiée ; comme `json-events.php`, les journées d'une
même compétition **aux mêmes dates et lieu** (phases d'une coupe) sont fusionnées en une seule (`id` = la
première). `event` = `{ id, libelle }` du premier événement publié contenant la journée, sinon `null`.
Tri : `start`, `level` (INT, NAT, REG), `GroupOrder`, tour, nom. Filtres optionnels `level` (`INT|NAT|REG`) et
`group` (code de groupe).

### 3.2 iCalendar
`text/calendar; charset=utf-8`, RFC 5545 : `VCALENDAR` avec `X-WR-CALNAME` (titre de la compétition et
saison), un `VEVENT` par journée : `UID` stable (`gameday-{id}@kayak-polo.info`), `DTSTART;VALUE=DATE` /
`DTEND;VALUE=DATE` (jour suivant la fin), `SUMMARY` (« {compétition} — {journée} »), `LOCATION` (lieu,
département), `URL` (page de la compétition sur le site), `LAST-MODIFIED`. Lignes repliées à 75 octets,
caractères spéciaux échappés. Aucune donnée personnelle.

### 3.3 Historique
`{ group: { code, libelle, libelle_en }, seasons: [{ season, competitions: [{ code, display_title, soustitre2,
type, podium: [{ rank, team: { number, label, logo }, medal }] }] }] }` : compétitions **publiées, terminées
(`END`) et du tour final** du groupe, saisons décroissantes (règle de `kphistorique.php`). `podium` = équipes
classées (rang > 0) au rang propre au type, `medal` selon `CompetitionRules::medal`.

### 3.4 Équipes
- Recherche : `[{ number, label, club: { code, label } }]` (`kp_equipe`, libellé ou code club contenant `q`).
- Fiche : `{ number, label, club: { code, label }, logo, colors, photo, honours: [{ season, competition: { code, display_title,
  group }, rank, medal }], seasons: [{ season, competitions: [{ code, display_title }] }] }` ; `honours` = rangs
  dans les compétitions publiées **terminées** (comme `kpequipes.php`), saisons décroissantes.
  `colors` et `photo` = `{ image, season }` ou `null`, règle de `kpequipes.php` (Q-P3-3) : couleurs
  `KIP/colors/{n}-{année}-colors.png` de l'année courante à année − 3, puis `KIP/colors/{n}-colors.png`
  (`season` = `null`) ; photo d'équipe `KIP/teams/{n}-{année}-team.jpg` de l'année courante à année − 5.
- Composition : `{ players: [{ first_name, last_name, number, category, role, goals, green, yellow, red,
  red_final }] }` ; `role` ∈ `captain|coach|null` (`Capitaine` = C / E) ; joueurs `A` et `X` exclus ; buts et
  cartons des matchs **validés et publiés** ; tri legacy (joueurs, puis encadrement, numéro, nom). **Ni
  licence, ni sexe, ni date de naissance.**

### 3.5 Clubs
- Liste : `[{ code, label, department, logo, position }]` : `position` = `{ lat, lng }` depuis `kp_club.Coord`
  (`null` si absente ou invalide), `logo` = `KIP/logo/{code}-logo.png` s'il existe, sinon drapeau
  `Nations/{3 lettres}.png` s'il existe, sinon `null` (règle de `kpclassements.php`).
- Fiche : `{ code, label, department: { code, label }, region: { code, label }, www, email, postal, position,
  logo, teams: [{ number, label }] }`. Coordonnées **du club** (structure), jamais de personne (cf. Q-P3-2).

### 3.6 Recherche globale
`{ competitions: [...], events: [...], teams: [...], clubs: [...] }`, 8 résultats au plus par catégorie, `q` de 2
à 50 caractères (sinon `400`), recherche insensible à la casse et aux accents sur les libellés et codes :
- compétitions publiées (titre, code), **saison active d'abord**, puis les plus récentes ;
- événements publiés (libellé, lieu), les plus récents d'abord ;
- équipes et clubs comme § 3.4 / § 3.5.
**Aucune personne n'est indexée** (stratégie § 11). Limitation de débit : 30 requêtes par minute et par IP
(Symfony RateLimiter) → `429`.

## 4. Décisions (09/10/2026)

- **Q-P3-1 — Recherche : moteur.** ✅ Retenu : SQL `LIKE` sur les libellés (volumes faibles : quelques milliers
  de lignes), sans moteur dédié ; à revoir si les temps de réponse dépassent 200 ms.
- **Q-P3-2 — E-mail des clubs.** Publié aujourd'hui par `kpclubs.php` (adresse de la structure). ✅ Retenu :
  le conserver, affiché en texte (pas de `mailto:` indexable) ; à retirer si un club le demande.

## 5. Critères d'acceptation

- **API3-01** — `/calendar` renvoie les journées publiées chevauchant la période, fusionnées par compétition, date
  et lieu, triées ; filtres `level` et `group` ; période > 400 jours ou dates invalides → 400.
- **API3-02** — Les fichiers ICS sont valides RFC 5545 (testés par un analyseur), avec `UID` stables, dates en
  journée entière et aucune donnée personnelle.
- **API3-03** — `/history/{group}` ne contient que des compétitions publiées, `END` et du tour final, avec
  médailles ; `/history` liste les groupes concernés par section.
- **API3-04** — `/teams?q=` exige 2 caractères et renvoie 20 équipes au plus.
- **API3-05** — `/team/{number}` donne club, palmarès (compétitions terminées publiées) et saisons ; équipe
  inconnue → 404.
- **API3-06** — La composition ne contient ni licence, ni sexe, ni date de naissance ; buts et cartons des seuls
  matchs validés et publiés ; joueurs A / X exclus.
- **API3-07** — `/clubs` et `/club/{code}` exposent position et logo selon les règles § 3.5 ; club inconnu → 404.
- **API3-08** — `/search` cherche sans tenir compte des accents ni de la casse, borne chaque catégorie à 8, ne
  renvoie aucune personne, refuse `q` < 2 ou > 50 caractères et limite le débit (429).
- **API3-09** — Chaque DTO a un test listant exactement ses champs ; chaque endpoint est documenté sous « 7. Site
  public » ; chaque réponse porte son `Cache-Control`.
