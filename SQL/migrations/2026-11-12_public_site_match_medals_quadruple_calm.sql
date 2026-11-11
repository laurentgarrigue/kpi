-- Médailles de match : Quadruplé, Quadruplé consécutif (joueur) et Calme plat (arbitre) — FEATURE_MATCH_MEDALS.md § 3.2, § 3.5.
-- Idempotente (INSERT IGNORE) : ne modifie jamais un élément déjà présent. Non publiés au départ : à publier depuis
-- app4 › Médailles › Catalogue, après avoir recalculé les saisons voulues (la rules_version du code passe à 2).
-- Application : docker exec -i <db> mariadb -uroot -p<pass> <base> < SQL/migrations/2026-11-12_public_site_match_medals_quadruple_calm.sql
-- Aussi chargée par les fixtures de test d'api2 (boucle *_public_site_*.sql).

SET NAMES utf8mb4;

INSERT IGNORE INTO `kp_medal_catalog`
  (`code`, `type`, `scopes`, `family`, `data_level`, `name_fr`, `name_en`, `explanation_fr`, `explanation_en`, `published_app2`, `published_app3`, `icon`, `sort_order`) VALUES
('quadruple', 'medal', 'P', 'finition', 'S', 'Quadruplé', 'Four-goal Haul', 'Marque au moins {quadrupleGoals} buts dans le match.', 'Scores at least {quadrupleGoals} goals in the game.', 0, 0, 'quadruple', 175),
('natural_quadruple', 'medal', 'P', 'finition', 'T', 'Quadruplé consécutif', 'Natural Four-goal Haul', 'Marque {quadrupleGoals} buts consécutifs du match, sans autre but entre eux.', 'Scores {quadrupleGoals} goals in a row of the game, with no other goal between them.', 0, 0, 'natural_quadruple', 185),
('ref_calm_game', 'medal', 'R', 'arbitrage', 'S', 'Calme plat', 'Dead Calm', 'Arbitre un match sans aucun but ni aucun carton.', 'Referees a game without a single goal or card.', 0, 0, 'ref_calm_game', 405);
