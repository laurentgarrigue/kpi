-- Médailles de match : réglages modifiables depuis l'admin (profil 1) — FEATURE_MATCH_MEDALS.md § 5.4, § 6.3.
-- Idempotente (CREATE TABLE IF NOT EXISTS) : peut être rejouée sans effet.
-- Application : docker exec -i <db> mariadb -uroot -p<pass> <base> < SQL/migrations/2026-11-11_public_site_match_medals_settings.sql
-- Aussi chargée par les fixtures de test d'api2 (boucle *_public_site_*.sql), après 2026-11-09_public_site_match_medals.sql.

SET NAMES utf8mb4;

-- Clé / valeur JSON : `thresholds` = remplacements des seuils du fichier match_medals.yaml ;
-- `deleted_seasons` = saisons dont les calculs ont été supprimés (ni sync ni validation ne les recalculent).
CREATE TABLE IF NOT EXISTS `kp_medal_setting` (
  `name` varchar(40) NOT NULL,
  `value` text NOT NULL COMMENT 'JSON',
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
