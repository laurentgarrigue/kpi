# 02: Import aligné sur le PCE

**What to build:** après chaque import PCE, les CR, CD et Clubs de KPI reflètent le PCE (seule source de vérité) : tous les nouveaux Clubs sont créés au même import, les libellés et rattachements des structures existantes sont mis à jour, et les CD d'outre-mer sont correctement convertis. Voir la spec, section « Import PCE ». Un cycle TDD par comportement, via le seam du ticket 01.

**Blocked by:** 01 (Import PCE depuis un fichier local)

**Status:** ready-for-agent

- [x] Tous les Clubs du PCE absents de KPI sont créés au même import (plus un seul par exécution), chacun rattaché à un seul CD
- [x] Un Club dont le libellé ou le CD change dans le PCE est mis à jour à l'import
- [x] Un CD dont le libellé ou le CR change dans le PCE est mis à jour à l'import ; idem pour le libellé d'un CR
- [x] Les champs propres à KPI d'un Club (coordonnées, adresse, site web, email) ne sont jamais modifiés par l'import
- [x] Les CD d'outre-mer sont convertis 975 → `9E00`, 976 → `9F00`, 986 → `9I00` (971–974 et 988 inchangés), pour les codes CD comme pour les codes Club
- [x] Les filtres existants sont conservés : Clubs `0`/`0000` et structures `98` exclus, saison courante uniquement
- [x] Les structures absentes du PCE ne sont ni supprimées ni modifiées par l'import
- [x] La suite de tests api2 et PHPStan passent
