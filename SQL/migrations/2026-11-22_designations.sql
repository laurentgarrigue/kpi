-- Migration : Désignations (officiels et responsables)
-- Date : 2026-11-22
-- Spec : DOC/specs/FEATURE_DESIGNATIONS.md § 8.1 (D2, D22, D23) ; ADR-0003
--
-- Idempotente (CREATE TABLE IF NOT EXISTS) : peut être rejouée sans effet.
-- Application : make db_migrate file=SQL/migrations/2026-11-22_designations.sql
-- Aussi présente dans SQL/fixtures/schema.sql pour les tests d'api2.

SET NAMES utf8mb4;

-- Une Désignation : un rôle du catalogue (App\Designation\RoleCatalog) tenu par un licencié sur un périmètre.
CREATE TABLE IF NOT EXISTS `kp_designation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `saison` char(4) NOT NULL,
  `role` varchar(20) NOT NULL COMMENT 'Code du catalogue des rôles',
  `perimetre_type` enum('NIVEAU','COMPETITION','EQUIPE','EVENEMENT','JOURNEE') NOT NULL,
  `perimetre_id` varchar(20) NOT NULL COMMENT 'NATIONAL, code compétition, id équipe, événement ou journée',
  `matric` int(11) unsigned NOT NULL COMMENT 'Licence (kp_licence.Matric)',
  `club` varchar(6) DEFAULT NULL COMMENT 'Club fournisseur figé à la création (D22)',
  `autorite` enum('CNA','ORGANISATEUR') NOT NULL DEFAULT 'CNA' COMMENT 'D23',
  `ordre` smallint(6) NOT NULL DEFAULT 1,
  `qualite` varchar(100) DEFAULT NULL COMMENT 'Précision libre (ex. représentant FFCK au jury d''appel)',
  `created_by` varchar(8) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_designation` (`saison`, `role`, `perimetre_type`, `perimetre_id`, `matric`),
  KEY `idx_perimetre` (`perimetre_type`, `perimetre_id`),
  KEY `idx_matric` (`matric`, `saison`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
