-- Médailles de match : « Dernière minute » devient « Dernières secondes » (late_winner) — FEATURE_MATCH_MEDALS.md § 3.2.
-- La fenêtre est réglée en secondes (lateWindowSeconds, 30 par défaut) : « minute » était trompeur.
-- Idempotente ; ne touche pas un nom déjà changé depuis app4 › Médailles › Catalogue. Aucun recalcul nécessaire.
-- Application : make db_migrate file=SQL/migrations/2026-11-11_public_site_match_medals_late_winner_name.sql

SET NAMES utf8mb4;

UPDATE `kp_medal_catalog` SET `name_fr` = 'Dernières secondes' WHERE `code` = 'late_winner' AND `name_fr` = 'Dernière minute';
