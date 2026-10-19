# Spécification - Scrutineering (contrôle des équipements) et profil 10 dans app4

## Statut : ✅ Validée (19/10/2026) — implémentée, amendement « hors événement » compris

Amendement du 19/10/2026, validé et implémenté : le scrutineering s'applique aussi aux équipes d'une compétition
**hors événement** (§ 1.3, impacts et décisions au § 9).

**Origine** : migration de la page `/scrutineering` d'app2 (`pages/scrutineering.vue`, `components/ScrOverview.vue`,
`composables/useScrutineering.js`) vers app4, au côté de la feuille de présence équipe
([PAGE_PRESENCE.md](PAGE_PRESENCE.md)). Le scrutineering **et toute authentification** sont supprimés d'app2 (§ 8).

**Décisions** : profil dédié 10 plutôt qu'une capacité ; routes `/staff` existantes passées du jeton app2 au JWT
(pas de service commun) ; équipement modifiable compétition verrouillée ; profil 10 attribué par les profils 1–2
uniquement ; connexion au back-office legacy refusée au profil 10 ; `/report/game/{id}` (dernier utilisateur du
jeton app2) supprimé ; copie, création de joueur et recherche par
licence ouvertes au profil 10.

---

## 1. Vue d'ensemble

Le scrutineering est le contrôle de l'équipement des joueurs d'une équipe avant la compétition : kayak, gilet,
casque, nombre de pagaies valides et commentaire libre par joueur. Les données sont stockées dans
`kp_scrutineering` (clé `id_equipe` + `matric`), une ligne par joueur d'une composition
`kp_competition_equipe_joueur`.

Il est confié à un **profil dédié**, le **profil 10 « Contrôle équipement »** (auparavant « Inutilisé »), qui gère
**le scrutineering et les feuilles de présence de toutes les équipes** des compétitions autorisées. Les profils 1 et
2 ont les mêmes droits. Chaque action est tracée dans le journal (`kp_journal`).

### 1.1 Routes app4

| Route | Écran | Accès |
|---|---|---|
| `/scrutineering` | Vue d'ensemble des équipes du contexte (§ 1.3) : événement ou compétition | 1, 2, 10 (middleware `scrutineering`) |
| `/scrutineering/team/:teamId` | Contrôle équipement d'une équipe | 1, 2, 10 |
| `/presence/team/:teamId` | Feuille de présence équipe (existante) | existant + 10 |

