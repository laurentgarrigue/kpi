# Spécification - Scrutineering (contrôle des équipements) et profil 10 dans app4

## Statut : ✅ Validée (19/10/2026) — implémentée

**Origine** : migration de la page `/scrutineering` d'app2 (`pages/scrutineering.vue`, `components/ScrOverview.vue`,
`composables/useScrutineering.js`) vers app4, au côté de la feuille de présence équipe
([PAGE_PRESENCE.md](PAGE_PRESENCE.md)). Le scrutineering **et toute authentification** sont supprimés d'app2 (§ 8).

**Décisions** : profil dédié 10 plutôt qu'une capacité ; routes `/staff` existantes passées du jeton app2 au JWT
(pas de service commun) ; équipement modifiable compétition verrouillée ; profil 10 attribué par les profils 1–2
uniquement ; connexion au back-office legacy refusée au profil 10 ; `/report/game/{id}` (dernier utilisateur du
jeton app2) supprimé ; nation = 3 premiers caractères du code club ; copie, création de joueur et recherche par
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
| `/scrutineering` | Vue d'ensemble des équipes de l'événement | 1, 2, 10 (middleware `scrutineering`) |
| `/scrutineering/team/:teamId` | Contrôle équipement d'une équipe | 1, 2, 10 |
| `/presence/team/:teamId` | Feuille de présence équipe (existante) | existant + 10 |

Entrées : menu **Scrutineering** (profils 1, 2, 10 ; seule entrée du profil 10) et carte **Scrutineering** de la page
d'accueil. Le profil 10 garde la page d'accueil (sélecteur du contexte de travail, saison).

### 1.2 Navigation entre équipes

Passer **facilement** d'une équipe à l'autre **au sein du même événement**, et **encore plus facilement** d'une
équipe à l'autre du **même club** ou de la **même nation**. Composant `AdminScrutineeringTeamNav`, affiché sur les
deux écrans d'équipe pour les profils 1, 2 et 10 dès qu'un événement est sélectionné :

- **Bascule** Feuille de présence ⇄ Scrutineering, qui conserve l'équipe.
- **Sélecteur** de toutes les équipes de l'événement (compétitions autorisées), groupées par compétition et triées par
  club puis libellé. Sur la feuille de présence, il remplace le sélecteur des équipes de la compétition.
- **Même club** : une puce par autre équipe du club dans l'événement, toutes compétitions confondues.
- **Même nation** : une puce par autre équipe de la même nation qui n'est pas du même club.
- **Précédent / Suivant** : flèches et raccourcis clavier `←` / `→` (inactifs pendant la saisie et quand une fenêtre
  modale est ouverte), dans l'ordre du sélecteur.

**Nation d'une équipe** (`App\Scrutineering\TeamNation`) : même règle que les logos de nation (`MyTools.php`) :
`Code_club` de 4 caractères = club français → `FRA` ; sinon les 3 premiers caractères (ex. `GER`, `GER-W` → `GER`).

**Événement** : filtre d'événement de page d'app4 (`event:{id}`, partagé avec les autres pages), sinon l'événement du
contexte de travail. Les écrans de scrutineering ont leur propre sélecteur d'événement (événements de la saison du
contexte de travail). Sans événement, ils invitent à en choisir un ; la feuille de présence garde alors son sélecteur
d'équipes de la compétition (sauf pour le profil 10, qui n'a pas accès à la liste des équipes d'une compétition).

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
| Toutes les autres pages (compétitions, équipes, journées, matchs, feuilles de match, classements, stats, documents, clubs, athlètes, utilisateurs…) | ❌ |
| Back-office legacy (`/admin/Login.php`) | ❌ connexion refusée, message renvoyant vers admin2 |

