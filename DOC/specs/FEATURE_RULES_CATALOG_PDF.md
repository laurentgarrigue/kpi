# Spécification - État PDF des catalogues de règles métier

> **Statut** : ✅ Implémenté (2026-11-25) — issue [#423](https://github.com/laurentgarrigue/kpi/issues/423).
> **Document** : `rules-catalog` du catalogue PDF ([PDF_GOTENBERG.md](PDF_GOTENBERG.md) § 7.5), rendu par Gotenberg.
> **Droits** : profil 1 uniquement ([DROITS_PAR_PROFIL.md](DROITS_PAR_PROFIL.md) « Documents PDF »).
> **Appelé depuis** : app4, Opérations › Système › « Règles métier » ([PAGE_OPERATIONS.md](PAGE_OPERATIONS.md)).

---

## 1. Besoin

Les règles métier qui changent avec le règlement sont des **catalogues en code** dans api2 (une classe `final` par
domaine, `…Rules` / `…Catalog`). Le profil 1 doit pouvoir en produire un **état PDF des valeurs** pour :

- les soumettre à validation (bureau CNA) ;
- proposer leurs évolutions d'une saison à l'autre, en partant de l'état en vigueur.

Le document décrit **les valeurs appliquées par le code**, pas le règlement fédéral : ce qui figure dans le PDF est
ce que KPI contrôle réellement.

## 2. Catalogues couverts

| Code (`catalog=`) | Catalogue | Source(s) | Dépend de la saison |
|---|---|---|---|
| `player-eligibility` | Joueur en règle | `src/Eligibility/PlayerEligibilityRules.php` | oui (pagaie nationale signalée sans bloquer dès 2027) |
| `age-categories` | Catégories d'âge | `src/AgeCategory/AgeCategoryRule.php`, table `kp_categorie` | oui (années de naissance) |
| `scoring` | Règles de jeu du scoring | `src/Scoring/ScoringRules.php` | non |
| `card-suspensions` | Cumuls de cartons et suspensions | `src/Discipline/SuspensionCatalog.php` ([FEATURE_CARD_SUSPENSIONS.md](FEATURE_CARD_SUSPENSIONS.md)) | oui (version en vigueur pour la saison) |
| `medals` | Médailles et seuils | `src/MatchMedals/Catalog/MedalCatalog.php`, `config/packages/match_medals.yaml`, tables `kp_medal_catalog`, `kp_medal_season` | oui (seuils figés au calcul de la saison) |
| `equipment` | Contrôle équipement | `src/Scrutineering/EquipmentRules.php` | non |
| `pdf-documents` | Documents PDF | `src/Pdf/PdfCatalog.php` | non |

Catalogues **à venir** (rôles de désignation, types de rapport officiel) : ils rejoignent le
document en implémentant la méthode commune (§ 3), sans toucher au document PDF.

## 3. Méthode commune : la description d'un catalogue

Chaque catalogue est accompagné d'un **descripteur** (`App\RuleCatalog\RuleCatalogDescriber`), rangé dans le
namespace du catalogue, qui expose une description lisible de ses valeurs :

- `code()` : identifiant stable (colonne « Code » du § 2) ;
- `describe(string $season)` : un `RuleCatalogDescription` = titre, version (ou null), fichiers source, sections
  (tableaux, couples libellé / valeur, lignes de texte).

Le descripteur **lit le catalogue** (constantes publiques, fonctions de la règle) et ne recopie jamais une valeur :
quand une règle n'est exposée que par une fonction (ex. `ScoringRules::nextPeriod`), le descripteur appelle la
fonction pour en déduire le tableau (enchaînement des périodes, matrice des cartons…). Un accesseur public est ajouté
au catalogue quand une valeur n'était lisible que dans une constante privée.

Les descripteurs sont des services taggés (`app.rule_catalog_describer`) ; l'ordre du document suit leur priorité,
dans l'ordre du § 2.

## 4. Le document `rules-catalog`

- Route : `GET /api2/admin/pdf/rules-catalog?season=AAAA[&catalog=<code>]` (thème admin, pas de version publique).
- Rôle : `ROLE_SUPER_ADMIN` (profil 1).
- Sans `catalog` : **sommaire** (catalogue, version, fichiers source) puis chaque catalogue sur une nouvelle page.
- Avec `catalog` : ce catalogue seul, sans sommaire.
- Chaque catalogue commence par un cartouche : **saison**, **version** (si le catalogue en a une), **fichier(s)
  source**, **date d'édition** (fuseau `tz`, Europe/Paris par défaut).
- Pied de page « document interne » (comme le document de travail du classement national des clubs).
- Langue : le contenu des catalogues est rédigé **en français** (langue du règlement et du bureau CNA) ; `lang=en`
  ne traduit que les libellés communs (titre, sommaire, cartouche).
- Nom de fichier : `rules-catalog_<saison>[_<code>]_<lang>.pdf`.

### 4.1 Contenu par catalogue

| Catalogue | Sections |
|---|---|
| Joueur en règle | Critères par niveau (contrôle bloquant ou alerte, licence de la saison, types de licence, certificat CK, pagaie minimale et son caractère bloquant pour la saison, surclassement) ; échelle des pagaies ; compétitions exigeant un surclassement et catégories exemptées ; forçage (profil maximal, statuts autorisés à l'ajout et au changement, statut donné à la copie) ; détermination du niveau |
| Catégories d'âge | Table `kp_categorie` : code, libellé, âges, **années de naissance pour la saison** (déduites de la règle) ; bornes de plausibilité ; dates conventionnelles « inconnue » |
| Règles de jeu du scoring | Enchaînement des périodes par type de match ; durées et pauses par défaut ; codes affichés ; cartons (chrono de pénalité, levée anticipée, retour du joueur) ; matrice de progression des cartons ; pénalités simultanées par équipe ; machine d'états du shot-clock |
| Cumuls de cartons et suspensions | Version en vigueur pour la saison (« aucune version » avant la première saison couverte) ; sections couvertes ; règles dans l'ordre de départage : cartons comptés, compté sur (match, journée, saison), seuil, redéclenchement, matchs de suspension, jury d'appel |
| Médailles et seuils | Version des règles ; seuils de la saison (copie figée si la saison a été calculée, sinon seuils en vigueur) ; éléments actifs du catalogue (code, nom, type, famille, explication rendue avec les seuils) |
| Contrôle équipement | Paramètres contrôlés et valeur maximale ; signification des états |
| Documents PDF | Pour chaque document : rôle minimal admin, options soumises à un rôle, version publique |

## 5. Critères d'acceptation

Chaque critère donne lieu à au moins un test nommé d'après son identifiant.

- **RCAT-01** : chaque catalogue du § 2 a un descripteur dont le `code()` est celui du tableau ; les codes sont uniques.
- **RCAT-02** : chaque description a un titre, au moins un fichier source existant dans le dépôt et au moins une section non vide.
- **RCAT-03** (joueur en règle) : la description reprend `PlayerEligibilityRules::RULES` (types de licence, pagaie
  minimale, contrôle bloquant / alerte) ; pour la saison 2027 la pagaie nationale est indiquée « signalée sans
  bloquer », pour 2026 « bloquante ».
- **RCAT-04** (catégories d'âge) : avec la table FFCK (`CAD` 15–16 ans), la saison 2027 donne les années de
  naissance 2011–2012 ; chaque année n'appartient qu'à une catégorie.
- **RCAT-05** (scoring) : l'enchaînement d'un match d'élimination est M1 → M2 → P1 → P2 → … ; un carton rouge ne
  lève pas sa pénalité sur but encaissé et le joueur ne revient pas ; un jaune après un jaune est refusé ; 2 pénalités
  simultanées au plus.
- **RCAT-06** (médailles) : les seuils décrits sont ceux de `MedalCatalog::thresholdsOf(saison)` ; l'explication
  d'un élément est rendue avec ces seuils ; un élément retiré n'apparaît pas.
- **RCAT-07** (équipement, documents PDF) : les maxima viennent d'`EquipmentRules`, les rôles de `PdfCatalog` (y
  compris `rules-catalog` → `ROLE_SUPER_ADMIN`).
- **RCAT-08** : sans `catalog`, le document contient un sommaire listant tous les catalogues puis chaque catalogue
  sur une nouvelle page ; avec `catalog=scoring`, seul le scoring figure, sans sommaire.
- **RCAT-09** : chaque catalogue porte la saison, la version (si elle existe), les fichiers source et la date d'édition.
- **RCAT-10** : `catalog` inconnu → 400.
- **RCAT-11** : `rules-catalog` exige `ROLE_SUPER_ADMIN` (profil 2 → 403) ; il n'a pas de version publique.
- **RCAT-12** (app4) : le lien n'est montré qu'au profil 1 (`canGetAdminPdf(1, 'rules-catalog')`).
- **RCAT-13** (cumuls de cartons) : la version décrite est `SuspensionCatalog::forSeason(saison)`, règles dans son
  ordre ; avant la première saison couverte (2026), la description l'indique, sans version.

## 6. Hors périmètre

- Comparaison automatique entre deux saisons (diff) : le profil 1 édite deux états et les compare.
- Catalogues d'affichage public, éditorial et journal (`CompetitionRules`, `PublicSiteRules`, `CmsRules`,
  `FormRules`, `JournalCategories`) : règles techniques, pas soumises au bureau CNA. Ils pourront rejoindre le
  document avec un descripteur.
- Édition des valeurs depuis le PDF ou app4 : changer une règle reste une PR sur le catalogue et ses tests.

## 7. Décisions (validées le 2026-11-26)

- **D1** : contenu des catalogues en français uniquement ; `lang=en` ne traduit que le titre, le sommaire et le cartouche.
- **D2** : les catalogues d'affichage public, éditorial et journal restent hors du document (§ 6).
- **D3** : app4 tient sa propre liste des catalogues (`RULE_CATALOGS`, `utils/pdf.ts`), à garder alignée sur les
  descripteurs d'api2 (`RuleCatalogs`).
