-- Migration: scoring_live_state — côtés du terrain (switch)
-- Date: 2026-11-28
-- Docs: DOC/specs/PAGE_SCORING.md §0.18 (D2, D3, D4, D15)
--
-- Les côtés courants ne sont PAS stockés : ils se calculent à partir du côté de départ, de la
-- période et des inversions manuelles (ScoringRules::sidesFor, app4 utils/scoringSides.ts).
--   sides_start     : côté de départ de l'équipe A, vu de l'arbitre principal et de la table ;
--                     NULL tant que la table ne l'a pas vérifié au coup d'envoi (D15).
--   sides_overrides : périodes inversées à la main, séparées par des virgules ('' = aucune).
-- Idempotente (MariaDB : ADD COLUMN IF NOT EXISTS). Additive : aucun impact legacy.

ALTER TABLE `scoring_live_state`
  ADD COLUMN IF NOT EXISTS `sides_start` enum('A_LEFT','A_RIGHT') DEFAULT NULL AFTER `heure_fin`,
  ADD COLUMN IF NOT EXISTS `sides_overrides` varchar(255) NOT NULL DEFAULT '' AFTER `sides_start`;

-- Vérification
SHOW COLUMNS FROM `scoring_live_state` LIKE 'sides_%';
