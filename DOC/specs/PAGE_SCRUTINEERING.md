# Spécification - Scrutineering (contrôle des équipements) dans app4

## Statut : 📝 Brouillon — à valider avant implémentation

**Origine** : migration de la page `/scrutineering` d'app2 (`sources/app2/pages/scrutineering.vue`,
`components/ScrOverview.vue`, `composables/useScrutineering.js`) vers app4, accessible depuis la feuille de
présence équipe ([PAGE_PRESENCE.md](PAGE_PRESENCE.md)).

---

## 1. Vue d'ensemble

Le scrutineering est le contrôle de l'équipement des joueurs d'une équipe avant la compétition : kayak, gilet,
casque, nombre de pagaies valides et commentaire libre par joueur. Les données sont stockées dans
`kp_scrutineering` (clé `id_equipe` + `matric`), une ligne par joueur d'une composition
`kp_competition_equipe_joueur`.

Dans app4, le scrutineering devient une **compétence transversale** (capacité « Scrutineer ») :

- accordée **par défaut** aux profils effectifs **1 et 2** ;
- **attribuable** à un utilisateur de profil **3 à 7** (même mécanisme que la capacité Rédacteur,
  table `kp_user_capability`) ;
- elle permet d'**agir sur le scrutineering** et de **modifier les feuilles de présence** de **toutes les équipes**
  des compétitions de son périmètre ;
- chaque action est **tracée dans le journal** (`kp_journal`).

### 1.1 Routes app4

| Route | Écran | Accès |
|---|---|---|
| `/presence/team/:teamId/scrutineering` | Contrôle équipement d'une équipe | Scrutineer (lecture : comme la feuille de présence) |
| `/scrutineering` | Vue d'ensemble des équipes des compétitions du contexte de travail | Scrutineer |

**Points d'entrée** :

- Feuille de présence équipe (`/presence/team/:teamId`) : bouton **« Scrutineering »** dans l'en-tête (à côté
  de « Éditions PDF »). Réciproquement, l'écran scrutineering a un bouton **« Feuille de présence »**.
- Menu : entrée **Compétitions › Scrutineering** (sous « Feuilles de présence » / « Équipes »), visible avec la
  capacité Scrutineer.
- Le sélecteur d'équipe de l'en-tête (identique à celui de la feuille de présence) navigue d'une équipe à l'autre
  sans repasser par la vue d'ensemble.

---

## 2. Droits

### 2.1 Capacité Scrutineer

| | Profils 1–2 | Profils 3–7 avec la capacité | Profils 3–7 sans | Profils 8–10 |
|---|---|---|---|---|
| Voir l'écran scrutineering | ✅ | ✅ | ✅ lecture seule | ✅ lecture seule |
| Modifier équipement / commentaire | ✅ | ✅ | ❌ | ❌ |
| Réinitialiser une équipe | ✅ | ✅ | ❌ | ❌ |
| Vue d'ensemble `/scrutineering` | ✅ | ✅ | ❌ | ❌ |
| Modifier la feuille de présence de **toute** équipe du périmètre (n°, statut, ajout, suppression) | ✅ | ✅ | selon règles actuelles (club) | ❌ |
| Attribuer / retirer la capacité | ✅ | ❌ | ❌ | ❌ |

Règles :

- **Niveau effectif** : la capacité n'a d'effet que si le niveau **effectif** (mandat compris) est ≤ 7. Un
  utilisateur dont le mandat actif est de niveau 8+ perd le bénéfice de la capacité tant que le mandat est actif.
- **Périmètre** : la capacité ne s'exerce que sur les compétitions de `getAllowedCompetitions()` (bypass pour les
  profils ≤ 2, cf. [DROITS_PAR_PROFIL.md](DROITS_PAR_PROFIL.md)). Elle **lève la restriction de clubs**
  (`Limit_clubs`) pour les feuilles de présence équipe : un scrutineer contrôle toutes les équipes, pas seulement
  celles de son club.
