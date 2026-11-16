# 01: Affichage des cartons selon la saison (rouge simple et carton noir à partir de 2027)

**Status:** needs-triage

**Blocked by:** None (peut démarrer dès que la spec est écrite)

## Contexte

À partir de la saison **2027**, le règlement change l'appellation et l'apparence de deux cartons :

| Code BDD (`Id_evt_match`) | Jusqu'en 2026 | À partir de 2027 |
|---|---|---|
| `R` | Carton rouge **pour cumul** : rouge posé sur un jaune | Carton **rouge** simple |
| `D` | Carton **rouge définitif** : rouge avec « D » / « E » (EN), libellé « Rouge déf. », « RD » / « RE » | Carton **noir** (EN *Ejection card*), libellé « Noir » |

Les codes `V`, `J`, `R`, `D` ne changent pas : **aucune migration BDD**, les données historiques restent
valables. Seul l'affichage dépend de la **saison de la compétition** du match (`Code_saison`), jamais de la
date du jour. La console de scoring app4 le fait déjà (branche `claude/scoring-refactoring-strategy-3d43ac`).

**Hors périmètre** : le legacy (pages publiques `kp*.php`, admin `admin/*.php`, PDF `admin/Feuille*.php`,
overlays `sources/live/`) reste sur l'ancien affichage.

## Ce qu'il faut construire

Une règle unique « saison ≥ 2027 → cartons 2027 », partagée, plutôt que des `if` dispersés :

- **api2** : un helper (ex. `CardRules::forSeason(int $season)`) qui donne le jeu de cartons d'une saison ;
  les réponses qui alimentent un affichage de cartons exposent la saison (ou le jeu de cartons) quand elles
  ne le font pas déjà (ex. la feuille de match app2 `GameSheet.vue` ne connaît pas la saison aujourd'hui).
- **Front** : un utilitaire équivalent côté app2 / app3 (kpi-layer quand app2 le consommera) / app4, et un
  composant d'icône de carton unique par application qui prend le code et la saison.

## Points d'impact identifiés

- **app2** : `components/GameEventIcon.vue`, `GameSheet.vue` (en-têtes de colonnes, 2 fois), `TeamPlayerStats.vue`.
- **app3** : `components/team/TeamRoster.vue` (`red` / `red_final`), i18n `teams.col.redFinal`
  (« Rouges définitifs »), `components/medal/MedalStatView.vue` à vérifier.
- **app4** (hors console de scoring) : `pages/stats/index.vue` + i18n `stats.columns.rouge_definitif`,
  `pages/athletes/index.vue` (participations, i18n `rouge_def`), `utils/medals.ts`, `components/scoring/EventHistory.vue`
  sur `main` (à aligner sur la branche de scoring).
- **PDF Gotenberg** (api2) : `Pdf/Scoresheet/ScoresheetSheet.php` + `templates/pdf/scoresheet.html.twig`
  (colonne `definitive`), `Pdf/Document/CardsDocument.php`, libellés `config/pdf/labels.yaml`
  (`scoresheet.definitive`, `cards.definitive` = `RD` / `RE`), couleur noire dans les tokens PDF.
- **Données api2 inchangées** : `PublicController`, `GroupController`, `PublicSiteRepository`, `AdminStatsController`,
  `AdminRankingsController`, `AdminAthletesController`, `MatchMedals` comptent toujours `R` et `D` ; seuls les
  libellés exposés / noms de champs d'affichage changent éventuellement (garder les clés JSON pour la compat).

## Critères d'acceptation

- [ ] Un match d'une compétition 2026 affiche `R` en rouge sur jaune et `D` en rouge définitif, comme aujourd'hui (app2, app3, app4, PDF)
- [ ] Un match d'une compétition 2027 affiche `R` en carton rouge simple et `D` en carton noir, avec le libellé « carton noir » / « Ejection card » (fr / en)
- [ ] Les tableaux de stats/cartons d'une saison 2027 titrent la colonne `D` « Noirs » ; ceux d'une saison ≤ 2026 gardent « Rouges définitifs »
- [ ] La feuille de match PDF et le PDF des cartons d'un événement 2027 titrent la colonne `D` « N » (libellé à valider) en noir ; 2026 inchangé
- [ ] La règle de bascule est en un seul endroit par brique (api2, chaque app) et testée en 2026 / 2027 (limites)
- [ ] Aucune migration BDD, aucun changement du legacy
- [ ] Suites de tests api2 (PHPUnit + PHPStan), app2, app3, app4 vertes

## Questions ouvertes (à trancher avant `ready-for-agent`)

1. **Vues multi-saisons** (carrière d'un athlète dans app4 `athletes`, stats sur plusieurs saisons, historiques app3) :
   une seule colonne `D` mélange « rouges définitifs » d'avant 2027 et « noirs » à partir de 2027. Proposition :
   libellé neutre « Exclusions définitives » (icône selon la saison la plus récente), ou deux colonnes ?
2. **Compétitions internationales / saison décalée** : la bascule suit bien `Code_saison` de la compétition, pas la date du match ?
3. **Libellés PDF** abrégés : « N » / « B » (Black) pour le noir ? Le « R » 2027 reste « R ».
4. **Fair-play** (`AdminStatsController`, médailles `DisciplineStat`) : la pondération `R` = `D` = 4 reste-t-elle valable en 2027 ?
5. **Overlays vidéo / TV** (`sources/live/`, alimentés par l'event-cache-worker) : considérés legacy donc exclus — à confirmer, car ils seront visibles en direct dès J1 2027 (13-14 mars).

## Comments
