-- Médailles de match : raison pour laquelle la timeline d'un match éligible n'est pas fiable — FEATURE_MATCH_MEDALS.md § 5.1.
-- score_mismatch | untimed | period_start | same_instant ; NULL pour un match fiable ou non éligible.
-- Idempotente. Les lignes existantes restent à NULL jusqu'au recalcul de leur saison (la rules_version du code passe à 3).
-- Application : make db_migrate file=SQL/migrations/2026-11-11_public_site_match_medals_timeline_issue.sql
-- Aussi chargée par les fixtures de test d'api2 (boucle *_public_site_*.sql).

ALTER TABLE `kp_match_insight`
  ADD COLUMN IF NOT EXISTS `timeline_issue` varchar(20) DEFAULT NULL AFTER `timeline_reliable`;
