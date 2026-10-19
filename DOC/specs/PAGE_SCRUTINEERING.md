# Spécification - Scrutineering (contrôle des équipements) et profil 10 dans app4

## Statut : 📝 Brouillon v2 — à valider avant implémentation

**Origine** : migration de la page `/scrutineering` d'app2 (`pages/scrutineering.vue`, `components/ScrOverview.vue`,
`composables/useScrutineering.js`) vers app4, au côté de la feuille de présence équipe
([PAGE_PRESENCE.md](PAGE_PRESENCE.md)). Une fois la migration livrée, le scrutineering **et toute authentification**
sont supprimés d'app2 (§ 8).

---

## 1. Vue d'ensemble

Le scrutineering est le contrôle de l'équipement des joueurs d'une équipe avant la compétition : kayak, gilet,
casque, nombre de pagaies valides et commentaire libre par joueur. Les données sont stockées dans
`kp_scrutineering` (clé `id_equipe` + `matric`), une ligne par joueur d'une composition
`kp_competition_equipe_joueur`.

Il est confié à un **profil dédié**, le **profil 10 « Contrôle équipement »** (aujourd'hui « Inutilisé »), qui gère
**le scrutineering et les feuilles de présence de toutes les équipes** des compétitions autorisées. Les profils 1 et
2 ont les mêmes droits. Chaque action est tracée dans le journal (`kp_journal`).

### 1.1 Routes app4

| Route | Écran | Accès |
|---|---|---|
| `/scrutineering` | Vue d'ensemble des équipes de l'événement du contexte de travail | 1, 2, 10 |
| `/scrutineering/team/:teamId` | Contrôle équipement d'une équipe | 1, 2, 10 |
| `/presence/team/:teamId` | Feuille de présence équipe (existante) | existant + 10 |

### 1.2 Navigation entre équipes

Exigence : passer **facilement** d'une équipe à l'autre **au sein du même événement**, et **encore plus
facilement** d'une équipe à l'autre du **même club** ou de la **même nation**. Elle s'applique aux deux écrans
d'équipe (scrutineering et feuille de présence) :

- **Bascule** Feuille de présence ⇄ Scrutineering sur la même équipe : un bouton dans l'en-tête de chaque écran,
  qui conserve l'équipe.
- **Sélecteur d'équipe de l'événement** : liste déroulante avec recherche de **toutes les équipes de l'événement**
  (filtrées sur les compétitions autorisées), groupées par compétition et triées par club puis libellé.
- **Barre « même club / même nation »** sous l'en-tête : une puce par autre équipe du même club dans
  l'événement, sur toutes les compétitions (ex. U18 H, U21 F, Senior H). Un clic ouvre la même vue pour cette équipe.
  Pour une équipe internationale, la barre liste les équipes de la même nation.
- **Précédent / Suivant** : flèches (et raccourcis clavier `←` / `→`) qui parcourent les équipes de l'événement
  dans l'ordre du sélecteur.
- Navigation par URL, sans rechargement de page, et l'onglet courant (présence ou scrutineering) est conservé.

**Nation d'une équipe** : même règle que les logos de nation (`MyTools.php`, `AdminTeamsController`) :
`Code_club` de 4 caractères = club français → nation `FRA` ; sinon les 3 premiers caractères de `Code_club`
(ex. `GER`, `GERW` → `GER`). Une équipe française a donc pour « nation » toutes les équipes françaises de
l'événement. La barre affiche **le club**, et **la nation** seulement si elle apporte des équipes en plus.

**Contexte** : l'événement vient du contexte de travail app4 (sélection « événement »). Sans événement
sélectionné, `/scrutineering` invite à en choisir un. Une feuille de présence ouverte hors événement n'affiche que
les équipes de la même compétition.

---

## 2. Profil 10 « Contrôle équipement »

### 2.1 Droits du profil 10

| Fonctionnalité | Profil 10 |
|---|---|
| Contexte de travail (saisons, compétitions, événements autorisés) | ✅ |
| Scrutineering : consulter, modifier équipement et commentaire, réinitialiser une équipe | ✅ toutes équipes |
| Vue d'ensemble scrutineering | ✅ |
| Feuille de présence équipe : consulter | ✅ |
| Feuille de présence équipe : n°, statut, ajout de joueur existant, suppression | ✅ **toutes équipes**, sans restriction de club, si compétition non verrouillée |
| Feuille de présence équipe : copie depuis une autre compétition, création de joueur, recherche par licence, forçage d'un joueur non en règle | ❌ (inchangé : ≤ 4 / ≤ 2) |
| Éditions PDF de la feuille de présence (FR, EN, photo, visa, contrôle) | ✅ |
| Toutes les autres pages (compétitions, équipes, journées, matchs, feuilles de match, classements, stats, documents, clubs, athlètes, utilisateurs…) | ❌ |

- **Périmètre** : les compétitions autorisées (`Filtre_competition`, `Filtre_saison`, `Id_Evenement` du profil ou du
  mandat), comme pour les autres profils. `Limit_clubs` n'a pas de sens pour ce profil : il est ignoré.
- **Profils 1 et 2** : mêmes droits sur toutes les compétitions (bypass de périmètre existant).
- **Autres profils (3 à 9)** : **pas** de scrutineering. Ils gardent leurs droits actuels sur la feuille de présence.
  Le profil 3 perd donc l'accès au scrutineering qu'il avait dans app2.
- **Verrou** : une compétition verrouillée (`Verrou = 'O'`) bloque la feuille de présence. Le **contrôle équipement
  reste modifiable**.

### 2.2 Impacts du profil 10 sur les autres profils et fonctionnalités

Le profil 10 n'est **pas** dans la hiérarchie linéaire « plus le numéro est petit, plus on a de droits ». C'est un
profil **spécialisé** : il n'a aucun droit de consultation général, mais il a un droit d'écriture (feuilles de
présence de toutes les équipes) que les profils 8 et 9 n'ont pas. Tous les contrôles `niveau <= N` (N < 10)
l'excluent donc automatiquement. Il faut ajouter des exceptions explicites là où il doit passer.

| Zone | Comportement actuel pour le niveau 10 | Changement |
|---|---|---|
| api2 `User::getRoles()` | `default => ROLE_USER` (aucun rôle métier) | Nouveau rôle **`ROLE_SCRUTINEER`** pour le niveau 10 (`role_hierarchy: ROLE_SCRUTINEER: [ROLE_USER]`), et `ROLE_ADMIN` hérite de `ROLE_SCRUTINEER` (profils 1–2) |
| api2 lectures `#[IsGranted('ROLE_VIEWER')]` (32 routes) | 403 | Inchangé, sauf `GET /admin/teams/{id}/players` et `GET /admin/players/search`, ouverts aussi à `ROLE_SCRUTINEER` |
| api2 écritures présence équipe (`getEffectiveNiveau() > 7` → 403) | 403 | Autorisées pour le niveau 10, avec la restriction de club levée. `canEdit` est renvoyé à `true` si la compétition n'est pas verrouillée |
| api2 présence match (`/admin/matches/*/players`) | 403 (> 7) | Inchangé (hors périmètre) |
| api2 `AdminRankingsController::justification` (`> 10`) | Passe le contrôle interne mais est bloqué par `ROLE_VIEWER` | Inchangé (déjà inaccessible) |
| api2 `/admin/filters/*` (contexte de travail) | Accessible (ROLE_USER) | Inchangé |
| api2 `AdminUsersController::validateProfileAssignment` | Profils 1, 2, 3 et 4 peuvent attribuer le niveau 10 | **Q1** : un profil 3 ou 4 pourrait créer un profil 10 doté d'un droit (scrutineering) qu'il n'a pas lui-même |
| api2 `validateProfileFilters` (niveau ≥ 3 : saison et compétition obligatoires) | S'applique | Inchangé : un profil 10 a un périmètre de compétitions |
| Mandats | Un mandat de niveau 10 est possible | Un mandat de niveau 10 donne les droits du profil 10. Un profil 10 avec un mandat de niveau 5 prend les droits du 5 et perd le scrutineering |
| app4 menu (`Header.vue`) | Menu vide (tout est en `profile <= 8` ou `<= 9`) | Entrée **Scrutineering** pour 1, 2, 10. Les autres entrées restent masquées pour le 10 |
| app4 page d'accueil | Raccourcis masqués (`hasProfile(8)`) | Le profil 10 est redirigé vers `/scrutineering` après connexion |
| app4 `usePresencePermissions` (`canView: profile <= 10`) | Lecture seule | `canEdit` aussi pour le profil 10 (tous clubs). `canCopy` / `canCreatePlayer` / `canSearchLicense` inchangés |
| app4 `/teams` (accès normal à la feuille de présence) | Masqué (≤ 8) | Inchangé : le profil 10 y accède par `/scrutineering` et la navigation du § 1.2 |
| app4 visites guidées (`maxProfile` absent = tous) | Les visites des pages inaccessibles apparaissent | Masquer pour le profil 10 les visites des pages qu'il ne voit pas |
| app4 utilisateurs (liste des profils, i18n `10 - (Inutilisé)`) | Libellé « Inutilisé » | Libellé **« 10 - Contrôle équipement »** (FR) / **« 10 - Scrutineering »** (EN) |
| Legacy PHP (`MyPage.php`, `GestionCompetition.php`, `<= 10`) | Un profil 10 qui se connecte au legacy voit le menu Compétitions et peut changer de saison | **Q2** : laisser tel quel (lecture seule) ou refuser la connexion legacy au profil 10 |
| Legacy `GestionEquipeJoueur.php` (feuille de présence legacy) | Écriture refusée au niveau 10 | Inchangé (l'écran app4 fait foi) |
| Données | Profil « Inutilisé » | Avant mise en production : `SELECT Code FROM kp_user WHERE Niveau = 10` et la même requête sur les mandats, pour vérifier qu'aucun compte n'a déjà ce niveau |
| Documentation | Colonne 10 de [DROITS_PAR_PROFIL.md](DROITS_PAR_PROFIL.md) cochée ✅ en consultation partout, ce qui est faux aujourd'hui (le contrôle `ROLE_VIEWER` bloque) | Corriger la colonne 10, renommer le profil, ajouter les lignes Scrutineering. Mettre à jour `DOC/developer/reference/PROFILE_ROLES.md` |

---

## 3. Écran « Contrôle équipement » (`/scrutineering/team/:teamId`)

Reprise **à l'identique** du mode contrôle d'app2, dans la charte app4 (`AdminPageHeader`, mode sombre, i18n FR/EN,
cibles tactiles adaptées à la tablette).

### 3.1 En-tête

Titre « Scrutineering », compétition / saison, logo et nom de l'équipe, navigation du § 1.2. Boutons :
**Feuille de présence**, **Vue d'ensemble**, **Rafraîchir** (désactivé pendant 5 s après un clic, comme app2),
**PDF Contrôle** (`FeuilleControle.php`).

### 3.2 Tableau

Le tableau liste les joueurs de la composition, **sauf** les arbitres (`A`) et les inactifs (`X`). L'ordre est celui
de la feuille de présence : joueurs et capitaine, puis staff ; à l'intérieur, par numéro, nom, prénom.

| Colonne | Contenu | Interaction |
|---|---|---|
| Joueur | Badge numéro (ou « Coach » pour `E`), Nom Prénom, badge `C` pour le capitaine | — |
| Kayak / Gilet / Casque | Icône de statut | Clic : 0 → 1 → 2 → 3 → 4 → 0 |
| Pagaies | Nombre de pagaies valides | Clic : 0 → 1 → … → 6 → 0 |
| Commentaire | Les 25 premiers caractères (ou « Ajouter un commentaire ») | Clic : fenêtre d'édition (255 caractères max) |

Pour le staff (`E`), seule la colonne Joueur est remplie.

| Valeur | Libellé | Icône / couleur |
|---|---|---|
| 0 | Non contrôlé | presse-papiers, gris |
| 1 | Valide | check, vert |
| 2 | Défaut esthétique | exclamation (cercle), rouge |
| 3 | Défaut sécurité | exclamation (triangle), rouge |
| 4 | Défaut technique | bouclier, rouge |

Le pied de tableau affiche le **total des pagaies valides** (staff exclu). Sous le tableau : la **légende**, et le
bouton **« Réinitialiser l'équipe »**, qui demande confirmation puis efface toutes les lignes `kp_scrutineering` de
l'équipe.

---

## 4. Vue d'ensemble (`/scrutineering`)

Reprise de `ScrOverview` d'app2, sur l'**événement** du contexte de travail : une grille **Club × Catégorie**
(catégorie = `Soustitre2` ou libellé de la compétition). Une cellule liste les équipes concernées avec leur statut.
Les statuts suivent la règle actuelle, sans compter les joueurs `E`, `A` et `X` :

- **Complet** : tous les joueurs ont kayak = 1, gilet = 1, casque = 1 et pagaies > 0 ;
- **Partiel** : au moins un équipement est contrôlé (> 0), mais pas tout ;
- **Aucun** : rien n'est contrôlé (une ligne remise à 0 compte comme « Aucun »).

Un clic sur une équipe ouvre `/scrutineering/team/:teamId`. La page a une légende et un bouton Rafraîchir. Seules
les compétitions autorisées de l'utilisateur sont affichées.

---

## 5. API (api2) — routes `/staff` existantes, en JWT

### 5.1 Authentification

Les routes `/staff/{eventId}/...` de `StaffController` sont **conservées** et passent du jeton app2
(`X-Auth-Token` / `kp_user_token`, `TokenAuthService`) au **JWT** d'app4. Concrètement :

- nouveau firewall `api_staff` (`pattern: ^/staff`, `jwt: ~`) et la règle
  `access_control: { path: ^/staff, roles: ROLE_SCRUTINEER }` ;
- `TokenAuthService` n'est plus utilisé dans `StaffController`. La règle « profil ≤ 3 » est remplacée par
  `ROLE_SCRUTINEER` (profils 1, 2, 10) ;
- **accès à l'événement** : l'événement doit faire partie de `getAllowedEvents()` (tous les événements pour les
  profils ≤ 2). Toutes les requêtes ne renvoient que les équipes des compétitions de `getAllowedCompetitions()` ;
- **protection IDOR** existante conservée : l'équipe doit appartenir à l'événement **et** à une compétition
  autorisée ;
- journal : on passe de `INSERT kp_journal` avec l'événement seul à `Users` + `Evenements` + `Saisons` +
  `Competitions`, pour pouvoir filtrer dans la page Journal.

### 5.2 Routes

| Méthode | Route | Usage app4 | Évolution |
|---|---|---|---|
| GET | `/staff/{eventId}/teams` | Sélecteur et navigation § 1.2 | Ajoute à chaque équipe `competition` (code et libellé), `club_label` et `nation` |
| GET | `/staff/{eventId}/team/{teamId}/players` | Tableau § 3 | Ajoute le contexte équipe (libellé, logo, club, nation, compétition, saison, verrou) |
| PUT | `/staff/{eventId}/team/{teamId}/player/{playerId}/{parameter}/{value}` | Cycle des équipements | Ajoute une validation serveur de la valeur (0–4, ou 0–6 pour `paddle_count`) et vérifie que le joueur fait partie de la composition |
| PUT | `/staff/{eventId}/team/{teamId}/player/{playerId}/comment` | Commentaire | Inchangée |
| DELETE | `/staff/{eventId}/team/{teamId}/reset` | Réinitialisation | Inchangée |
| GET | `/staff/{eventId}/overview` | Vue d'ensemble § 4 | Filtrée sur les compétitions autorisées |
| PUT | `/staff/{eventId}/team/{teamId}/composition/{playerId}` | — | **Supprimée** (mode composition d'app2 : la feuille de présence app4 le remplace) |
| POST | `/staff/{eventId}/team/{teamId}/player` | — | **Supprimée** (idem) |
| GET | `/staff/{eventId}/team/{teamId}/players/search` | — | **Supprimée** (idem) |
| GET | `/staff/test` | — | **Supprimée** |

**Feuille de présence** : les routes existantes `/admin/teams/{teamId}/players*` (`AdminPresenceController`)
s'ouvrent au niveau 10 comme décrit au § 2.2. Leurs libellés de journal ne changent pas.

### 5.3 Journal

| Action | `Actions` | `Journal` |
|---|---|---|
| Statut équipement | `Scrut équipement` | `Equipe {id} - Joueur {matric} - {kayak\|gilet\|casque\|pagaies}={valeur}` |
| Commentaire | `Scrut commentaire` | `Equipe {id} - Joueur {matric}` |
| Réinitialisation | `Scrut réinitialisation` | `Equipe {id} - réinitialisation complète` |
| Feuille de présence | `Ajout titulaire`, `Modification kp_competition_equipe_joueur`, `Suppression titulaire` (existants) | inchangé |

---

## 6. Feuille de présence modifiée par le profil 10

Pas de nouvel écran : la feuille de présence équipe existante est **éditable** par le profil 10 sur toute équipe de
son périmètre, avec les règles de validation actuelles (éligibilité, numéro 0–99, statuts `- C E A X`). On y ajoute
la navigation du § 1.2 et la bascule vers le scrutineering.

---

## 7. Critères d'acceptation

**Droits**
- **ADM-SCR-01** — Un profil 10 ne voit dans le menu que « Scrutineering » (et le contexte de travail). Toute autre
  page app4 et toute route `/admin` hors présence équipe lui renvoient 403 ou une redirection.
- **ADM-SCR-02** — Les profils 1, 2 et 10 accèdent au scrutineering ; un profil 3 à 9 reçoit 403 sur `/staff/*`.
- **ADM-SCR-03** — `/staff/*` refuse le jeton app2 (`X-Auth-Token`) et accepte le JWT app4.
- **ADM-SCR-04** — Un profil 10 reçoit 403 sur un événement absent de son périmètre et 404 sur une équipe hors de
  l'événement ou de ses compétitions autorisées.
- **ADM-SCR-05** — Un profil 10 modifie la feuille de présence de n'importe quelle équipe d'une compétition
  autorisée, quel que soit son club. La copie, la création de joueur et la recherche par licence lui restent
  refusées.
- **ADM-SCR-06** — Si la compétition est verrouillée, la feuille de présence passe en lecture seule pour le
  profil 10, mais l'équipement reste modifiable.
- **ADM-SCR-07** — Un profil 10 avec un mandat de niveau 5 a les droits d'un profil 5 et perd le scrutineering.
  Un profil 5 avec un mandat de niveau 10 a les droits d'un profil 10.

**Contrôle équipement**
- **ADM-SCR-08** — Un clic sur Kayak, Gilet ou Casque fait passer le statut 0→1→2→3→4→0 ; un clic sur Pagaies,
  0→1→…→6→0. La valeur est persistée et l'icône est mise à jour. Une valeur hors bornes envoyée à l'API est
  refusée (400).
- **ADM-SCR-09** — Le total des pagaies valides est la somme des pagaies hors staff.
- **ADM-SCR-10** — Les arbitres (`A`) et inactifs (`X`) n'apparaissent pas ; le staff (`E`) apparaît sans contrôle.
- **ADM-SCR-11** — Le commentaire est limité à 255 caractères et s'affiche tronqué à 25 caractères.
- **ADM-SCR-12** — « Réinitialiser l'équipe » demande confirmation, puis remet tous les contrôles de l'équipe à 0.
- **ADM-SCR-13** — Chaque modification d'équipement, chaque commentaire et chaque réinitialisation crée une ligne
  `kp_journal` avec l'utilisateur, l'événement, la saison, la compétition et le détail du § 5.3.

**Vue d'ensemble et navigation**
- **ADM-SCR-14** — La vue d'ensemble affiche Complet, Partiel ou Aucun selon la règle du § 4, sur les seules
  compétitions autorisées.
- **ADM-SCR-15** — Depuis une équipe, la barre « même club » liste les autres équipes du club dans l'événement, et
  un clic ouvre la même vue (présence ou scrutineering) pour cette équipe.
- **ADM-SCR-16** — Pour une équipe internationale (`Code_club` ≠ 4 caractères), la barre liste les équipes de la
  même nation (3 premiers caractères).
- **ADM-SCR-17** — Précédent / Suivant et `←` / `→` parcourent les équipes de l'événement dans l'ordre du
  sélecteur ; la bascule Présence ⇄ Scrutineering conserve l'équipe.

**Nettoyage**
- **ADM-SCR-18** — app2 n'a plus de page `/login` ni `/scrutineering`, n'envoie plus de jeton et ne redirige plus
  vers la connexion. Ses pages publiques fonctionnent sans changement (§ 8).
- **ADM-SCR-19** — Le libellé du profil 10 est « Contrôle équipement » dans app4. `DROITS_PAR_PROFIL.md` et
  `PROFILE_ROLES.md` sont à jour.

---

## 8. Suppression dans app2 (après livraison app4)

Fichiers supprimés : `pages/scrutineering.vue`, `pages/login.vue`, `components/ScrOverview.vue`,
`components/CommentModal.vue`, `components/TeamSelector.vue` (déjà inutilisé), `composables/useScrutineering.js`,
`stores/scrutineeringStore.js`, `composables/useAuth.js`, `composables/useUser.js`, `composables/useStatus.js`
(utilisé seulement par ces écrans). Code modifié : `useApi.js` (plus d'en-tête de jeton, plus de logout ni de
redirection `/login`), `middleware/event-guard.ts` (`/login` retiré), préférences `scr_*`, clés i18n
`Scrutineering.*` et `Login.*`, et le commentaire de `AppHeader.vue`.

api2 : `AuthController` (`POST /login`, `GET /me`, authentification app2) est **supprimé** : seule app2 l'appelait.
`app_wsm_dev` et `app_dev` utilisent l'API legacy `/api/`, pas api2. Ensuite, `TokenAuthService` ne reste utilisé
que par `ReportController` (`GET /report/game/{id}`), qu'aucun front n'appelle (**Q3**). La table `kp_user_token`
est conservée : le legacy (`api/`, `connector/`) et `AdminAuthController` l'utilisent encore.

---

## 9. Questions ouvertes

- **Q1 — Attribution du profil 10** : aujourd'hui, les profils 1, 2, 3 et 4 peuvent créer un utilisateur de niveau 10
  dans leur périmètre. Faut-il réserver le profil 10 aux profils 1–2 (proposé, puisque 3 et 4 n'ont pas le
  scrutineering), ou laisser 3 et 4 désigner leurs contrôleurs dans leurs compétitions ?
- **Q2 — Legacy** : faut-il refuser la connexion au back-office legacy pour le profil 10 (proposé) ?
- **Q3 — `/report/game/{id}`** : faut-il le supprimer avec `TokenAuthService` (aucun appelant trouvé), ou le garder
  hors de ce lot ?
- **Q4 — Nation** : la règle « 4 caractères = club français, nation FRA » convient-elle pour la barre « même
  nation » ?
