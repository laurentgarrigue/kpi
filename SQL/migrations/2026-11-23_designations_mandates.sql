-- Migration : mandats générés par les Désignations (FEATURE_DESIGNATIONS.md D6, MAN-08 ; ticket designations/06)
-- Date : 2026-11-23
-- Un mandat généré porte le rôle et la saison qui l'ont produit ; un mandat saisi à la main a ces colonnes à NULL
-- et n'est jamais modifié par les Désignations. Un seul mandat généré par (compte, saison, rôle).
-- Idempotente. Application : make db_migrate file=SQL/migrations/2026-11-23_designations_mandates.sql

ALTER TABLE `kp_user_mandat`
  ADD COLUMN IF NOT EXISTS `designation_role` varchar(20) DEFAULT NULL COMMENT 'Rôle de Désignation qui a généré ce mandat',
  ADD COLUMN IF NOT EXISTS `designation_saison` char(4) DEFAULT NULL COMMENT 'Saison des Désignations',
  ADD UNIQUE KEY IF NOT EXISTS `uniq_generated_mandate` (`user_code`, `designation_saison`, `designation_role`);