Entrée **unique** : la page **Équipes** (`/teams`, [PAGE_EQUIPES.md](PAGE_EQUIPES.md)), pour les profils 1, 2 et 10
(profil effectif, mandat compris) : bouton **Scrutineering** de la barre d'outils (vue d'ensemble) et icône
**Scrutineering** sur chaque équipe (contrôle de l'équipe), que la compétition fasse partie d'un événement ou non. Ni entrée de menu, ni carte d'accueil. Le profil 10 a
dans le menu la seule page **Équipes**, en lecture seule, et garde la page d'accueil (contexte de travail, saison).
L'entrée de menu **Équipes** reste active sur `/scrutineering*` et `/presence/team/*`.

### 1.2 Navigation entre équipes

Passer **facilement** d'une équipe à l'autre **au sein du même contexte** (§ 1.3 : événement, sinon compétition), et
**encore plus facilement** d'une équipe à l'autre du **même club** (ou de la même sélection nationale, dont le code
club est la nation, ex. `GER`). Composant `AdminScrutineeringTeamNav`, affiché sur les deux écrans d'équipe pour les
profils 1, 2 et 10 :

- **Bascule** Feuille de présence ⇄ Scrutineering, qui conserve l'équipe.
- **Sélecteur** de toutes les équipes du contexte (compétitions autorisées), groupées par compétition et triées par
  club puis libellé. Sur la feuille de présence, il remplace le sélecteur des équipes de la compétition.
- **Même club / nation** : une puce par autre équipe du même code club dans le contexte (dans l'événement : toutes
  compétitions confondues).
- **Précédent / Suivant** : flèches et raccourcis clavier `←` / `→` (inactifs pendant la saisie et quand une fenêtre
  modale est ouverte), dans l'ordre du sélecteur.

### 1.3 Contexte : événement ou compétition

Le contrôle porte sur **une équipe** (`kp_scrutineering` est indexé par équipe) : il ne dépend d'aucun événement. Le
**contexte** ne sert qu'à la vue d'ensemble, à la navigation et au journal :

1. **Événement** : filtre « Événement / Groupe » de page d'app4 positionné sur un événement (`event:{id}`) ;
2. sinon **compétition** : compétition de page d'app4 (`pageCompetitionCode`) de la saison du contexte de travail ;
3. sinon, sur `/scrutineering`, invitation à choisir un événement ou une compétition. L'écran d'une équipe
   (`/scrutineering/team/:teamId`) reste utilisable sans contexte, sans navigation entre équipes.

Les écrans de scrutineering utilisent les **filtres communs** des pages d'app4 (`AdminPageHeader` :
`AdminEventGroupSelect` + `AdminCompetitionSingleSelect`, comme la page Équipes et la feuille de présence) : le
contexte est donc partagé avec ces pages, et la page Équipes ouvre la vue d'ensemble dans le contexte qu'elle affiche.
Un filtre de groupe vaut contexte « compétition ». Sur l'écran d'une équipe, comme sur la feuille de présence, le
filtre de compétition suit la compétition de l'équipe affichée ; le changer ouvre la première équipe de la compétition
choisie.

Une compétition **hors événement** est une compétition dont aucune journée n'est rattachée à un événement
(`kp_evenement_journee`) ; une compétition d'événement peut aussi être contrôlée dans le contexte « compétition ».

---

## 2. Profil 10 « Contrôle équipement »

### 2.1 Droits du profil 10

| Fonctionnalité | Profil 10 |
|---|---|
| Contexte de travail (saisons, compétitions, événements autorisés) | ✅ |
| Scrutineering : consulter, modifier équipement et commentaire, réinitialiser une équipe | ✅ toutes équipes |
| Vue d'ensemble scrutineering | ✅ |
| Feuille de présence équipe : consulter | ✅ |
| Feuille de présence équipe : n°, statut, ajout, suppression, copie depuis une autre compétition, création de joueur, recherche par licence | ✅ **toutes équipes**, sans restriction de club, compétition non verrouillée |
| Forçage d'un joueur non en règle | ❌ (inchangé : ≤ 2) |
| Éditions PDF (présence, contrôle) | ✅ |
| Page Équipes (`/teams`) : consulter, sans lien club ni PDF de compétition | ✅ lecture seule |
| Toutes les autres pages (compétitions, journées, matchs, feuilles de match, classements, stats, documents, clubs, athlètes, utilisateurs…) | ❌ |
| Back-office legacy (`/admin/Login.php`) | ❌ connexion refusée, message renvoyant vers admin2 |

- **Périmètre** : compétitions et saisons autorisées (profil ou mandat), événements autorisés (`Id_Evenement`). Une
  équipe est accessible si sa saison et sa compétition sont autorisées et, **quand le compte est limité à des
  événements**, si sa compétition a une journée dans l'un d'eux : un compte limité à des événements ne contrôle donc
  pas les compétitions hors événement (§ 9, point à valider n° 1).
  `Limit_clubs` est ignoré pour ce profil (`User::getAllowedClubs()` renvoie `null`).
- **Profils 1 et 2** : mêmes droits, sur toutes les compétitions. **Profils 3 à 9** : pas de scrutineering (le profil 3
  perd l'accès qu'il avait dans app2) ; leurs droits sur la feuille de présence sont inchangés.
- **Verrou** : une compétition verrouillée (`Verrou = 'O'`) bloque la feuille de présence ; le **contrôle équipement
  reste modifiable**.

