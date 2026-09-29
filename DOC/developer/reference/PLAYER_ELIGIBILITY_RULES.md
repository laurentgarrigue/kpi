# Règles « joueur en règle » (éligibilité des joueurs)

Ce document décrit le contrôle appliqué aux joueurs inscrits sur une **feuille de présence**
(`kp_competition_equipe_joueur`) des compétitions nationales et régionales.

> **Point de paramétrage unique** :
> [`sources/api2/src/Eligibility/PlayerEligibilityRules.php`](../../../sources/api2/src/Eligibility/PlayerEligibilityRules.php)
>
> Toute évolution du règlement se fait **uniquement** dans ce fichier. La logique
> (`PlayerEligibilityChecker.php`, même dossier) et app4 s'adaptent automatiquement :
> app4 reçoit le jeu de règles actif depuis l'API et ne le réimplémente pas.

---

## 1. Compétitions concernées

| Niveau | Détection | Application |
|---|---|---|
| **National** | code commençant par `N` (championnats) ou `CF` (Coupe de France) — comme le legacy | **Bloquant** |
| **Régional** | `kp_competition.Code_niveau = 'REG'` | **Alertes seulement** |
| Autres (INT, tournois…) | — | Aucun contrôle |

Détection : `PlayerEligibilityRules::levelFor()`.

## 2. Critères

| Critère | Donnée | National | Régional | Code d'erreur |
|---|---|---|---|---|
| Licence de la saison | `kp_licence.Origine >= saison` | ✅ | ✅ | `Saison_licence` |
| Licence compétition | `kp_licence.Type_licence` ∈ liste | ✅ `Carte 1 an Compétition` | ✅ `Carte 1 an Compétition` | `Type_licence` |
| Certificat compétition | `kp_licence.Etat_certificat_CK = 'OUI'` | ✅ | ✅ | `Certif` |
| Pagaie eau calme minimum | `kp_licence.Pagaie_ECA` | ✅ verte | ✅ jaune | `Pagaie_couleur` |
| Surclassement | `kp_surclassement` de la saison | ✅ (compétitions/catégories listées) | — | `Surclassement` |

- Échelle des pagaies (`PAGAIE_RANKS`) : blanche < jaune < verte < bleue < rouge < noire.
  Un code vide ou inconnu (ex. `PAGI`) ne satisfait jamais un minimum.
- Surclassement : requis pour les compétitions de `SURCLASSEMENT_COMPETITIONS`, sauf pour les
  catégories de `SURCLASSEMENT_EXEMPT_CATEGORIES` (JUN, SEN, V1 à V4). Il est donc obligatoire
  pour les jeunes (moins de JUN) **et pour les vétérans à partir de V5**. Ces deux listes étaient auparavant dans les
  variables d'environnement `SURCLASSEMENT_COMPETITIONS` / `SURCLASSEMENT_EXEMPT_CATEGORIES`
  (supprimées : elles peuvent rester dans d'anciens `.env`, elles sont ignorées).
- Les codes d'erreur sont traduits dans app4 par `presence.error_<code>`.

## 3. Statuts

`-` Joueur, `C` Capitaine (statuts **joueurs**, `PLAYING_STATUSES`) ; `E` Staff, `A` Arbitre non
joueur, `X` Inactif (statuts **non joueurs**).

## 4. Comportement

### 4.1 Compétition nationale (bloquant)

| Action | Joueur non en règle |
|---|---|
| **Ajout** sur la feuille de présence | Refusé (HTTP 400, `errors`, `canForce`). Profils ≤ 2 (`FORCE_MAX_PROFILE`) : ajout forcé possible **uniquement** en statut Arbitre ou Staff (`FORCE_ADD_STATUSES`). |
| **Changement de statut** vers Staff / Arbitre / Inactif | Toujours autorisé. |
| **Changement de statut** vers Joueur / Capitaine | Refusé. Profils ≤ 2 : forçage possible (`FORCE_STATUS_CHANGE_STATUSES`) après confirmation. |
| **Copie de feuille de présence** | Les joueurs non en règle ayant un statut Joueur/Capitaine passent en **Inactif** (`COPY_INELIGIBLE_STATUS`). Staff/Arbitre/Inactif conservent leur statut. |
| Création d'un joueur non licencié | Refusée (un joueur créé n'a ni certificat ni pagaie) ; l'onglet est déjà masqué dans app4. |

