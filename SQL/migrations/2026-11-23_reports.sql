-- Migration : rapports officiels (FEATURE_DESIGNATIONS.md D12, D13, D18 ; ticket designations/09)
-- Date : 2026-11-23
-- kp_report : un rapport (bilan de journée, incident, jury d'appel, évaluation d'arbitrage), ses réponses en JSON
-- avec la version du modèle du catalogue (App\Report\ReportTypeCatalog).
-- kp_report_submission : une ligne par soumission, figée (réponses, données KPI, destinataires) ; le PDF d'une
-- soumission est rendu à la demande depuis elle, donc chaque PDF soumis reste disponible après une réouverture.
-- Idempotente. Application : make db_migrate file=SQL/migrations/2026-11-23_reports.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `kp_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(30) NOT NULL,
  `version_modele` smallint(6) NOT NULL,
  `perimetre_type` enum('COMPETITION','EVENEMENT','JOURNEE','MATCH') NOT NULL,
  `perimetre_id` varchar(20) NOT NULL,
  `saison` char(4) NOT NULL,
  `auteur_matric` int(11) unsigned DEFAULT NULL COMMENT 'Licence de l''auteur (arbitre dont un officiel saisit le rapport, D17)',
  `saisi_par` varchar(8) NOT NULL COMMENT 'Compte qui a saisi le rapport',
  `etat` enum('BROUILLON','SOUMIS') NOT NULL DEFAULT 'BROUILLON',
  `soumis_le` datetime DEFAULT NULL,
  `hors_delai` tinyint(1) NOT NULL DEFAULT 0,
  `reponses` longtext NOT NULL DEFAULT '{}',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_report_perimetre` (`perimetre_type`, `perimetre_id`),
  KEY `idx_report_saison` (`saison`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kp_report_submission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `numero` smallint(6) NOT NULL,
  `version_modele` smallint(6) NOT NULL,
  `reponses` longtext NOT NULL,
  `donnees_kpi` longtext NOT NULL COMMENT 'Données KPI pré-remplies au moment de la soumission (JSON)',
  `destinataires` text NOT NULL COMMENT 'Licences des destinataires (JSON)',
  `submitted_at` datetime NOT NULL,
  `submitted_by` varchar(8) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_report_numero` (`report_id`, `numero`),
  CONSTRAINT `fk_submission_report` FOREIGN KEY (`report_id`) REFERENCES `kp_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