### 2.2 Impacts du profil 10 sur les autres profils et fonctionnalités

Le profil 10 n'est **pas** dans la hiérarchie linéaire : il n'a aucun droit de consultation général, mais il écrit
les feuilles de présence de toutes les équipes, ce que les profils 8 et 9 ne font pas. Tous les contrôles
`niveau <= N` (N < 10) l'excluent ; il passe par des exceptions explicites.

| Zone | Avant | Après |
|---|---|---|
| api2 `User::getRoles()` | `ROLE_USER` seul | **`ROLE_SCRUTINEER`** ; `ROLE_ADMIN` en hérite (profils 1–2) |
| api2 consultation de la feuille de présence | `ROLE_VIEWER` | **`ROLE_PRESENCE_VIEWER`**, hérité par `ROLE_VIEWER` et `ROLE_SCRUTINEER` : `GET /admin/teams/{id}/players`, `GET /admin/players/search`, `GET /admin/competition-teams/{id}/compositions`, `GET /admin/operations/autocomplete/players` |
| api2 liste des équipes d'une compétition | `ROLE_VIEWER` | `ROLE_PRESENCE_VIEWER` : `GET /admin/competition-teams` (compétitions du périmètre) |
| api2 autres lectures `ROLE_VIEWER` | 403 | 403 (inchangé) |
| api2 écritures présence équipe | `niveau > 7` → 403 | garde commune : niveau ≤ 7 **ou** 10, compétition du périmètre (nouveau, pour tous), clubs (ignorés pour le 10) ; création de joueur ≤ 4 **ou** 10 |
| api2 présence match | > 7 → 403 | inchangé |
| api2 attribution du niveau 10 (utilisateur ou mandat) | profils 1 à 4 | **profils 1–2 uniquement** (`PROFILE_RESTRICTED`) |
| Mandats | — | un mandat de niveau 10 donne les droits du 10 ; un profil 10 sous mandat de niveau 5 a les droits du 5 |
| app4 menu / accueil | vides | entrée et carte **Équipes** (lecture seule) ; scrutineering depuis la page Équipes |
| app4 permissions présence | `profile <= 7` / `<= 4` / `<= 2` | + profil 10 (`canWriteTeamPresence`, copie, création, recherche par licence) |
| app4 fenêtre utilisateur | niveau 10 proposé aux profils 1 à 4 | proposé aux profils 1–2 seulement |
| app4 libellé | `10 - (Inutilisé)` | **`10 - Contrôle équipement`** / `10 - Scrutineering` |
| app4 visites guidées | `welcome` (tous), `rankings` (≤ 4) | inchangé : `welcome` porte sur la page d'accueil, accessible au 10 |
| Legacy `Login.php` | connexion possible (menu Compétitions) | **refusée** |
| Données | — | avant la mise en production : `SELECT Code FROM kp_user WHERE Niveau = 10` et `SELECT id FROM kp_user_mandat WHERE niveau = 10` doivent être vides |

---

## 3. Écran « Contrôle équipement » (`/scrutineering/team/:teamId`)

Reprise du mode contrôle d'app2 dans la charte app4 (mode sombre, i18n FR/EN, cibles tactiles ≥ 44 px).

