# Les règles métier sont des catalogues en code dans api2, que app4 reçoit

Chaque règle métier qui change avec le règlement est définie à **un seul endroit**, dans une classe `final` d'api2 propre à son domaine (`…Rules`, `…Catalog`). Cette classe porte la documentation de référence et des tests unitaires. app4 reçoit la règle active par l'API et ne la réimplémente pas. Les catalogues des rôles de désignation et des types de rapport officiel ([FEATURE_DESIGNATIONS.md](../../../specs/FEATURE_DESIGNATIONS.md)) suivent ce modèle et sont **versionnés**, parce que des documents remplis avec une ancienne version doivent rester lisibles.

Catalogues existants au 2026-11-22 :

| Catalogue | Fichier | Contenu |
|---|---|---|
| Joueur en règle | `src/Eligibility/PlayerEligibilityRules.php` ([PLAYER_ELIGIBILITY_RULES.md](../PLAYER_ELIGIBILITY_RULES.md)) | Critères par niveau de compétition : saison et type de licence, certificat CK, pagaie couleur, surclassement ; blocage ou alerte |
| Catégorie d'âge | `src/AgeCategory/AgeCategoryRule.php` + table `kp_categorie` | Dérivation de la catégorie depuis l'année de naissance et la saison |
| Règles de jeu du scoring | `src/Scoring/ScoringRules.php` | Périodes, prolongations, progression des cartons, exclusions, shot-clock. **Exception** : il a un miroir TypeScript `app4/utils/scoringRules.ts`, tenu à jour à la main avec des suites de tests jumelles, car la console doit marcher hors ligne |
| Médailles | `src/MatchMedals/Catalog/MedalCatalog.php` + seuils `config/packages/match_medals.yaml` | Règles, seuils par saison, libellés éditables (`kp_medal_catalog`) |
| Contrôle équipement | `src/Scrutineering/EquipmentRules.php` | États des contrôles et bornes |
| Documents PDF | `src/Pdf/PdfCatalog.php` | Documents, rôle minimal, options, accès public |
| Affichage des compétitions | `src/PublicResults/CompetitionRules.php`, `src/PublicSite/PublicSiteRules.php` | Règles d'affichage public reprises du legacy |
| Éditorial et formulaires | `src/Cms/CmsRules.php`, `src/Forms/FormRules.php` | Règles du CMS et des formulaires d'inscription |
| Journal | `src/Journal/JournalCategories.php` | Familles d'actions du journal d'activité |

Il n'existe **aucun catalogue** de notifications ou de cumuls de cartons (suspension, alerte après N cartons).

## Considered Options

- **Règles paramétrables dans app4** (tables de réglages, constructeur) : réservé aux contenus éditoriaux (libellés des médailles, formulaires d'inscription). Pour une règle qui conditionne un calcul ou un droit, on préfère une évolution relue et testée.

## Consequences

- Changer une règle, c'est une PR sur un seul fichier et ses tests ; app4 suit sans modification.
- Un miroir côté client n'est admis que pour un besoin hors ligne, avec des tests jumeaux.
