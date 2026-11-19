# app4 : tests de pages et composants, domaine par domaine

Status: claimed
Blocked by: —

Spec : [TEST_COVERAGE_STRATEGY.md](../../../DOC/developer/in-progress/TEST_COVERAGE_STRATEGY.md)

Projet `nuxt` en place (`tests/nuxt/`, exemple : `experimental-banner.spec.ts`). Ordre proposé, d'après les specs `DOC/specs/` : scoring (`PAGE_SCORING.md`), présence, auth/login, éditorial (`PAGE_CMS.md`), puis le reste. Un critère d'acceptation = un test, nommé par son ID. Relever le plancher à chaque lot.

## Comments

**Lot 1 — scoring, composants présentationnels** (`tests/nuxt/scoring-*.spec.ts`) : `StatusBadge`, `EventIcon`, `Shotclock`, `Penalties`, `Readiness`, `TimeAdjust`. 40 tests ; app4 9,9 % → 10,6 % de lignes, plancher relevé à 10,5.
Les textes sont lus via l'i18n de l'app (`tests/nuxt/helpers.ts`), pas copiés. Attention : l'environnement de test tourne en **anglais**, ne pas coder de libellé français en dur.

**Lot 2 — scoring** : `Board`, `TeamColumn`, `PeriodSelector` (40 tests). app4 10,6 % → 11,7 %, plancher 11,5. Le helper `t()` de `tests/nuxt/helpers.ts` échoue désormais sur une clé i18n absente (sinon une assertion « contient t(clé) » passe à tort).
Non testé dans `PeriodSelector` : le menu d'accès direct et sa modale de confirmation (menu rendu hors du composant, à traiter avec `AdminConfirmModal`).

Reste dans le scoring : `EventHistory`, `OfficialInput`, `CloseMatchModal`, `SettingsPanel`, `ShortcutsModal`, `DisplayOptionsModal` ; puis présence, auth/login, éditorial.
