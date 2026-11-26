# Spécification - Cumuls de cartons, suspensions et notifications

## Statut : 🚧 EN COURS (2026-11-26) — détection, notifications et brouillon de jury livrés ; décisions du 26/11 appliquées (§ 10) ; affichage à étudier (§ 8)

**Issue** : [laurentgarrigue/kpi#424](https://github.com/laurentgarrigue/kpi/issues/424)
**Décisions d'architecture** : [ADR-0004](../developer/reference/adr/0004-regles-metier-en-catalogues-api2.md) (catalogue en
code, versionné par saison), [ADR-0003](../developer/reference/adr/0003-designations-source-unique-absorbe-kp-rc.md)
(destinataires tirés des Désignations)
**Dépend de** : [FEATURE_DESIGNATIONS.md](FEATURE_DESIGNATIONS.md) (rôles `BUREAU_CNA`, `RC`, `DELEGUE_CNA` ; rapport de
jury d'appel)
**Vocabulaire** : [GLOSSARY.md](../../GLOSSARY.md) (Timeline, Cumul de cartons, Suspension, Journée sportive)
**Tickets** : `.scratch/cumuls-cartons/`

---

## 1. Objectif

Aucun outil ne repère aujourd'hui les cumuls de cartons prévus par l'article 50 du règlement (« Cartons et
suspensions »). KPI doit :

1. **détecter** dans la timeline des matchs validés les cartons qui déclenchent une suspension ;
2. en **déduire la suspension** (nombre de matchs, jury d'appel) selon le catalogue de la saison ;
3. **notifier** par e-mail le bureau CNA et les RC de la compétition concernée, une seule fois par suspension ;
4. quand la suspension relève du **jury d'appel**, ouvrir un **rapport de jury d'appel en brouillon** et le rappeler
   dans la notification.

KPI **constate** un cumul et le signale ; il ne prononce pas la sanction, qui reste à la commission et au jury d'appel.

## 2. Règles (art. 50) — catalogue `App\Discipline\SuspensionCatalog`

| Code | Situation | Suspension | Répétition |
|---|---|---|---|
| `GREEN_SEASON` | 8 cartons verts dans la saison | Match suivant | À chaque multiple du seuil (8e, 16e…) |
| `YELLOW_RED_GAMEDAY` | 2 cartons jaunes ou rouges dans des **matchs différents** d'une journée sportive | Match suivant | Puis chaque carton jaune ou rouge en plus dans cette journée |
| `YELLOW_RED_SEASON` | 4 cartons jaunes ou rouges dans la saison | Match suivant | À chaque multiple du seuil (4e, 8e…) |
| `BLACK_GAME` | 1 carton noir dans un match | 2 matchs + jury d'appel | Chaque carton noir |
| `BLACK_GAMEDAY` | 2 cartons noirs dans une journée sportive | 3 matchs + jury d'appel | Puis chaque carton noir en plus dans cette journée |
| `BLACK_SEASON` | 3 cartons noirs dans la saison | 4 matchs + jury d'appel | Puis chaque carton noir en plus dans la saison |

Codes de la timeline (`kp_match_detail.Id_evt_match`) : `V` vert, `J` jaune, `R` rouge, `D` noir.

Le « comportement grave » (4 matchs + jury) ne se lit pas dans la timeline : il reste hors détection.

Les cartons noirs entrent aussi dans les critères de départage des égalités, avant les rouges : ce n'est **pas** une
suspension, il est traité à part (§ 9).

## 3. Décisions

| # | Sujet | Décision |
|---|---|---|
| S1 | Catalogue | En code (ADR-0004), **versionné par saison** : `SuspensionCatalog::forSeason($saison)` rend la version en vigueur, ou rien avant la première saison couverte. app4 reçoit les règles par l'API. |
| S2 | Entrée en vigueur | La version 1 (tableau § 2) s'applique **à partir de la saison 2027**. Les seuils antérieurs (12 verts, 6 jaunes) ne sont pas repris : aucune détection avant 2027. |
| S3 | Périmètre du décompte « saison » | **Par section** de compétition (`kp_groupe.section` du groupe de la compétition) : un joueur a un compteur par section. |
| S4 | Sections couvertes | **Nationale (2)** seulement (décision du 2026-11-26). Les sections Régionale, International, Tournoi, Continental et Divers (et une compétition sans groupe) ne sont pas détectées ; ajouter une section, c'est une nouvelle version du catalogue. |
| S5 | Source des cartons | Timeline (`kp_match_detail`) des matchs **validés** (`kp_match.Validation = 'O'`), cartons portés sur une licence (`Competiteur > 0`), équipe A ou B. Joueurs et entraîneurs sont comptés de la même façon. |
| S6 | Journée sportive | Une journée (`kp_journee`) ; les phases d'une compétition de type CP de mêmes dates et lieu ne font qu'**une** journée sportive (même règle que SUI-07 des Désignations). |
| S7 | Ordre des cartons | Date du match, heure, n° d'ordre, id du match ; dans un match, ordre de saisie (`date_insert`, `Id`). C'est le carton qui atteint le seuil qui **déclenche**. |
| S8 | Plusieurs règles sur le même carton | Une **seule suspension par carton** : la règle au plus grand nombre de matchs est retenue (à égalité, l'ordre du catalogue), les autres règles déclenchées sont listées dans la notification. Jury d'appel si l'une d'elles le prévoit. Deux cartons différents donnent deux suspensions. |
| S9 | Destinataires | Membres du **bureau CNA** (Désignations `BUREAU_CNA` de la saison) et **RC de la compétition** du match déclencheur (Désignations `RC`, héritage compris), résolus **à la détection** depuis les Désignations, sans code à modifier quand ils changent. Sans destinataire joignable (aucun e-mail), l'adresse d'administration (`MAIL_ADMIN_TO`) reçoit la notification. |
| S10 | Une seule notification | Chaque suspension est enregistrée (`kp_card_suspension`) ; une suspension déjà enregistrée n'est jamais renotifiée. |
| S11 | Correction de la feuille | Quand un carton déclencheur disparaît ou ne déclenche plus la même suspension (match dévalidé, carton supprimé ou modifié, ordre changé), la suspension est **annulée** (conservée, datée) et ses destinataires reçoivent un e-mail d'annulation ; la nouvelle situation est détectée et notifiée comme une nouvelle suspension. |
| S12 | Jury d'appel | Une suspension « jury d'appel » ouvre un **rapport de jury d'appel en brouillon** (catalogue des rapports, type `JURY_APPEL`) sur l'événement de la journée du match, ou sur la journée hors événement. Auteur : le délégué CNA effectif de la journée (président du jury, D27), saisi par `KPI` ; sans délégué, seuls les profils 1–2 le reprennent. Motif, match concerné et faits sont pré-remplis ; la composition du jury l'est par le moteur des rapports. La notification rappelle le brouillon et donne son lien. Une suspension annulée ne supprime pas le rapport. |
| S13 | Déclenchement | Commande `app:card-suspensions:sync` lancée **par cron toutes les 10 minutes** sur la saison active (comme les médailles). `--season=AAAA` traite une autre saison ; `--silent` enregistre sans e-mail ni rapport (reprise d'une saison en cours lors de la mise en service). |
| S14 | Consultation | `GET /admin/card-suspensions?season=` (profils 1–2) : suspensions de la saison, actives et annulées, et règles de la version en vigueur. |

## 4. Données

Table `kp_card_suspension` (migration `SQL/migrations/2026-11-25_card_suspensions.sql`) :

| Colonne | Contenu |
|---|---|
| `id` | |
| `saison`, `section`, `matric` | Compteur concerné (S3) |
| `carton_id` | `kp_match_detail.Id` du carton déclencheur |
| `match_id`, `journee_id`, `code_competition` | Match déclencheur et son périmètre |
| `regle`, `regles` | Règle retenue ; toutes les règles déclenchées par ce carton (JSON, S8) |
| `version_regles` | Version du catalogue appliquée |
| `matchs`, `jury_appel` | Suspension retenue |
| `actif` | `1` pour la suspension en vigueur, `NULL` une fois annulée ; unicité `(carton_id, actif)` : une seule suspension active par carton |
| `detectee_le`, `notifiee_le`, `annulee_le` | Cycle de vie (S10, S11) ; `notifiee_le` reste vide en mode `--silent` |
| `destinataires` | Licences notifiées (JSON), reprises pour l'annulation |
| `rapport_id` | Brouillon de jury d'appel ouvert (S12) |

## 5. Notification

Objet : `KPI — Suspension : NOM Prénom (licence)`. Corps : joueur et licence ; compétition, journée sportive, match
(n°, date, équipes) ; carton déclencheur ; règle retenue et décompte (« 4e carton jaune ou rouge de la saison, section
Nationale ») ; suspension (« 1 match : le match suivant », « 2 matchs + jury d'appel ») ; autres règles déclenchées ;
pour un jury d'appel, rappel du rapport en brouillon et son lien app4 (`/reports/{id}`).

Annulation : `KPI — Suspension annulée : NOM Prénom (licence)`, avec le motif (feuille de match corrigée).

## 6. API

| Endpoint | Rôle |
|---|---|
| `GET /admin/card-suspensions?season=AAAA` | Profils 1–2. `{ season, catalog: { version, firstSeason, sections, rules[] } \| null, suspensions[] }` ; chaque suspension avec joueur, section, compétition, match, règle(s), matchs, jury, état (`ACTIVE`, `CANCELLED`), dates et rapport. |

## 7. Critères d'acceptation

Les tests portent le code du critère dans leur nom.

**Catalogue (unitaires, `SuspensionCatalogTest`)**

- **SUS-01** — Étant donné la saison 2026, alors aucun catalogue ; étant donné 2027 ou après, alors la version 1 avec
  les six règles du § 2.
- **SUS-02** — Le catalogue sérialisé (API) donne pour chaque règle son code, ses libellés, ses couleurs, son seuil, sa
  portée (match, journée, saison), ses matchs de suspension et le jury d'appel.

**Détection (unitaires, `SuspensionDetectorTest`)**

- **SUS-03** — Étant donné 7 cartons verts dans la saison, alors rien ; au 8e, une suspension `GREEN_SEASON` d'un
  match ; au 16e, une autre ; le 9e ne déclenche rien.
- **SUS-04** — Étant donné 2 cartons jaunes dans le **même** match d'une journée, alors rien ; un 3e carton jaune ou
  rouge dans un autre match de la journée déclenche `YELLOW_RED_GAMEDAY` ; un 4e dans la journée en déclenche une autre.
- **SUS-05** — Étant donné un carton jaune dans deux journées sportives différentes, alors pas de `YELLOW_RED_GAMEDAY`.
- **SUS-06** — Étant donné 4 cartons jaunes ou rouges dans la saison, dans 4 journées, alors le 4e déclenche
  `YELLOW_RED_SEASON`.
- **SUS-07** — Étant donné un carton noir, alors `BLACK_GAME` : 2 matchs + jury d'appel ; le 2e carton noir de la
  journée retient `BLACK_GAMEDAY` (3 matchs) et liste `BLACK_GAME` ; le 3e de la saison retient `BLACK_SEASON` (4 matchs).
- **SUS-08** — Étant donné 3 cartons jaunes en section Nationale et 4 en section Régionale, alors rien ; un 4e jaune
  en Nationale déclenche `YELLOW_RED_SEASON` (S3, S4).
- **SUS-09** — Étant donné un carton qui déclenche `YELLOW_RED_GAMEDAY` et `YELLOW_RED_SEASON`, alors une seule
  suspension d'un match, qui liste les deux règles (S8).
- **SUS-10** — Étant donné les phases d'un tournoi CP de mêmes dates et lieu, alors elles forment une seule journée
  sportive (S6).

**Synchronisation et notifications (intégration, `CardSuspensionsSyncTest`)**

- **SUS-11** — Seuls les matchs validés d'une section couverte comptent : un carton d'un match non validé ou d'une
  compétition de section Tournoi ne déclenche rien.
- **SUS-12** — Étant donné une suspension détectée, alors elle est enregistrée et notifiée une fois au bureau CNA et
  aux RC de la compétition ; une 2e synchronisation n'envoie rien.
- **SUS-13** — Étant donné une suspension notifiée dont le match est dévalidé, alors elle est annulée et ses
  destinataires reçoivent l'annulation.
- **SUS-14** — Étant donné un carton noir, alors un rapport `JURY_APPEL` en brouillon est créé sur l'événement de la
  journée, avec pour auteur le délégué CNA effectif, saisi par `KPI` ; le motif cite la suspension et le match ; la
  notification donne le lien du brouillon.
- **SUS-15** — Avec `--silent`, les suspensions sont enregistrées sans e-mail ni rapport, et ne sont pas notifiées
  ensuite.
- **SUS-16** — Sans destinataire désigné avec e-mail, la notification part à l'adresse d'administration.
- **SUS-17** — `GET /admin/card-suspensions` : profils 1–2 seulement (`403` sinon) ; rend le catalogue et les
  suspensions de la saison.

## 8. À étudier : afficher la suspension (feuille de présence, console de scoring)

Question ouverte de l'issue, **non implémentée**. Pistes :

| Option | Pour | Contre |
|---|---|---|
| A. Nouveau statut « suspendu » d'un joueur dans la **feuille de présence** (composition de l'équipe pour la journée), posé automatiquement à la détection et levé par un profil 1–2 | La feuille de présence est l'endroit où l'on choisit qui joue ; le responsable d'équipe le voit avant le match | Il faut savoir **quels** matchs purger (le « match suivant » dépend du calendrier et des forfaits) et qui lève la suspension |
| B. Alerte dans la **console de scoring** à l'ouverture du match (joueur inscrit et suspendu) | Le dernier contrôle avant la mise à l'eau | Trop tard pour remplacer le joueur ; la table de marque n'a pas à arbitrer une suspension |
| C. Les deux, A comme source et B en lecture seule | Cohérent | Coût le plus élevé |

Recommandation : **A, puis B en lecture seule** une fois A validé, la suspension modifiable par les profils 1–2
seulement. Préalable : décider comment compter les matchs purgés (matchs joués par l'équipe du joueur dans la même
section après le match déclencheur, forfaits compris ou non).

## 9. Hors périmètre

- **Départage des égalités par les cartons noirs** : les classements comptent déjà les cartons `V`, `J`, `R`, `D` par
  équipe (`AdminRankingsController`, requête des cartons pour les critères) ; vérifier que `D` passe avant `R` et que les
  critères sont réappliqués en cascade pour 3 équipes ou plus relève des classements (ticket à part).
- Sanction elle-même, purge effective, appel d'une suspension : décisions humaines, hors KPI.
- Notification du joueur ou du responsable d'équipe : non demandée.

## 10. Décisions du métier (2026-11-26)

Confirmés : répétition des seuils (§ 2), Nationale seule (S4), entrée en vigueur en 2027 (S2), non-cumul sur un même
carton (S8).

Cas où un même carton atteint plusieurs règles (S8) :

| Carton | Règles atteintes | Retenu | Sans non-cumul |
|---|---|---|---|
| Jaune ou rouge qui est à la fois le 2e match avec carton de la journée (ou un carton en plus) et un multiple de 4 sur la saison | `YELLOW_RED_GAMEDAY` + `YELLOW_RED_SEASON` | 1 match | 2 matchs |
| 2e noir de la journée (ou un noir en plus), moins de 3 noirs sur la saison | `BLACK_GAME` + `BLACK_GAMEDAY` | 3 matchs + jury | 5 matchs |
| 3e noir de la saison (ou un noir en plus), seul noir de sa journée | `BLACK_GAME` + `BLACK_SEASON` | 4 matchs + jury | 6 matchs |
| 3e noir de la saison (ou un noir en plus), 2e noir de sa journée (ou plus) | `BLACK_GAME` + `BLACK_GAMEDAY` + `BLACK_SEASON` | 4 matchs + jury | 9 matchs |

Un carton vert n'atteint jamais qu'une règle. Deux cartons différents donnent deux suspensions, qui s'ajoutent.

**Question ouverte** : sur deux cartons noirs de la même journée, KPI notifie aujourd'hui 2 matchs (1er noir) puis
3 matchs (2e noir), soit 5 matchs au total. Le règlement veut-il plutôt 3 matchs **au total** pour la journée (le 2e
noir porte la suspension de 2 à 3) ? Même question pour le 3e noir de la saison (4 matchs en plus, ou 4 au total).
