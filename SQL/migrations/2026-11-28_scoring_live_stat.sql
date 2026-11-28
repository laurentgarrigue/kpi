-- Migration: scoring_live_stat — tirs et arrêts saisis à la console (statistiques non officielles)
-- Date: 2026-11-28
-- Docs: DOC/specs/PAGE_SCORING.md §0.19 (T1–T8)
--
-- Table DÉDIÉE, volontairement hors scoring_live_event : celle-ci alimente la consolidation
-- kp_match_detail et la vue v_match_detail (médailles, suspensions, désignations). Les tirs et
-- arrêts ne doivent apparaître dans aucune de ces règles, ni sur la feuille de match.
--   kind  : SHOT (tir non marqué et non arrêté par le gardien) / SAVE (arrêt du gardien)
--   team  : équipe déduite de la possession (SHOT = attaque, SAVE = défense) ; '' = inconnue
--   id_player / numero : tireur ou gardien, facultatifs
-- Idempotente (IF NOT EXISTS). Additive : aucun impact legacy.

CREATE TABLE IF NOT EXISTS `scoring_live_stat` (
  `uid` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `id_match` int(10) UNSIGNED NOT NULL,
  `kind` enum('SHOT','SAVE') NOT NULL,
  `periode` varchar(4) DEFAULT NULL,
  `temps` time DEFAULT NULL,
  `team` char(1) NOT NULL DEFAULT '',
  `id_player` varchar(25) DEFAULT NULL,
  `numero` varchar(4) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  PRIMARY KEY (`uid`),
  KEY `idx_scoring_stat_match` (`id_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vérification
SHOW TABLES LIKE 'scoring_live_stat';