- **En-tête** : filtres communs Événement / Groupe et Compétition (§ 1.3), puis une ligne avec la navigation du § 1.2
  (← sélecteur d'équipe →), **Rafraîchir** (inactif 5 s après un clic), **PDF Contrôle**
  (`FeuilleControle.php?equipe=…`) puis, à droite, **Vue d'ensemble** et la bascule **Feuille de présence** ; dessous, les puces « Même club / nation »,
  le logo, le nom, la compétition et le club. Sans navigation (pas de contexte), la ligne garde les trois boutons. Un
  bandeau signale une compétition verrouillée.
- **Commentaire** : la fenêtre d'édition place le curseur dans le champ texte à l'ouverture.
- **Tableau** : joueurs de la composition **sauf** arbitres (`A`) et inactifs (`X`), dans l'ordre de la feuille de
  présence. Le staff (`E`) apparaît sans contrôle.

| Colonne | Contenu | Clic |
|---|---|---|
| Joueur | numéro (ou « Coach »), nom prénom, badge `C` | — |
| Kayak / Gilet / Casque | icône de statut | 0 → 1 → 2 → 3 → 4 → 0 |
| Pagaies | nombre de pagaies valides | 0 → 1 → … → 6 → 0 |
| Commentaire | 25 premiers caractères | fenêtre d'édition (255 caractères) |

| Valeur | Libellé | Icône |
|---|---|---|
| 0 | Non contrôlé | presse-papiers, gris |
| 1 | Valide | check, vert |
| 2 | Défaut esthétique | exclamation (cercle), rouge |
| 3 | Défaut sécurité | exclamation (triangle), rouge |
| 4 | Défaut technique | bouclier, rouge |

Pied de tableau : **total des pagaies valides** (staff exclu). Dessous : **légende** et **Réinitialiser l'équipe**
(confirmation), qui efface les lignes `kp_scrutineering` de l'équipe, puis **« Dernière modification jj/mm/aaaa
hh:mm:ss par Prénom NOM »** (dernière action `Scrut …` de l'équipe), comme sur la feuille de présence (§ 5.1).

---

## 4. Vue d'ensemble (`/scrutineering`)

Grille **Club × Catégorie** (catégorie = `Soustitre2`, à défaut le libellé de la compétition) du contexte (§ 1.3), sur
les compétitions autorisées. Dans le contexte « compétition », la grille n'a qu'une colonne. Statut par équipe, joueurs `E`, `A` et `X` exclus :

- **Complet** : tous les joueurs ont kayak = 1, gilet = 1, casque = 1 et pagaies > 0 ;
- **Partiel** : au moins un équipement contrôlé (> 0), pas tous complets ;
- **Non contrôlé** : rien de contrôlé (une ligne remise à 0 compte comme non contrôlée).

Un clic sur une équipe ouvre son contrôle. Légende et bouton Rafraîchir.

---

## 5. API (api2) — routes `/staff` en JWT

`StaffController` : firewall `api_staff` (`^/staff`, JWT), `#[IsGranted('ROLE_SCRUTINEER')]` au niveau de la classe
(sensible au mandat), `TokenAuthService` supprimé. Les routes d'une **équipe** ne portent plus d'événement ; celles
d'un **contexte** prennent soit `event`, soit `season` + `competition` (400 si aucun ou les deux). Un événement hors
`getAllowedEvents()` ou une compétition / saison non autorisée : 403. Une équipe hors du périmètre du § 2.1 : 404.

| Méthode | Route | Réponse |
|---|---|---|
| GET | `/staff/teams?event={id}` ou `?season={s}&competition={c}` | équipes du contexte : `team_id`, `label`, `club`, `club_label`, `logo`, `competition_code`, `competition_label`, `season`, `locked` |
| GET | `/staff/overview?event={id}` ou `?season={s}&competition={c}` | `{ categories, clubs, teams[status] }` |
| GET | `/staff/team/{teamId}/players` | `{ team, players, lastUpdate }` (sans `A` ni `X`) |
| PUT | `/staff/team/{teamId}/player/{playerId}/{parameter}/{value}` | `{ value, lastUpdate }` ; `parameter` ∈ `kayak_status`, `vest_status`, `helmet_status` (0–4), `paddle_count` (0–6) ; 400 hors bornes ; 404 si le joueur n'est pas de la composition |
| PUT | `/staff/team/{teamId}/player/{playerId}/comment` | `{ comment, lastUpdate }`, texte brut tronqué à 255 caractères |
| DELETE | `/staff/team/{teamId}/reset` | `{ reset, deleted }` |

Les écritures acceptent un paramètre facultatif `?event={id}` (événement du contexte d'app4), contrôlé comme
ci-dessus et vérifié contre l'équipe (sa compétition a une journée dans cet événement, 400 sinon), qui alimente la
colonne `Evenements` du journal.

Routes supprimées : composition d'app2 (`PUT …/composition/{id}`, `POST …/player`, `GET …/players/search`),
`GET /staff/test`, `POST /login`, `GET /me` (`AuthController`), `GET /report/game/{id}` (`ReportController`). La
table `kp_user_token` reste utilisée (réinitialisation de mot de passe, legacy).

### 5.1 Journal

| Action | `Actions` | `Journal` | Colonnes de contexte |
|---|---|---|---|
| Statut équipement | `Scrut équipement` | `Equipe {id} - Joueur {matric} - {kayak\|gilet\|casque\|pagaies}={valeur}` | `Saisons`, `Competitions` ; `Evenements` si l'action est faite dans le contexte d'un événement (sinon vide) |
| Commentaire | `Scrut commentaire` | `Equipe {id} - Joueur {matric}` | idem |
| Réinitialisation | `Scrut réinitialisation` | `Equipe {id} - réinitialisation complète` | idem |
| Feuille de présence | libellés existants (`Ajout titulaire`, …) | inchangés | `Saisons`, `Competitions` |

« Dernière modification » (`App\Service\TeamLastUpdateService`, `lastUpdate: { date, user, action }`) : la feuille de
présence ignore les actions `Scrut …`, le scrutineering ne retient qu'elles. `user` = « Prénom NOM » de la licence de
l'utilisateur, à défaut son identité (`kp_user.Identite`), à défaut son identifiant.

---

## 6. Feuille de présence modifiée par le profil 10

Pas de nouvel écran : la feuille de présence équipe est éditable par le profil 10 sur toute équipe de son périmètre,
avec les règles de validation existantes (éligibilité, numéro 0–99, statuts `- C E A X`), plus la navigation du § 1.2.
Le mode composition d'app2 n'est pas repris : la feuille de présence le couvre.

---

## 7. Critères d'acceptation

Tests : api2 `tests/Integration/StaffScrutineeringTest.php`, `PresenceScrutineerTest.php`,
`ScrutineerProfileAssignmentTest.php`, `tests/Unit/Scrutineering/*`, `tests/Unit/Service/TeamLastUpdateServiceTest.php`, `tests/Unit/Security/UserRolesTest.php` ;
app4 `tests/unit/scrutineering.spec.ts`. Fixtures : événement 80, compétitions `SCA` / `SCB` (verrouillée), **`SCC`
(hors événement, à ajouter)**, comptes `TSCRUT` (limité à l'événement 80, `SCC` à ajouter à ses compétitions),
`TSCRUTA` (sans limite d'événement, `SCC` à ajouter), `TDIV`, `TCLUB` ([SQL/fixtures](../../SQL/fixtures/README.md)).

**Droits**
- **ADM-SCR-01** — Le profil 10 n'a dans le menu que « Équipes », en lecture seule, sur ses compétitions ; les autres
  routes `/admin` hors feuille de présence équipe lui renvoient 403 (`/admin/clubs`, `/admin/competitions`, présence
  match). Le scrutineering n'a ni entrée de menu ni carte d'accueil : bouton et icône par équipe de la page Équipes,
  affichés aux seuls profils 1, 2 et 10.
- **ADM-SCR-02** — Les profils 1, 2 et 10 accèdent au scrutineering ; un profil 3 à 9 reçoit 403 sur `/staff/*` et est
  redirigé depuis `/scrutineering`.
- **ADM-SCR-03** — `/staff/*` refuse le jeton app2 (`X-Auth-Token`) : 401.
- **ADM-SCR-04** — Événement, saison ou compétition de contexte hors périmètre : 403 ; équipe hors compétitions
  autorisées, ou hors des événements autorisés d'un compte limité à des événements : 404 sur `/staff`, 403 sur la
  feuille de présence.
- **ADM-SCR-05** — Le profil 10 consulte et modifie la feuille de présence de toute équipe d'une compétition
  autorisée, quel que soit son club : n°, ajout, création, suppression, copie, recherche.
- **ADM-SCR-06** — Compétition verrouillée : feuille de présence en lecture seule, équipement modifiable.
- **ADM-SCR-07** — Le mandat prime : profil 10 sous mandat 5 = droits du 5 ; mandat 10 = droits du 10.

**Contrôle équipement**
- **ADM-SCR-08** — Kayak, Gilet, Casque : 0→1→2→3→4→0 ; Pagaies : 0→…→6→0 ; valeur persistée ; hors bornes : 400.
- **ADM-SCR-09** — Total des pagaies valides = somme hors staff.
- **ADM-SCR-10** — Arbitres et inactifs absents ; staff sans contrôle.
- **ADM-SCR-11** — Commentaire en texte brut, 255 caractères au plus, affiché tronqué à 25.
- **ADM-SCR-12** — « Réinitialiser l'équipe » demande confirmation puis efface tous les contrôles de l'équipe.
- **ADM-SCR-13** — Chaque modification d'équipement, commentaire et réinitialisation crée une ligne `kp_journal`
  (utilisateur, saison, compétition, événement du contexte s'il y en a un, détail du § 5.1).

**Vue d'ensemble et navigation**
- **ADM-SCR-14** — Statuts Complet / Partiel / Non contrôlé selon le § 4, sur les compétitions autorisées.
- **ADM-SCR-15** — « Même club / nation » liste les autres équipes du même code club dans le contexte ; un clic ouvre
  la même vue. Il n'y a pas de rubrique « Même nation ».
- **ADM-SCR-16** — Le contrôle équipement affiche « Dernière modification … par Prénom NOM » de la dernière action
  `Scrut …` de l'équipe, mis à jour après chaque modification ; la feuille de présence affiche aussi « Prénom NOM » et
  ignore les actions `Scrut …`. L'ouverture du commentaire place le curseur dans le champ texte.
- **ADM-SCR-17** — Précédent / Suivant et `←` / `→` suivent l'ordre du sélecteur ; la bascule conserve l'équipe.

**Hors événement**
- **ADM-SCR-22** — Une équipe d'une compétition hors événement (`SCC`) se contrôle comme les autres (équipement,
  commentaire, réinitialisation, journal sans événement) par un profil 1–2 ou un profil 10 sans limite d'événement ;
  un profil 10 limité à des événements reçoit 404.
- **ADM-SCR-23** — Sans événement, la vue d'ensemble et la navigation portent sur la compétition de page ; depuis la
  page Équipes, le bouton Scrutineering ouvre la vue d'ensemble de la compétition affichée, l'icône d'une équipe son
  contrôle, avec navigation entre les équipes de la compétition.

**Profil 10 et nettoyage**
- **ADM-SCR-18** — app2 n'a plus de `/login` ni de `/scrutineering` et n'envoie plus de jeton.
- **ADM-SCR-19** — Libellé « 10 - Contrôle équipement » ; `DROITS_PAR_PROFIL.md` et `PROFILE_ROLES.md` à jour.
- **ADM-SCR-20** — Seuls les profils 1–2 attribuent le niveau 10 (utilisateur ou mandat) ; sinon 403
  `PROFILE_RESTRICTED`.
- **ADM-SCR-21** — Le profil 10 ne peut pas se connecter au back-office legacy.

---

## 8. Suppression dans app2

Supprimés : `pages/scrutineering.vue`, `pages/login.vue`, `components/ScrOverview.vue`, `components/CommentModal.vue`,
`components/TeamSelector.vue`, `composables/useScrutineering.js`, `stores/scrutineeringStore.js`,
`composables/useAuth.js`, `composables/useUser.js`, `composables/useStatus.js`. `useApi.js` n'envoie plus de jeton et
ne redirige plus vers `/login` (`postApi` et `getToken` supprimés) ; `/login` retiré de `event-guard`, `/scrutineering`
de l'ordre de navigation ; préférences `scr_*` et clés i18n `Login.*`, `Scrutineering.*` retirées.

---

## 9. Amendement « hors événement » : impacts et points à valider

### 9.1 Impacts

| Brique | Impact |
|---|---|
| Données | Aucun : `kp_scrutineering` est déjà indexé par équipe. Pas de migration. |
| api2 `StaffController` | Routes d'équipe sans `{eventId}` ; routes de contexte `teams` / `overview` en `?event=` ou `?season=&competition=` ; périmètre réécrit (compétition + saison, condition d'événement pour les comptes limités) ; contrôle d'appartenance de l'équipe à l'événement retiré des routes d'équipe ; `Evenements` du journal alimenté par le paramètre facultatif `event`. Changement d'URL **cassant** : app4 et api2 doivent partir dans la même livraison (aucun autre client depuis la suppression du scrutineering d'app2). |
| api2 tests | `StaffScrutineeringTest` à adapter aux nouvelles routes ; nouveaux cas ADM-SCR-22 et 23. Fixtures : compétition `SCC` sans journée d'événement, avec équipe et joueurs ; comptes `TSCRUT` / `TSCRUTA` étendus à `SCC`. |
| app4 contexte | `resolveEventId` remplacé par `resolveContext` (`{ event } \| { season, competition } \| null`), `contextParams`, `contextEventId` ; `useScrutineeringNavigation` (`context`, `goToCompetition`) et `useScrutineeringApi` le prennent en paramètre. |
| app4 écrans | `/scrutineering` et l'écran d'équipe : filtres communs d'`AdminPageHeader` à la place du sélecteur d'événement propre au scrutineering (`AdminScrutineeringEventSelect`, supprimé) ; l'écran d'équipe fonctionne sans contexte. Vue d'ensemble à une colonne en contexte compétition. |
| app4 page Équipes | Le bouton Scrutineering ouvre la vue d'ensemble de la compétition affichée (ou de l'événement filtré). |
| app4 feuille de présence | La navigation (§ 1.2) apparaît aussi sans événement, sur la compétition. Le profil 10 lisant désormais la liste des équipes d'une compétition, l'exception « sauf pour le profil 10 » disparaît. |
| Legacy | Aucun : `FeuilleControle.php` prend `equipe`, `compet`, `season`, sans événement. |
| Journal / « Dernière modification » | Aucun impact sur `TeamLastUpdateService` (recherche par équipe). Colonne `Evenements` vide hors événement : la page Journal affiche ces actions sans événement. |
| Droits | Les profils 1–2 contrôlent toute équipe. Pour le profil 10, cela dépend du point n° 1. |
| Docs | `PAGE_EQUIPES.md` (cible du bouton), `QA_APP4_PROFILES.md` (cas hors événement), `DROITS_PAR_PROFIL.md` (note du profil 10). |

### 9.2 Décisions (validées le 19/10/2026)

1. **Compte profil 10 limité à des événements** (`Id_Evenement` renseigné) : il **ne** contrôle **pas** les
   compétitions hors événement (moindre privilège). Le filtre de compétitions d'un compte est souvent vide, ce qui veut
   dire « toutes » : sans cette règle, un contrôleur limité à un événement contrôlerait toutes les équipes de la saison.
   Dans le contexte d'une telle compétition, la liste des équipes est vide.
2. **Journal** : `Evenements` renseigné seulement quand l'action est faite dans le contexte d'un événement qui contient
   l'équipe.
3. **Événement filtré et compétition sélectionnée** (cas normal des filtres communs) : l'événement l'emporte. Pour
   passer en contexte « compétition », on retire le filtre d'événement.

