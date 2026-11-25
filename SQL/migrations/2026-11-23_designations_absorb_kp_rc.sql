-- Migration : absorption de kp_rc dans kp_designation (FEATURE_DESIGNATIONS.md D4, RC-01, RC-02 ; ADR-0003)
-- Date : 2026-11-23
-- Prérequis : SQL/migrations/2026-11-22_designations.sql
--
-- 1. Chaque RC devient une Désignation `RC` : sur la compétition, ou sur le niveau national pour un RC « national
--    sans compétition » (Code_competition NULL ou '- CNA -'). Club figé = club actuel de la licence (D22).
-- 2. La table kp_rc est renommée kp_rc_avant_designations (sauvegarde) et remplacée par une VUE kp_rc en lecture
--    seule, pour que les pages legacy continuent de fonctionner (ADR-0002) ; leurs écritures sur kp_rc échouent.
-- Idempotente : rejouée, elle n'insère rien de plus (clé unique) et ne renomme pas une seconde fois.
-- Application : make db_migrate file=SQL/migrations/2026-11-23_designations_absorb_kp_rc.sql

SET NAMES utf8mb4;

SET @kp_rc_is_table = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kp_rc' AND TABLE_TYPE = 'BASE TABLE');

SET @copy_rc = IF(@kp_rc_is_table = 1,
  'INSERT IGNORE INTO kp_designation (saison, role, perimetre_type, perimetre_id, matric, club, autorite, ordre, created_by)
   SELECT rc.Code_saison, ''RC'',
          IF(rc.Code_competition IS NULL OR rc.Code_competition IN ('''', ''- CNA -''), ''NIVEAU'', ''COMPETITION''),
          IF(rc.Code_competition IS NULL OR rc.Code_competition IN ('''', ''- CNA -''), ''NATIONAL'', rc.Code_competition),
          rc.Matric, NULLIF(l.Numero_club, ''''), ''CNA'', rc.Ordre, ''MIGRATION''
   FROM kp_rc rc LEFT JOIN kp_licence l ON l.Matric = rc.Matric
   WHERE rc.Matric > 0',
  'SELECT 1');
PREPARE copy_rc FROM @copy_rc;
EXECUTE copy_rc;
DEALLOCATE PREPARE copy_rc;

SET @rename_rc = IF(@kp_rc_is_table = 1, 'RENAME TABLE kp_rc TO kp_rc_avant_designations', 'SELECT 1');
PREPARE rename_rc FROM @rename_rc;
EXECUTE rename_rc;
DEALLOCATE PREPARE rename_rc;

-- Mêmes colonnes et même jeu de caractères que l'ancienne table (jointures du legacy sur kp_competition, utf8mb3).
CREATE OR REPLACE VIEW `kp_rc` AS
SELECT d.id AS Id,
       IF(d.perimetre_type = 'NIVEAU', NULL, CONVERT(d.perimetre_id USING utf8mb3) COLLATE utf8mb3_general_ci) AS Code_competition,
       CONVERT(d.saison USING utf8mb3) COLLATE utf8mb3_general_ci AS Code_saison,
       d.ordre AS Ordre,
       d.matric AS Matric
FROM kp_designation d
WHERE d.role = 'RC';