- **Périmètre** : compétitions et saisons autorisées (profil ou mandat), événements autorisés (`Id_Evenement`).
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
| api2 autres lectures `ROLE_VIEWER` | 403 | 403 (inchangé), dont `GET /admin/competition-teams` |
| api2 écritures présence équipe | `niveau > 7` → 403 | garde commune : niveau ≤ 7 **ou** 10, compétition du périmètre (nouveau, pour tous), clubs (ignorés pour le 10) ; création de joueur ≤ 4 **ou** 10 |
| api2 présence match | > 7 → 403 | inchangé |
| api2 attribution du niveau 10 (utilisateur ou mandat) | profils 1 à 4 | **profils 1–2 uniquement** (`PROFILE_RESTRICTED`) |
| Mandats | — | un mandat de niveau 10 donne les droits du 10 ; un profil 10 sous mandat de niveau 5 a les droits du 5 |
| app4 menu / accueil | vides | entrée et carte **Scrutineering** |
| app4 permissions présence | `profile <= 7` / `<= 4` / `<= 2` | + profil 10 (`canWriteTeamPresence`, copie, création, recherche par licence) |
| app4 fenêtre utilisateur | niveau 10 proposé aux profils 1 à 4 | proposé aux profils 1–2 seulement |
| app4 libellé | `10 - (Inutilisé)` | **`10 - Contrôle équipement`** / `10 - Scrutineering` |
| app4 visites guidées | `welcome` (tous), `rankings` (≤ 4) | inchangé : `welcome` porte sur la page d'accueil, accessible au 10 |
| Legacy `Login.php` | connexion possible (menu Compétitions) | **refusée** |
| Données | — | avant la mise en production : `SELECT Code FROM kp_user WHERE Niveau = 10` et `SELECT id FROM kp_user_mandat WHERE niveau = 10` doivent être vides |

---

## 3. Écran « Contrôle équipement » (`/scrutineering/team/:teamId`)

Reprise du mode contrôle d'app2 dans la charte app4 (mode sombre, i18n FR/EN, cibles tactiles ≥ 44 px).

- **En-tête** : sélecteur d'événement, **Vue d'ensemble**, **Rafraîchir** (inactif 5 s après un clic), **PDF Contrôle**
  (`FeuilleControle.php?equipe=…`), puis la navigation du § 1.2, le logo, le nom, la compétition et le club. Un bandeau
  signale une compétition verrouillée.
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
(confirmation), qui efface les lignes `kp_scrutineering` de l'équipe.

---

## 4. Vue d'ensemble (`/scrutineering`)

Grille **Club × Catégorie** (catégorie = `Soustitre2`, à défaut le libellé de la compétition) de l'événement, sur les
compétitions autorisées. Statut par équipe, joueurs `E`, `A` et `X` exclus :

- **Complet** : tous les joueurs ont kayak = 1, gilet = 1, casque = 1 et pagaies > 0 ;
- **Partiel** : au moins un équipement contrôlé (> 0), pas tous complets ;
- **Non contrôlé** : rien de contrôlé (une ligne remise à 0 compte comme non contrôlée).

Un clic sur une équipe ouvre son contrôle. Légende et bouton Rafraîchir.

---

## 5. API (api2) — routes `/staff` en JWT

`StaffController` : firewall `api_staff` (`^/staff`, JWT), `#[IsGranted('ROLE_SCRUTINEER')]` au niveau de la classe
(sensible au mandat), `TokenAuthService` supprimé. L'événement doit faire partie de `getAllowedEvents()` (403 sinon) ;
toutes les requêtes sont restreintes aux saisons et compétitions autorisées ; une équipe hors de l'événement ou du
périmètre renvoie 404.

| Méthode | Route | Réponse |
|---|---|---|
| GET | `/staff/{eventId}/teams` | équipes : `team_id`, `label`, `club`, `club_label`, `nation`, `logo`, `competition_code`, `competition_label`, `season`, `locked` |
| GET | `/staff/{eventId}/team/{teamId}/players` | `{ team, players }` (sans `A` ni `X`) |
| PUT | `/staff/{eventId}/team/{teamId}/player/{playerId}/{parameter}/{value}` | `parameter` ∈ `kayak_status`, `vest_status`, `helmet_status` (0–4), `paddle_count` (0–6) ; 400 hors bornes ; 404 si le joueur n'est pas de la composition |
| PUT | `/staff/{eventId}/team/{teamId}/player/{playerId}/comment` | `{ comment }`, texte brut tronqué à 255 caractères |
| DELETE | `/staff/{eventId}/team/{teamId}/reset` | `{ reset, deleted }` |
| GET | `/staff/{eventId}/overview` | `{ categories, clubs, teams[status] }` |