### 4.2 Compétition régionale (alertes)

Mêmes contrôles, mais aucune action n'est refusée ni aucun statut modifié :
- ajout : le joueur est ajouté, l'API renvoie `warnings` → notification d'alerte ;
- changement vers Joueur/Capitaine : effectué, avec `warnings` ;
- copie : statuts conservés, le nombre de joueurs non en règle est signalé.

### 4.3 Copies de feuilles de présence concernées

| Écran | Action | Endpoint |
|---|---|---|
| Feuille de présence | « Copier depuis » | `POST /admin/teams/{id}/players/copy` |
| Équipes | « Ajouter » (historique ou classement) avec « Inclure la/les feuille(s) de présence » | `POST /admin/competition-teams` |
| Équipes | « Dupliquer » avec « Inclure la/les feuille(s) de présence » | `POST /admin/competition-teams/duplicate` |
| Classements | « Affecter les équipes cochées » avec « Inclure la/les feuille(s) de présence » (compétition terminée uniquement) | `POST /admin/rankings/transfer` (`includePlayers`, défaut `true`) |

Chacune renvoie `ineligible: { enforcement, count }` ; app4 affiche une notification si `count > 0`.
Le contrôle est évalué pour la **compétition et la saison cibles**.

## 5. Affichage app4

- Feuille de présence : badge « National » ou « Contrôle régional » ; grand triangle ⚠ plein à côté
  du nom des joueurs non en règle (infobulle = critères manquants ; rouge si bloquant, orange si
  alerte, gris pour un statut non joueur) ; colonne Surclassement avec pastilles ✓ / ✗ pleines.
- Chaque critère en défaut est signalé dans sa colonne par un badge uniforme (rouge si bloquant,
  orange si alerte ; infobulle = message) : année de licence ancienne et « Non compét. » à côté
  du n° de licence, couleur de pagaie (ou « aucune »), certificat « Non ».
  Composant : `components/admin/PresenceComplianceCell.vue`.
- Menus de statut : en national, Joueur/Capitaine sont désactivés pour un joueur non en règle,
  sauf pour les profils ≤ 2 qui obtiennent une confirmation de forçage.
- Réponse API de la feuille : `competition.eligibility` (jeu de règles actif ou `null`) et
  `players[].eligibilityErrors`.

## 6. Faire évoluer le règlement

| Besoin | Modification dans `PlayerEligibilityRules.php` |
|---|---|
| Changer la pagaie minimum | `RULES[<niveau>]['minPagaieECA']` |
| Ajouter/retirer un critère pour un niveau | booléen / liste dans `RULES[<niveau>]` |
| Passer le régional en bloquant | `RULES[LEVEL_REGIONAL]['enforcement'] = ENFORCEMENT_BLOCK` |
| Changer les profils autorisés à forcer | `FORCE_MAX_PROFILE` |
| Changer les statuts forçables | `FORCE_ADD_STATUSES`, `FORCE_STATUS_CHANGE_STATUSES` |
| Compétitions avec surclassement | `SURCLASSEMENT_COMPETITIONS`, `SURCLASSEMENT_EXEMPT_CATEGORIES` |
| Détection des niveaux | `levelFor()` |

Un **nouveau type de critère** (nouvelle colonne à contrôler) nécessite en plus d'ajouter la
vérification dans `PlayerEligibilityChecker::evaluate()`, la colonne dans ses requêtes, un code
d'erreur et sa traduction `presence.error_<code>` (fr/en).

Tests : `sources/api2/tests/Unit/Eligibility/PlayerEligibilityCheckerTest.php` (à mettre à jour
avec les règles). api2 tourne en mode worker : le déploiement CI/CD recycle le worker
(`make api2_restart`) automatiquement ; à faire à la main seulement hors CI/CD.
