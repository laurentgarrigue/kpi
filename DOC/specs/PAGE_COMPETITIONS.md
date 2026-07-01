# Spécification - Page Compétitions

## 1. Vue d'ensemble

La page Compétitions permet de gérer les compétitions d'une saison : création, modification, paramétrage des options d'affichage, gestion des images (bandeau, logo, sponsor), et configuration des classements.

**Route** : `/competitions`

**Accès** :
- Profil ≤ 10 : Lecture seule
- Profil ≤ 4 : Toggle publication
- Profil ≤ 3 : Modification / Verrouillage / Images
- Profil ≤ 2 : Création / Suppression / Copie / Code de compétition modifiable à l'import

**Restrictions transverses** (voir [§ 11](#11-statut-end-et-lecture-seule)) :
- Une compétition au statut **END** est en **lecture seule** (équipes, journées/phases, présents inclus).
- Les **saisons antérieures** à la saison active sont en lecture seule pour les profils **> 2** (seuls les profils ≤ 2 peuvent rouvrir une compétition passée).

**Page PHP Legacy** : `GestionCompetition.php`

---

## 2. Fonctionnalités

### 2.1 Liste des compétitions

| # | Fonctionnalité | Profil | Statut |
|---|----------------|--------|--------|
| 1 | Liste par saison (contexte de travail) | ≤ 10 | ✅ Implémenté |
| 2 | Groupement par section avec accordion | ≤ 10 | ✅ Implémenté |
| 3 | Recherche texte client-side (code/libellé/groupe) | ≤ 10 | ✅ Implémenté |
| 4 | Replier/déplier toutes les sections | ≤ 10 | ✅ Implémenté |
| 5 | Afficher nb équipes / journées / matchs | ≤ 10 | ✅ Implémenté |
| 6 | Toggle publication | ≤ 4 | ✅ Implémenté |
| 7 | Toggle verrou FDM | ≤ 3 (désactivé si END) | ✅ Implémenté |
| 8 | Changement de statut (ATT→ON→END→ATT, cycle) | ≤ 3 — ≤ 2 si saison passée | ✅ Implémenté |
| 9 | Import depuis saison précédente (autocomplete) | ≤ 2 | ✅ Implémenté |

> **Statut END** : le passage à END verrouille automatiquement la compétition (`Verrou='O'`) et la rend lecture seule partout (cf. [§ 11](#11-statut-end-et-lecture-seule)). La modification (✏️) ouvre alors le formulaire en consultation, et le toggle verrou est désactivé. Le cycle de statut reste possible (END→ATT pour rouvrir) selon le profil et la saison.

### 2.2 Formulaire création/modification

| # | Fonctionnalité | Profil | Statut |
|---|----------------|--------|--------|
| 1 | Code unique par saison (max 12 car.) | ≤ 2 (création) / ≤ 3 (modification) | ✅ Implémenté |
| 2 | Niveau (INT/NAT/REG) | ≤ 3 | ✅ Implémenté |
| 3 | Type de classement (CHPT/CP/MULTI) | ≤ 3 | ✅ Implémenté |
| 4 | Libellé, sous-titre, catégorie | ≤ 3 | ✅ Implémenté |
| 5 | Groupe et ordre dans le groupe | ≤ 3 | ✅ Implémenté |
| 6 | Tour/Phase, statut | ≤ 3 | ✅ Implémenté |
| 7 | Qualifiés / Éliminés (hors MULTI) | ≤ 3 | ✅ Implémenté |
| 8 | Barème de points (hors MULTI) | ≤ 3 | ✅ Implémenté |
| 9 | Goal average (hors MULTI) | ≤ 3 | ✅ Implémenté |
| 10 | Compétitions sources MULTI | ≤ 3 | ✅ Implémenté |
| 11 | Grille de points MULTI | ≤ 3 | ✅ Implémenté |
| 12 | Type de classement MULTI | ≤ 3 | ✅ Implémenté |
| 13 | Lien web | ≤ 3 | ✅ Implémenté |
| 14 | Options d'affichage (checkboxes) | ≤ 3 | ✅ Implémenté |
| 15 | Images bandeau / logo / sponsor | ≤ 3 | ✅ Implémenté |
| 16 | Commentaires privés | ≤ 3 | ✅ Implémenté |

### 2.3 Gestion des images

Chaque image (bandeau, logo, sponsor) dispose d'un picker à 3 modes, accessible uniquement au profil ≤ 3.

| Mode | Description |
|------|-------------|
| **Existante** | Recherche parmi les fichiers déjà présents dans `/img/logo/` (min. 2 caractères, 5 résultats max) |
| **Upload** | Upload d'un fichier local — redimensionnement automatique si dépassement des dimensions max |
| **URL externe** | Import serveur-side depuis une URL : téléchargement, validation (magic bytes + MIME), redimensionnement si nécessaire |

**Nommage normalisé** :
- Bandeau : `B-{CODE}-{SAISON}.ext`  (max 2480×250 px)
- Logo    : `L-{CODE}-{SAISON}.ext`  (max 1000×1000 px)
- Sponsor : `S-{CODE}-{SAISON}.ext`  (max 2480×250 px)

Formats acceptés : JPG ou PNG. Stockage dans `/img/logo/` (backend PHP legacy).

**Comportement** :
- L'upload et l'import URL sont immédiats (indépendants du save du formulaire)
- Le code et la saison doivent être renseignés pour construire le nom normalisé
- "Retirer" vide le champ en base uniquement — le fichier physique n'est pas supprimé
- La prévisualisation utilise `legacyBaseUrl + /img/logo/{filename}`

### 2.4 Suppression

- Possible uniquement si la compétition n'a ni équipes, ni journées, ni matchs
- Confirmation requise (modal)
- Suppression en masse possible via sélection (profil ≤ 2)

### 2.5 Liens vers autres pages

| Lien | Page cible | Profil |
|------|-----------|--------|
| Code → icône document | Page Documents | ≤ 10 |
| Icône RC | Page RC | ≤ 10 |
| Nb équipes | Page Équipes (filtrée) | ≤ 10 |
| Nb journées | Page Journées | ≤ 10 |
| Nb matchs | Page Matchs | ≤ 10 |
| Bouton "Copier" | Page Copie de compétition | ≤ 2 |

---

## 3. Structure de la Page

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Header : Gestion des compétitions                                       │
├─────────────────────────────────────────────────────────────────────────┤
│  Toolbar : [Recherche]  [Replier tout] [Déplier tout]  [Copier] [+ Ajouter] │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ▼ Section: National                                                     │
│  ┌───┬──────┬─────┬────────────────────────┬──────┬──────┬───────┬────┐ │
│  │👁️ │ Code │ ✏️  │ Libellé                │ Niv  │Groupe│Statut │... │ │
│  ├───┼──────┼─────┼────────────────────────┼──────┼──────┼───────┼────┤ │
│  │🟢 │ N1M  │ ✏️  │ Nationale 1 Masculine  │ NAT  │ N1   │  ON   │... │ │
│  └───┴──────┴─────┴────────────────────────┴──────┴──────┴───────┴────┘ │
│                                                                          │
│  ▶ Section: Coupe de France  (collapsed)                                 │
└─────────────────────────────────────────────────────────────────────────┘
```

### 3.1 Colonnes du tableau (desktop)

| Colonne | Description | Action |
|---------|-------------|--------|
| 👁️ Publication | Vert = publié, gris = privé | Toggle (profil ≤4) |
| Code | Code unique | Lien vers Documents |
| ✏️ | Modifier | Ouvre modal (profil ≤3) |
| Libellé | Nom + sous-titre | - |
| Niveau | INT / NAT / REG | - |
| Groupe | Code du groupe | Lien vers Journées (groupe) |
| Étape | Numéro de tour/phase | - |
| Type | CHPT / CP / MULTI | - |
| Statut | ATT / ON / END | Click pour cycler le statut (profil ≤3 ; ≤2 en saison passée) |
| Équipes | Nombre d'équipes | Lien vers page Équipes |
| 🔒 Verrou | Verrouiller FDM | Toggle (profil ≤3 ; désactivé si END) |
| Journées | Nb journées/phases | Lien vers Journées |
| Matchs | Nombre de matchs | Lien vers Matchs |
| Actions | RC + Suppression | Profil ≤2 pour suppression |

---

## 4. Modal Création/Édition

### 4.1 Mode création : import depuis saison précédente

Accessible aux profils ≤ 2 uniquement. Un autocomplete permet de rechercher une compétition des saisons précédentes et de pré-remplir tous les champs. Le code importé est verrouillé par défaut ; les profils ≤ 2 peuvent le déverrouiller via un bouton dédié.

### 4.2 Champs du formulaire

| Champ | Type | Requis | Validation | Profil |
|-------|------|--------|------------|--------|
| code | text(12) | Oui | Unique par saison, majuscules | ≤3 (≤2 si import) |
| code_niveau | select | Oui | INT / NAT / REG | ≤3 |
| code_typeclt | select | Oui | CHPT / CP / MULTI | ≤3 |
| libelle | text(80) | Oui | Max 80 car. | ≤3 |
| soustitre | text(80) | Non | Titre public | ≤3 |
| soustitre2 | text(80) | Non | Catégorie / libellé court | ≤3 |
| code_ref | select | Non | Groupe existant | ≤3 |
| group_order | number | Non | Ordre dans le groupe | ≤3 |
| code_tour | select | Oui | 1–6 ou 10 (Finale) | ≤3 |
| statut | select | Oui | ATT / ON / END | ≤3 |
| qualifies | number | Non | Hors MULTI | ≤3 |
| elimines | number | Non | Hors MULTI | ≤3 |
| points | select | Oui | 4-2-1-0 ou 3-1-0-0, hors MULTI | ≤3 |
| goalaverage | select | Oui | gen / part, hors MULTI | ≤3 |
| ranking_structure_type | select | Si MULTI | team/club/cd/cr/nation | ≤3 |
| points_grid | json | Si MULTI | Grille de points JSON | ≤3 |
| multi_competitions | json[] | Si MULTI | Codes des compétitions sources | ≤3 |
| web | url | Non | URL du site web | ≤3 |
| en_actif | checkbox | Non | Libellé anglais actif | ≤3 |
| titre_actif | checkbox | Non | Titre actif | ≤3 |
| bandeau_actif | checkbox | Non | Afficher le bandeau | ≤3 |
| logo_actif | checkbox | Non | Afficher le logo | ≤3 |
| sponsor_actif | checkbox | Non | Afficher le sponsor | ≤3 |
| kpi_ffck_actif | checkbox | Non | Afficher logo KPI/FFCK | ≤3 |
| bandeau_link | picker | Non | Fichier dans `/img/logo/` | ≤3 |
| logo_link | picker | Non | Fichier dans `/img/logo/` | ≤3 |
| sponsor_link | picker | Non | Fichier dans `/img/logo/` | ≤3 |
| commentaires_compet | textarea | Non | Notes privées | ≤3 |

> **Note** : `bandeau_link`, `logo_link`, `sponsor_link` ne sont pas inclus dans le payload PUT/POST — ils sont mis à jour directement via l'API d'images au moment de l'upload/import/sélection.

### 4.3 Section Images (détail)

```
┌─────────────────────────────────────────────────────┐
│ Images                                               │
│                                                      │
│ Bandeau                                              │
│ ┌──────────────────────────────────────────────────┐│
│ │ [aperçu image pleine largeur, max-h 64px]        ││
│ │ B-N1H-2024-2025.jpg                   [Retirer]  ││
│ │ ┌─────────────┬──────────┬────────────┐          ││
│ │ │  Existante  │  Upload  │ URL externe│          ││
│ │ └─────────────┴──────────┴────────────┘          ││
│ │ [🔍 Rechercher un fichier... ]                   ││
│ │  → affichage après 2 car., max 5 résultats       ││
│ └──────────────────────────────────────────────────┘│
│                                                      │
│ Logo    [idem]                                       │
│ Sponsor [idem]                                       │
└─────────────────────────────────────────────────────┘
```

---

## 5. Type MULTI — Configuration spécifique

### 5.1 Grille de points

Format JSON définissant les points par position :
```json
{"1": 10, "2": 6, "3": 4, "default": 0}
```

**Composant** : `AdminPointsGridEditor` (v-model sur `formData.pointsGrid`)

- Nombre de positions : 1–50
- Positions vides exclues du JSON émis
- Bouton "Effacer" remet à `null`
- Aperçu JSON en temps réel (collapsible)

### 5.2 Compétitions sources

Select multiple groupé par section (compétitions de la saison courante).

### 5.3 Types de classement MULTI

| Type | Description |
|------|-------------|
| team | Par équipe (défaut) |
| club | Par club |
| cd | Par Comité Départemental |
| cr | Par Comité Régional |
| nation | Par nation |

---

## 6. Endpoints API2

### 6.1 Compétitions

| Méthode | Endpoint | Description | Profil |
|---------|----------|-------------|--------|
| GET | `/admin/competitions` | Liste (filtrée par saison/contexte) | ≤10 |
| GET | `/admin/competitions/{code}` | Détail | ≤10 |
| POST | `/admin/competitions` | Créer | ≤2 |
| PUT | `/admin/competitions/{code}` | Modifier | ≤3 |
| DELETE | `/admin/competitions/{code}` | Supprimer | ≤2 |
| POST | `/admin/competitions/bulk-delete` | Suppression en masse | ≤2 |
| PATCH | `/admin/competitions/{code}/publish` | Toggle publication | ≤4 |
| PATCH | `/admin/competitions/{code}/lock` | Toggle verrou FDM | ≤3 |
| PATCH | `/admin/competitions/{code}/status` | Changer statut (END ⇒ pose aussi `Verrou='O'`) | ≤3 — ≤2 si saison antérieure à la saison active |

> **`PATCH .../status`** — Comportement particulier :
> - `statut=END` : pose `Statut='END'` **et** `Verrou='O'` dans le même UPDATE. La réponse renvoie `{ code, statut, verrou }` (`verrou=true` quand END, `null` sinon) pour que le front mette à jour le toggle sans second appel.
> - Garde **saison antérieure** : si `niveau > 2` et que la saison concernée est strictement antérieure à la saison active → **403**. Seuls les profils ≤ 2 peuvent ainsi rouvrir (END→ATT) une compétition passée.
> - Sortir de END (END→ATT) ne déverrouille **pas** automatiquement ; le verrou se rebascule via le toggle dédié.

### 6.2 Images (réutilisation de l'API Operations)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/admin/operations/images/list?imageType=&q=` | Lister les images existantes (min 2 car., max 5 rés.) |
| POST | `/admin/operations/images/upload` | Upload fichier local (FormData) |
| POST | `/admin/operations/images/import-url` | Import depuis URL externe |

**Paramètres communs pour les images de compétition** :
- `imageType` : `logo_competition`, `bandeau_competition`, `sponsor_competition`
- `codeCompetition` : code de la compétition (ex: `N1H`)
- `saison` : code saison (ex: `2024-2025`)

### 6.3 Autres endpoints utilisés

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/admin/competitions-groups` | Liste des groupes |
| GET | `/admin/competitions-for-multi` | Compétitions disponibles pour MULTI |
| GET | `/admin/competitions/search` | Autocomplete saisons précédentes |
| GET | `/admin/competitions/{code}/from-season/{season}` | Données complètes pour import |

---

## 7. Schéma de données

### 7.1 Table kp_competition (colonnes pertinentes)

| Colonne | Type | Description |
|---------|------|-------------|
| Code | varchar(12) | PK, Code unique |
| Code_saison | varchar(10) | PK, Saison |
| Code_niveau | varchar(3) | INT / NAT / REG |
| Libelle | varchar(80) | Nom principal |
| Soustitre | varchar(80) | Titre public |
| Soustitre2 | varchar(80) | Catégorie |
| Web | varchar(80) | URL site web |
| BandeauLink | varchar(255) | Nom de fichier bandeau (ex: `B-N1H-2024-2025.jpg`) |
| LogoLink | varchar(255) | Nom de fichier logo |
| SponsorLink | varchar(255) | Nom de fichier sponsor |
| Bandeau_actif | char(1) | O/N |
| Logo_actif | char(1) | O/N |
| Sponsor_actif | char(1) | O/N |
| En_actif | char(1) | O/N |
| Titre_actif | char(1) | O/N |
| Kpi_ffck_actif | char(1) | O/N |
| Code_ref | varchar(20) | FK vers kp_groupe |
| GroupOrder | int | Ordre dans le groupe |
| Code_typeclt | varchar(5) | CHPT / CP / MULTI |
| points_grid | text | JSON grille points MULTI |
| multi_competitions | text | JSON codes compétitions MULTI |
| ranking_structure_type | varchar(10) | Type classement MULTI |
| Code_tour | int | Tour/Phase (1–10) |
| Qualifies | int | Nb équipes qualifiées |
| Elimines | int | Nb équipes éliminées |
| Points | varchar(10) | Barème (4-2-1-0 ou 3-1-0-0) |
| goalaverage | varchar(10) | gen / part |
| Statut | varchar(3) | ATT / ON / END — END ⇒ lecture seule (cf. § 11) |
| Publication | char(1) | O/N |
| Verrou | char(1) | O/N — Verrou FDM ; posé automatiquement au passage à END |
| commentairesCompet | text | Notes privées |

> **Note** : `BandeauLink`, `LogoLink`, `SponsorLink` stockent le nom de fichier seul. L'API retourne le chemin complet `/img/logo/{filename}` via `buildImageLink()`. Le composant picker travaille avec le nom de fichier seul et construit l'URL de prévisualisation via `legacyBaseUrl + /img/logo/`.

### 7.2 Table kp_groupe

| Colonne | Type | Description |
|---------|------|-------------|
| id | int | PK |
| Groupe | varchar(20) | Code groupe |
| Libelle | varchar(50) | Nom français |
| Libelle_en | varchar(50) | Nom anglais |
| section | int | Section (1=Inter, 2=Nat, ...) |
| ordre | int | Ordre dans section |
| Code_niveau | varchar(3) | Niveau par défaut |

---

## 8. Composants Vue (implémentation réelle)

### 8.1 Fichiers

```
sources/app4/pages/competitions/
└── index.vue                          # Page principale (liste + modals)

sources/app4/components/admin/
├── CompetitionAutocomplete.vue        # Autocomplete import saison précédente
├── CompetitionImagePicker.vue         # Picker 3 modes (existante/upload/URL)
├── CompetitionGroupedSelect.vue       # Select groupé pour MULTI
├── CompetitionMultiSelect.vue         # Sélection multiple compétitions
├── CompetitionSingleSelect.vue        # Sélection simple
└── PointsGridEditor.vue               # Éditeur grille de points MULTI

sources/app4/types/
└── competitions.ts                    # Types TypeScript

sources/api2/src/Trait/
└── CompetitionLockTrait.php           # Garde lecture seule END / verrou / saison passée (cf. § 11)
```

### 8.2 Type `CompetitionFormData`

```typescript
interface CompetitionFormData {
  code: string
  codeNiveau: 'INT' | 'NAT' | 'REG'
  libelle: string
  soustitre: string
  soustitre2: string
  codeRef: string
  groupOrder: number | null
  codeTypeclt: 'CHPT' | 'CP' | 'MULTI'
  codeTour: number
  qualifies: number
  elimines: number
  points: string
  goalaverage: string
  statut: 'ATT' | 'ON' | 'END'
  web: string
  enActif: boolean
  titreActif: boolean
  bandeauActif: boolean
  logoActif: boolean
  sponsorActif: boolean
  kpiFfckActif: boolean
  bandeauLink: string   // nom de fichier seul, ex: "B-N1H-2024-2025.jpg"
  logoLink: string
  sponsorLink: string
  pointsGrid: Record<string, number> | null
  multiCompetitions: string[]
  rankingStructureType: 'team' | 'club' | 'cd' | 'cr' | 'nation' | null
  commentairesCompet: string
}
```

### 8.3 Composant `AdminCompetitionImagePicker`

Props :
- `modelValue: string` — nom de fichier courant (v-model)
- `imageKind: 'bandeau_competition' | 'logo_competition' | 'sponsor_competition'`
- `competitionCode: string` — requis pour nommage normalisé
- `saison: string` — requis pour nommage normalisé
- `disabled?: boolean`

---

## 9. Sécurité

- Validation serveur : code unique par saison, libellé requis, JSON valide pour grille/MULTI
- Suppression bloquée si équipes/journées/matchs existants
- Upload/import images : validation MIME par magic bytes, limite 10 Mo, redimensionnement automatique
- Import URL : `filter_var(FILTER_VALIDATE_URL)` + validation contenu image côté serveur
- **Lecture seule END** : écritures (équipes, journées) refusées en 403 si la compétition est END ou verrouillée (`CompetitionLockTrait`, cf. [§ 11.1](#111-statut-end--lecture-seule-complète))
- **Saison antérieure** : changement de statut refusé en 403 pour les profils > 2 (cf. [§ 11.2](#112-clôture-à-lactivation-dune-nouvelle-saison))

## 10. Audit

Actions journalisées dans `kp_journal` :
- `Ajout Compet`
- `Modif Competition`
- `Upload Image` / `Import URL Image`
- `Publication competition`
- `Verrou Compet` (également posé lors du passage à END)
- `Statut Competition`
- `Change Saison Active` / `Cloture compétitions saison précédente` (page Opérations — cf. [§ 11.2](#112-clôture-à-lactivation-dune-nouvelle-saison))

---

## 11. Statut END et lecture seule

Deux mécanismes complémentaires figent les données : le **statut END** d'une compétition, et la **lecture seule des saisons antérieures** pour les profils > 2. Le second s'appuie sur le premier.

### 11.1 Statut END → lecture seule complète

Quand une compétition passe au statut **END** :

- Le backend (`AdminCompetitionsController::changeStatus`) pose `Statut='END'` **et** `Verrou='O'` en une seule opération.
- La compétition devient **lecture seule partout** : équipes, journées/phases, et présents (feuilles de présence) inclus.

**Implémentation (back) — refus 403 sur toute écriture** quand la compétition est END ou verrouillée, via `App\Trait\CompetitionLockTrait` :

| Méthode | Rôle |
|---------|------|
| `isCompetitionReadOnly($code, $season)` | `true` si `Verrou='O'` **ou** `Statut='END'` (les deux, pour rester robuste à d'éventuelles lignes END sans verrou) |
| `filterEditableGamedayIds($ids)` | Filtre une liste d'ids de journées pour ne garder que celles modifiables (utilisé par les opérations en masse) |

Contrôleurs couverts :
- `AdminGamedaysController` : `create`, `update`, `togglePublication`, `toggleType`, `inlineUpdate`, `duplicate`, `delete` + les 4 opérations en masse (publication / calendrier / officiels / suppression).
- `AdminTeamsController` : `pool-draw`, `colors`, `duplicate`, `update-logos`, `init-starters` (les `create` / `delete` / `bulk-delete` vérifiaient déjà `Verrou`).

**Implémentation (front)** :
- Page **Compétitions** : la modification (✏️) ouvre le formulaire en **consultation** (`isFormReadOnly` quand `statut === 'END'`) ; le toggle verrou est désactivé sur une compétition END.
- Page **Équipes** : `isReadOnly` (`verrou` ou `statut === 'END'`) désactive l'édition inline, les opérations spéciales, l'ajout/suppression et l'édition des propriétés.
- Page **Journées/Phases** : la liste pouvant mélanger plusieurs compétitions, le contrôle est **par ligne** via `canEditGameday(g)` (`g.competitionStatut !== 'END'`). Le DTO `Gameday` porte `competitionStatut`.

> Sortir de END (END→ATT) ne déverrouille **pas** automatiquement la compétition : le verrou reste posé tant qu'il n'est pas rebasculé manuellement.

### 11.2 Clôture à l'activation d'une nouvelle saison

Règle métier : **les profils > 2 ne peuvent rien créer/modifier/supprimer dans une saison antérieure à la saison active**. Plutôt qu'un garde global sur toutes les routes d'écriture, la règle s'applique via le statut END + un garde ciblé sur le changement de statut :

1. **Garde changement de statut** — `changeStatus` refuse (403) si `niveau > 2` et saison strictement antérieure à la saison active (`CompetitionLockTrait::isPastSeason()`, comparaison des codes saison comme chaînes : `"2024-2025" < "2025-2026"`). Côté front, `canCycleStatus` (Compétitions) et `canChangeStatus` (Classements) tombent à profil ≤ 2 en saison passée.

2. **Clôture à l'activation** (page **Opérations → Saisons**, profil ≤ 2 / `ROLE_ADMIN`) — à l'activation d'une nouvelle saison, l'ancienne saison active peut être clôturée : toutes ses compétitions non encore END passent à `Statut='END'` + `Verrou='O'`.
   - `GET /admin/operations/seasons/{code}/activate-preview` → `{ previousSeason, nonEndedCount, sections[] }` (compétitions non terminées **groupées par section**).
   - `PATCH /admin/operations/seasons/{code}/activate` accepte `{ endPreviousSeason: bool }` ; renvoie `{ code, endedCompetitions }`.
   - **Modale de confirmation** (`SeasonsTab.vue`) : si l'ancienne saison a des compétitions non terminées, elles sont **listées par section (codes)** avec un avertissement. L'opérateur choisit :
     - **clôturer** ces compétitions (case cochée par défaut) puis activer, ou
     - **annuler** pour corriger manuellement le statut de certaines compétitions avant de réactiver.

> Si la case est décochée, les compétitions passées restées non-END demeurent éditables (équipes/journées) par les profils > 2 : c'est un choix assumé par l'opérateur ≤ 2 qui a vu l'avertissement. Le **statut** de ces compétitions reste toutefois non modifiable par les profils > 2 (garde « changement de statut » ci-dessus, point 1).

---

**Document créé le** : 2026-02-01
**Dernière mise à jour** : 2026-06-29
**Statut** : ✅ Implémenté