Routes supprimées : composition d'app2 (`PUT …/composition/{id}`, `POST …/player`, `GET …/players/search`),
`GET /staff/test`, `POST /login`, `GET /me` (`AuthController`), `GET /report/game/{id}` (`ReportController`). La
table `kp_user_token` reste utilisée (réinitialisation de mot de passe, legacy).

### 5.1 Journal

| Action | `Actions` | `Journal` | Colonnes de contexte |
|---|---|---|---|
| Statut équipement | `Scrut équipement` | `Equipe {id} - Joueur {matric} - {kayak\|gilet\|casque\|pagaies}={valeur}` | `Evenements`, `Saisons`, `Competitions` |
| Commentaire | `Scrut commentaire` | `Equipe {id} - Joueur {matric}` | idem |
| Réinitialisation | `Scrut réinitialisation` | `Equipe {id} - réinitialisation complète` | idem |
| Feuille de présence | libellés existants (`Ajout titulaire`, …) | inchangés | `Saisons`, `Competitions` |

La « dernière mise à jour » de la feuille de présence ignore les actions `Scrut …`.

---

## 6. Feuille de présence modifiée par le profil 10

Pas de nouvel écran : la feuille de présence équipe est éditable par le profil 10 sur toute équipe de son périmètre,
avec les règles de validation existantes (éligibilité, numéro 0–99, statuts `- C E A X`), plus la navigation du § 1.2.
Le mode composition d'app2 n'est pas repris : la feuille de présence le couvre.

---

## 7. Critères d'acceptation

Tests : api2 `tests/Integration/StaffScrutineeringTest.php`, `PresenceScrutineerTest.php`,
`ScrutineerProfileAssignmentTest.php`, `tests/Unit/Scrutineering/*`, `tests/Unit/Security/UserRolesTest.php` ;
app4 `tests/unit/scrutineering.spec.ts`. Fixtures : événement 80, compétitions `SCA` / `SCB` (verrouillée), comptes
`TSCRUT`, `TSCRUTA`, `TDIV`, `TCLUB` ([SQL/fixtures](../../SQL/fixtures/README.md)).

**Droits**
- **ADM-SCR-01** — Le profil 10 n'a dans le menu que « Scrutineering » ; les routes `/admin` hors feuille de présence
  équipe lui renvoient 403 (`/admin/competition-teams`, `/admin/competitions`, présence match).
- **ADM-SCR-02** — Les profils 1, 2 et 10 accèdent au scrutineering ; un profil 3 à 9 reçoit 403 sur `/staff/*` et est
  redirigé depuis `/scrutineering`.
- **ADM-SCR-03** — `/staff/*` refuse le jeton app2 (`X-Auth-Token`) : 401.
- **ADM-SCR-04** — Événement hors périmètre : 403 ; équipe hors événement ou hors compétitions autorisées : 404 sur
  `/staff`, 403 sur la feuille de présence.
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
  (utilisateur, événement, saison, compétition, détail du § 5.1).

**Vue d'ensemble et navigation**
- **ADM-SCR-14** — Statuts Complet / Partiel / Non contrôlé selon le § 4, sur les compétitions autorisées.
- **ADM-SCR-15** — « Même club » liste les autres équipes du club dans l'événement ; un clic ouvre la même vue.
- **ADM-SCR-16** — « Même nation » liste les autres équipes de la nation (hors même club).
- **ADM-SCR-17** — Précédent / Suivant et `←` / `→` suivent l'ordre du sélecteur ; la bascule conserve l'équipe.

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