- **Verrou** : une compétition verrouillée (`Verrou = 'O'`) reste non modifiable pour la feuille de présence,
  capacité ou non (comme le mode composition d'app2). Le contrôle équipement, lui, reste possible même compétition
  verrouillée (comportement actuel d'app2 : la composition est figée, pas l'équipement).
- La capacité **n'élargit pas** les autres droits de la feuille de présence : copie depuis une autre compétition et
  création de joueur restent ≤ 4, recherche par licence ≤ 2, ajout/statut forcé d'un joueur non en règle ≤ 2.
- La capacité s'attribue dans la fenêtre d'édition d'un utilisateur (`UserEditModal`), case
  **« Scrutineer (contrôle équipement) »**, visible et modifiable par les profils 1–2, proposée uniquement si le
  profil de l'utilisateur édité est ≤ 7.

### 2.2 Impact sur app2

App2 accorde aujourd'hui le scrutineering aux profils ≤ 3 ayant l'événement dans leur périmètre (jeton
`kp_user_token`). **Proposition** : app2 inchangée dans ce lot (cf. question Q3).

---

## 3. Écran « Contrôle équipement » (`/presence/team/:teamId/scrutineering`)

Reprise **à l'identique** du mode « contrôle » d'app2, dans la charte app4 (`AdminPageHeader`, mode sombre,
i18n FR/EN).

### 3.1 En-tête

- Titre « Scrutineering », badges compétition / saison, logo et nom de l'équipe, sélecteur d'équipe.
- Boutons : **Rafraîchir** (désactivé 5 s après usage, comme app2), **Feuille de présence**, **Vue d'ensemble**,
  **Éditions PDF › Contrôle** (`FeuilleControle.php`, déjà présent dans la feuille de présence).

### 3.2 Tableau

Joueurs de la composition **hors** Arbitre (`A`) et Inactif (`X`), triés comme la feuille de présence
(joueurs/capitaine, puis staff ; puis numéro, nom, prénom).

| Colonne | Contenu | Interaction (scrutineer) |
|---|---|---|
| Joueur | Badge numéro (ou « Coach » si `E`), Nom Prénom, badge `C` si capitaine | — |
| Kayak / Gilet / Casque | Icône de statut | Clic : cycle 0 → 1 → 2 → 3 → 4 → 0 |
| Pagaies | Nombre de pagaies valides | Clic : cycle 0 → 1 → … → 6 → 0 |
| Commentaire | 25 premiers caractères (ou « Ajouter un commentaire ») | Clic : fenêtre d'édition (255 car. max) |

Staff (`E`) : seule la colonne Joueur est renseignée, pas de contrôle.

Statuts équipement :

| Valeur | Libellé | Icône / couleur |
|---|---|---|
| 0 | Non contrôlé | presse-papiers, gris |
| 1 | Valide | check, vert |
| 2 | Défaut esthétique | exclamation (cercle), rouge |
| 3 | Défaut sécurité | exclamation (triangle), rouge |
| 4 | Défaut technique | bouclier, rouge |

Pied de tableau : **total des pagaies valides** (somme hors staff). Sous le tableau : **légende** et bouton
**« Réinitialiser l'équipe »** (fenêtre de confirmation) qui efface toutes les lignes `kp_scrutineering` de
l'équipe.

Sans la capacité : mêmes informations, boutons inactifs, pas de réinitialisation.

---

## 4. Vue d'ensemble (`/scrutineering`)

Reprise de `ScrOverview` d'app2, mais **par compétition** (contexte de travail app4) au lieu de par événement :

- grille **Club (lignes) × Compétition (colonnes)** sur les compétitions du contexte de travail (filtre de page
  compétition standard d'app4) ; une cellule liste les équipes du club dans la compétition ;
- statut par équipe (même règle que l'API actuelle, joueurs `E/A/X` exclus) :
  - **Complet** : tous les joueurs ont kayak = 1, gilet = 1, casque = 1 et pagaies > 0 ;
  - **Partiel** : au moins un équipement contrôlé (> 0) mais pas complet ;
  - **Aucun** : rien de contrôlé ;
- clic sur une équipe → `/presence/team/:teamId/scrutineering` ; légende ; bouton rafraîchir.

---

## 5. Feuille de présence modifiée par un scrutineer

Aucun nouvel écran : la feuille de présence équipe existante (`/presence/team/:teamId`) devient **éditable** pour
un scrutineer sur toute équipe de son périmètre (cf. § 2.1), avec les règles de validation actuelles
(éligibilité, numéro 0–99, statuts `- C E A X`). Le mode composition d'app2 n'est donc **pas** dupliqué dans
app4 : il est couvert par la feuille de présence.

---

## 6. API (api2)

### 6.1 Constat

Les routes existantes `/staff/{eventId}/...` (StaffController) sont authentifiées par le **jeton app2**
(`X-Auth-Token` / `kp_user_token`), limitées au **profil ≤ 3** et **scopées par événement**. App4 s'authentifie par
**JWT** (firewall `^/admin`) et travaille **par compétition / équipe**. Elles ne sont donc pas appelables telles
quelles depuis app4.

### 6.2 Proposition (à valider, Q1)

Réutiliser **la logique et les données** des routes `/staff` (requêtes, règles de cycle, statut d'overview,
libellés de journal) en les extrayant dans un service partagé `ScrutineeringService`, appelé :

- par `StaffController` (app2, **comportement inchangé**) ;
- par un nouveau contrôleur JWT `AdminScrutineeringController` :

| Méthode | Route | Équivalent `/staff` | Droit |
|---|---|---|---|
| GET | `/admin/teams/{teamId}/scrutineering` | `GET /staff/{e}/team/{t}/players` | ROLE_VIEWER, périmètre ; renvoie `canEdit` |
| PUT | `/admin/teams/{teamId}/scrutineering/{matric}/{parameter}/{value}` | `PUT /staff/{e}/team/{t}/player/{p}/{param}/{value}` | Scrutineer |
| PUT | `/admin/teams/{teamId}/scrutineering/{matric}/comment` | `PUT /staff/{e}/team/{t}/player/{p}/comment` | Scrutineer |
| DELETE | `/admin/teams/{teamId}/scrutineering` | `DELETE /staff/{e}/team/{t}/reset` | Scrutineer |
| GET | `/admin/scrutineering/overview?competitions=…` | `GET /staff/{e}/overview` | Scrutineer |

Et pour la feuille de présence : les routes existantes `/admin/teams/{teamId}/players*` (AdminPresenceController)
acceptent la capacité Scrutineer en lieu et place de la restriction de clubs (`canEdit` renvoyé à `true`).

Validation serveur : `parameter ∈ {kayak_status, vest_status, helmet_status, paddle_count}`, `value` 0–4
(0–6 pour les pagaies), joueur appartenant à la composition de l'équipe, équipe dans le périmètre.

La capacité est exposée par `/auth/me` dans `user.capabilities` (déjà en place pour `editor`) sous la valeur
`scrutineer` ; un `ScrutineeringAccess` (calqué sur `EditorialAccess`) porte la règle « ≤ 2 ou capacité et niveau
effectif ≤ 7 ».

### 6.3 Journal

Actions écrites avec `Saisons` + `Competitions` (helper `logActionForCompetition`) pour être filtrables dans la
page Journal, en reprenant les libellés d'app2 :

| Action | Libellé `Actions` | `Journal` |
|---|---|---|
| Statut équipement | `Scrut équipement` | `Equipe {id} - Joueur {matric} - {kayak\|gilet\|casque\|pagaies}={valeur}` |
| Commentaire | `Scrut commentaire` | `Equipe {id} - Joueur {matric}` |
| Réinitialisation | `Scrut réinitialisation` | `Equipe {id} - réinitialisation complète` |
| Attribution capacité | `Capacité scrutineer` | `{code utilisateur} : ajout\|retrait` |
| Feuille de présence | libellés actuels (`Ajout titulaire`, `Modification kp_competition_equipe_joueur`, `Suppression titulaire`) | inchangés |

---

## 7. Critères d'acceptation

- **ADM-SCR-01** — Un profil 1 ou 2 sans capacité voit le bouton « Scrutineering » sur la feuille de présence et
  peut modifier l'équipement de n'importe quelle équipe.
- **ADM-SCR-02** — Un profil 3–7 sans capacité voit l'écran en lecture seule (boutons inactifs) ; toute écriture
  directe sur l'API renvoie 403.
- **ADM-SCR-03** — Un profil 3–7 avec la capacité peut modifier l'équipement et réinitialiser une équipe d'une
  compétition de son périmètre ; hors périmètre, l'API renvoie 403.
- **ADM-SCR-04** — Un profil 7 avec la capacité et `Limit_clubs` renseigné peut modifier la feuille de présence
  d'une équipe d'un autre club de la compétition ; sans la capacité, il ne le peut pas (comportement actuel).
- **ADM-SCR-05** — Avec la capacité, une feuille de présence d'une compétition verrouillée reste en lecture seule ;
  l'équipement reste modifiable.
- **ADM-SCR-06** — Un clic sur Kayak / Gilet / Casque fait passer le statut au suivant (0→1→2→3→4→0) ; un clic sur
  Pagaies 0→1→…→6→0 ; la valeur est persistée et l'icône mise à jour.
- **ADM-SCR-07** — Le total des pagaies valides est la somme des pagaies des joueurs hors staff.
- **ADM-SCR-08** — Arbitres (`A`) et inactifs (`X`) n'apparaissent pas ; le staff (`E`) apparaît sans contrôle.
- **ADM-SCR-09** — Le commentaire est limité à 255 caractères, enregistré et affiché tronqué à 25 caractères.
- **ADM-SCR-10** — « Réinitialiser l'équipe » demande confirmation puis remet tous les contrôles de l'équipe à 0.
- **ADM-SCR-11** — Chaque modification d'équipement, commentaire et réinitialisation crée une ligne `kp_journal`
  avec utilisateur, saison, compétition et le détail du § 6.3.
- **ADM-SCR-12** — La vue d'ensemble affiche Complet / Partiel / Aucun selon la règle du § 4 ; une ligne
  `kp_scrutineering` remise à 0 ne rend pas l'équipe « Partielle ».
- **ADM-SCR-13** — La case « Scrutineer » de la fenêtre utilisateur n'est visible que pour un éditeur de profil
  1–2 et un utilisateur édité de profil ≤ 7 ; elle ajoute / retire la capacité et trace l'action.
- **ADM-SCR-14** — Un utilisateur avec la capacité dont le mandat actif est de niveau ≥ 8 n'a plus les droits
  d'écriture.
- **ADM-SCR-15** — Les routes `/staff/*` d'app2 gardent leur comportement (tests de non-régression).
- **ADM-SCR-16** — Écran utilisable sur tablette (cibles tactiles ≥ 44 px) et en mode sombre.

---

## 8. Questions ouvertes

- **Q1 — API** : nouvelles routes JWT `/admin/...` partageant la logique des `/staff` via un service (proposé), ou
  faire accepter le JWT aux routes `/staff/{eventId}/...` existantes (impose un événement dans le contexte de
  travail et un scope par événement) ?
- **Q2 — Vue d'ensemble** : par compétitions du contexte de travail (proposé), par événement comme app2, ou pas
  de vue d'ensemble dans ce lot ?
- **Q3 — App2** : laisser app2 sur sa règle actuelle (profil ≤ 3 + événement, proposé), ou l'aligner sur la
  capacité (profils 1–2 + scrutineers, profil 3 perdant l'accès par défaut) ?
- **Q4 — Verrou** : équipement modifiable même compétition verrouillée (proposé, comme app2) ?
