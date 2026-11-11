-- Médailles de match : But de la délivrance · Tiebreaker (joueur) — FEATURE_MATCH_MEDALS.md § 3.2, MED-81.
-- Idempotente (INSERT IGNORE) : ne modifie jamais un élément déjà présent. Non publiée au départ : à publier depuis
-- app4 › Médailles › Catalogue, après avoir recalculé les saisons voulues (rules_version 3).
-- Application : make db_migrate file=SQL/migrations/2026-11-11_public_site_match_medals_tiebreaker.sql
-- Aussi chargée par les fixtures de test d'api2 (boucle *_public_site_*.sql).

SET NAMES utf8mb4;

INSERT IGNORE INTO `kp_medal_catalog`
  (`code`, `type`, `scopes`, `family`, `data_level`, `name_fr`, `name_en`, `explanation_fr`, `explanation_en`, `published_app2`, `published_app3`, `icon`, `sort_order`) VALUES
('tiebreaker', 'medal', 'P', 'reaction', 'T', 'But de la délivrance', 'Tiebreaker', 'Marque le dernier but du match alors que le score est à égalité, pour une victoire d’un but (le but en or en fait partie).', 'Scores the last goal of the game with the score tied, for a one-goal win (a golden goal is one).', 0, 0, 'tiebreaker', 85);
