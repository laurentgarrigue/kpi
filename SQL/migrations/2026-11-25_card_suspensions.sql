-- Migration : cumuls de cartons et suspensions (FEATURE_CARD_SUSPENSIONS.md § 4 ; issue #424)
-- Date : 2026-11-25
-- kp_card_suspension : une suspension détectée par carton déclencheur (S8), notifiée une seule fois (S10), annulée
-- (conservée) quand la feuille de match est corrigée (S11). `actif` vaut 1 pour la suspension en vigueur et NULL une
-- fois annulée : l'unicité (carton_id, actif) garantit une seule suspension active par carton.
-- Idempotente. Application : make db_migrate file=SQL/migrations/2026-11-25_card_suspensions.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `kp_card_suspension` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `saison` char(4) NOT NULL,
  `section` int(11) NOT NULL COMMENT 'kp_groupe.section de la compétition (décompte par section, S3)',
  `matric` int(11) unsigned NOT NULL,
  `carton_id` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT 'kp_match_detail.Id du carton déclencheur',
  `match_id` int(10) unsigned NOT NULL,
  `journee_id` int(11) NOT NULL,
  `code_competition` varchar(12) NOT NULL,
  `regle` varchar(30) NOT NULL COMMENT 'Règle retenue (App\\Discipline\\SuspensionCatalog)',
  `regles` text NOT NULL COMMENT 'Toutes les règles déclenchées par ce carton (JSON)',
  `version_regles` smallint(6) NOT NULL,
  `decompte` smallint(6) NOT NULL COMMENT 'Décompte de la règle retenue (4e carton de la saison…)',
  `matchs` smallint(6) NOT NULL COMMENT 'Matchs que ce carton ajoute à la suspension',
  `matchs_journee` smallint(6) DEFAULT NULL COMMENT 'Total de la journée sportive pour une règle qui ne s''additionne pas sur la journée (S15)',
  `jury_appel` tinyint(1) NOT NULL DEFAULT 0,
  `actif` tinyint(1) DEFAULT 1 COMMENT '1 = en vigueur, NULL = annulée',
  `detectee_le` datetime NOT NULL,
  `notifiee_le` datetime DEFAULT NULL COMMENT 'Vide si enregistrée en mode --silent',
  `annulee_le` datetime DEFAULT NULL,
  `destinataires` text DEFAULT NULL COMMENT 'Licences notifiées (JSON)',
  `rapport_id` int(11) DEFAULT NULL COMMENT 'Brouillon de rapport de jury d''appel (kp_report)',
  `equipe_id` int(10) unsigned DEFAULT NULL COMMENT 'Composition où le statut S a été posé (S16)',
  `statut_avant` char(1) DEFAULT NULL COMMENT 'Statut du joueur avant S, restauré à la levée (S19)',
  `levee_le` datetime DEFAULT NULL,
  `levee_par` varchar(8) DEFAULT NULL COMMENT 'Compte qui a levé la suspension (profil 1-2)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_card_suspension_actif` (`carton_id`, `actif`),
  KEY `idx_card_suspension_saison` (`saison`, `matric`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
