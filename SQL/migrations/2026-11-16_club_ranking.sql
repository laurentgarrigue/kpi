-- Migration : classement national des clubs
-- Date : 2026-11-16
-- Spec : DOC/specs/PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 9
--
-- Idempotente (CREATE TABLE IF NOT EXISTS, INSERT IGNORE) : peut être rejouée sans effet ; elle ne modifie jamais
-- un barème déjà présent (il se modifie ensuite dans l'admin, profil 1).
-- Application : docker exec -i <db> mariadb -uroot -p<pass> <base> < SQL/migrations/2026-11-16_club_ranking.sql
-- Aussi chargée par les fixtures de test d'api2 (CI et `make api2_test_fixtures`), entre schema.sql et data.sql.

SET NAMES utf8mb4;

-- Une saison du classement : état, périmètre national, date de référence du dernier calcul (§ 5.1, § 8).
CREATE TABLE IF NOT EXISTS `kp_club_ranking_season` (
  `season` char(4) NOT NULL,
  `status` enum('draft','computed','validated','published') NOT NULL DEFAULT 'draft',
  `top_n` smallint(6) NOT NULL DEFAULT 40 COMMENT 'Clubs du PDF public',
  `national_prefixes` varchar(100) NOT NULL DEFAULT 'N,CF' COMMENT 'Préfixes des compétitions nationales (§ 5.2)',
  `excluded_competitions` varchar(255) NOT NULL DEFAULT '' COMMENT 'Codes exclus du périmètre national',
  `stale` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Barème ou saisies modifiés depuis le dernier calcul',
  `computed_at` datetime DEFAULT NULL COMMENT 'Date de référence',
  `computed_by` varchar(8) DEFAULT NULL,
  `alerts` mediumtext DEFAULT NULL COMMENT 'JSON des alertes du dernier calcul',
  `validated_at` datetime DEFAULT NULL,
  `validated_by` varchar(8) DEFAULT NULL,
  PRIMARY KEY (`season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Barème : une rubrique de l'article 7 des annexes de la saison (§ 3.1).
CREATE TABLE IF NOT EXISTS `kp_club_ranking_rule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season` char(4) NOT NULL,
  `article` varchar(5) NOT NULL COMMENT '7.1 … 7.4',
  `position` tinyint(4) NOT NULL COMMENT 'Rang dans le tableau : code = article.position',
  `label` varchar(300) NOT NULL COMMENT 'Texte exact de l''annexe',
  `short_label` varchar(40) NOT NULL,
  `points` int(11) NOT NULL,
  `calc_type` varchar(30) NOT NULL,
  `params` text NOT NULL COMMENT 'JSON',
  `note` varchar(500) NOT NULL DEFAULT '' COMMENT 'Note d''interprétation',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rule` (`season`, `article`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories de l'annexe et compétitions KPI de la saison (§ 3.2). `entry_codes` : codes séparés par des virgules,
-- `*` final = préfixe (N15* = N15, N15A, N15_2…).
CREATE TABLE IF NOT EXISTS `kp_club_ranking_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season` char(4) NOT NULL,
  `code` varchar(20) NOT NULL,
  `label` varchar(80) NOT NULL,
  `entry_codes` varchar(500) NOT NULL DEFAULT '',
  `final_code` varchar(12) DEFAULT NULL,
  `regular_code` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category` (`season`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lignes de détail du dernier calcul (§ 2) : remplacées à chaque calcul, figées à la validation (instantané).
CREATE TABLE IF NOT EXISTS `kp_club_ranking_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season` char(4) NOT NULL,
  `rule_code` varchar(10) NOT NULL,
  `club_code` varchar(6) NOT NULL,
  `points` int(11) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `source_type` varchar(20) NOT NULL,
  `source_id` varchar(40) NOT NULL,
  `matric` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_season_club` (`season`, `club_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saisies des rubriques manuelles (7.1.4, 7.2.5, 7.3.4… § 7) : un licencié ou directement un club.
CREATE TABLE IF NOT EXISTS `kp_club_ranking_entry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season` char(4) NOT NULL,
  `rule_code` varchar(10) NOT NULL,
  `matric` int(11) UNSIGNED DEFAULT NULL,
  `club_code` varchar(6) DEFAULT NULL,
  `detail` varchar(200) NOT NULL DEFAULT '',
  `created_by` varchar(8) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_season_rule` (`season`, `rule_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajustements : ajout ou retrait de points, motif obligatoire (§ 7). Conservés par les recalculs.
CREATE TABLE IF NOT EXISTS `kp_club_ranking_adjustment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season` char(4) NOT NULL,
  `rule_code` varchar(10) NOT NULL,
  `club_code` varchar(6) NOT NULL,
  `points` int(11) NOT NULL,
  `reason` varchar(300) NOT NULL,
  `created_by` varchar(8) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_season` (`season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ententes déclarées : clubs associés d'une équipe (kp_equipe.Numero) pour une saison (N-7).
CREATE TABLE IF NOT EXISTS `kp_club_ranking_entente` (
  `season` char(4) NOT NULL,
  `team_number` int(11) NOT NULL,
  `club_code` varchar(6) NOT NULL,
  PRIMARY KEY (`season`, `team_number`, `club_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rapprochements nom saisi (délégué, juge arbitre) → licencié, réutilisés d'une saison à l'autre (N-5).
CREATE TABLE IF NOT EXISTS `kp_club_ranking_alias` (
  `name` varchar(80) NOT NULL COMMENT 'Nom normalisé',
  `matric` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Publications : instantané figé du classement publié (§ 8 étapes 5-6). Une saison repassée en brouillon garde ses
-- publications précédentes.
CREATE TABLE IF NOT EXISTS `kp_club_ranking_publication` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season` char(4) NOT NULL,
  `version` int(11) NOT NULL,
  `published_at` datetime NOT NULL,
  `published_by` varchar(8) DEFAULT NULL,
  `payload` mediumtext NOT NULL COMMENT 'JSON : barème, classement, lignes, ajustements',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_publication` (`season`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Barème 2026 (annexes 2026, article 7 ; spec § 4). Les catégories sont pré-remplies avec les codes connus et
-- doivent être vérifiées dans l'admin avant le premier calcul.
INSERT IGNORE INTO `kp_club_ranking_season` (`season`, `status`) VALUES ('2026', 'draft');

INSERT IGNORE INTO `kp_club_ranking_category` (`season`, `code`, `label`, `entry_codes`, `final_code`, `regular_code`) VALUES
('2026', 'N1H', 'N1H', 'N1H,NPOH', NULL, 'N1H'),
('2026', 'N1D', 'N1D', 'N1F,N1D,NPOF', NULL, 'N1F'),
('2026', 'PO_H', 'Play Off H', 'NPOH', 'NPOH', NULL),
('2026', 'PO_D', 'Play Off D', 'NPOF', 'NPOF', NULL),
('2026', 'N2', 'N2', 'N2H*', NULL, 'N2H'),
('2026', 'N3', 'N3', 'N3*', NULL, 'N3'),
('2026', 'N4', 'N4', 'N4*', NULL, NULL),
('2026', 'U15', 'U15', 'N15*', 'N15', NULL),
('2026', 'U18', 'U18', 'N18*', 'N18', NULL),
('2026', 'U21D', 'U21 D', 'U21F*,U21D*', NULL, NULL),
('2026', 'U21H', 'U21 H', 'U21H*', NULL, NULL),
('2026', 'CF15', 'Coupe de France U15', 'CF15*', 'CF15', NULL),
('2026', 'CFD', 'Coupe de France D', 'CFF*,CFD*', 'CFF', NULL),
('2026', 'CFH', 'Coupe de France H', 'CFH*', 'CFH', NULL);

INSERT IGNORE INTO `kp_club_ranking_rule` (`season`, `article`, `position`, `label`, `short_label`, `points`, `calc_type`, `params`, `note`) VALUES
('2026', '7.1', 1, 'Équipe du club en N1D, U15, U18, CFD, CF15 (par équipe de club engagée)', 'Équipe N1D, U15, U18, CFD, CF15', 20, 'TEAM_OF_CLUB', '{"categories":["N1D","U15","U18","CFD","CF15"],"ententes":true,"require_played":true}', 'Une fois par équipe et par catégorie ; chaque club d''une entente ; rien pour une sélection ; au moins un match joué.'),
('2026', '7.1', 2, 'Un ou plusieurs licenciés dans une équipe N1D, U15, U18, CFD, CF15, U21D, U21H', 'Licenciés N1D, U15, U18, CFD, CF15, U21', 15, 'LICENSEE_IN_TEAM', '{"categories":["N1D","U15","U18","CFD","CF15","U21D","U21H"],"presence":"played","include_team_club":true}', 'Par équipe et par club ; joueur ayant disputé au moins un match validé, entraîneurs exclus ; club de l''équipe compris ; sélections comprises.'),
('2026', '7.1', 3, 'Équipe du club en N1H, N2, N3, NEM, CF', 'Équipe N1H, N2, N3, CFH', 10, 'TEAM_OF_CLUB', '{"categories":["N1H","N2","N3","N4","CFH"],"ententes":true,"require_played":true}', '« CF » lu comme CFH ; N4 et NEM selon les compétitions de la saison.'),
('2026', '7.1', 4, 'Un ou plusieurs licenciés en Équipe de France (Joueur(euse), Entraîneur, Staff...)', 'Équipe de France', 5, 'MANUAL_LIST', '{"unit":"club"}', 'Saisie manuelle ; une fois par club ; sélections en compétition officielle de la saison.'),
('2026', '7.2', 1, 'Club organisateur d''une compétition internationale officielle (ECA Cup, Championnats d''Europe ou du Monde)', 'Orga. internationale', 55, 'EVENT_ORGANISER', '{"nature":"international"}', 'Compétition de niveau INT organisée par le club ; à défaut dans KPI, ajustement.'),
('2026', '7.2', 2, 'Club organisateur d''une finale de championnats (plusieurs divisions / catégories)', 'Orga. finale multiple', 45, 'EVENT_ORGANISER', '{"nature":"final_multi"}', 'Par événement, rubrique la plus élevée.'),
('2026', '7.2', 3, 'Club organisateur d''une finale de Championnat (une division/catégorie) ou d''une finale de Coupe de France', 'Orga. finale', 35, 'EVENT_ORGANISER', '{"nature":"final_single"}', 'Par événement, rubrique la plus élevée.'),
('2026', '7.2', 4, 'Club organisateur d''une journée multiple de compétitions nationales (plusieurs divisions / catégories)', 'Orga. journée multiple', 20, 'EVENT_ORGANISER', '{"nature":"day_multi"}', 'Par événement, rubrique la plus élevée.'),
('2026', '7.2', 5, 'Club organisateur d''une ou plusieurs formations d''arbitre national', 'Orga. formation arbitre', 15, 'MANUAL_LIST', '{"unit":"club"}', 'Saisie manuelle ; une fois par club.'),
('2026', '7.2', 6, 'Club organisateur d''une journée simple de compétition nationale (une seule division / catégorie)', 'Orga. journée simple', 10, 'EVENT_ORGANISER', '{"nature":"day_single"}', 'Par événement.'),
('2026', '7.3', 1, 'Délégué CNA ou Juge arbitre sur une ou plusieurs journées de compétition nationale', 'Délégué / juge arbitre', 50, 'JOURNEE_OFFICIAL', '{"fields":["delegate","chief_referee"]}', 'Une fois par licencié ; noms des journées rapprochés des licenciés.'),
('2026', '7.3', 2, 'Responsable de compétition', 'Responsable de compétition', 40, 'COMPETITION_MANAGER', '{}', 'Une fois par licencié, quel que soit le nombre de compétitions.'),
('2026', '7.3', 3, 'Arbitre international officiant en compétition nationale', 'Arbitre international', 35, 'REFEREE_QUALIFICATION', '{"qualifications":[{"qualification":"Int","level":"*"}],"min_national_games":1}', 'Au moins un match arbitré en compétition nationale.'),
('2026', '7.3', 4, 'Autre membre de la CNA (Hors RC, délégué CNA, juge arbitre)', 'Membre CNA', 20, 'MANUAL_LIST', '{"unit":"licensee","exclude_rules":["7.3.1","7.3.2"]}', 'Saisie manuelle ; exclut les licenciés déjà comptés en 7.3.1 et 7.3.2.'),
('2026', '7.3', 5, 'Arbitre national catégorie A', 'Arbitre nat. A', 15, 'REFEREE_QUALIFICATION', '{"qualifications":[{"qualification":"Nat","level":"A"}],"min_national_games":0}', 'Licencié de la saison, sans condition d''arbitrage effectif.'),
('2026', '7.3', 6, 'Arbitre national catégorie B ou C', 'Arbitre nat. B, C', 10, 'REFEREE_QUALIFICATION', '{"qualifications":[{"qualification":"Nat","level":"B"},{"qualification":"Nat","level":"C"}],"min_national_games":0}', 'Licencié de la saison, sans condition d''arbitrage effectif.'),
('2026', '7.3', 7, 'Arbitre national stagiaire ou régional, Officiel de table de marque, Jeune Officiel', 'Arb. stag., rég., OTM, JO', 3, 'REFEREE_QUALIFICATION', '{"qualifications":[{"qualification":"Nat","level":"S"},{"qualification":"Reg","level":"*"},{"qualification":"OTM","level":"*"},{"qualification":"JO","level":"*"}],"min_national_games":0}', 'Licencié de la saison, sans condition d''activité.'),
('2026', '7.4', 1, 'Champion de France (U15, U18, Play Off D, Play Off H)', 'Champion de France', 40, 'RANKING', '{"slots":[{"categories":["U15","U18","PO_D","PO_H"],"ranking":"final","place":1}],"beneficiary":"licensee_clubs"}', 'Chaque club d''origine des joueurs, sélections comprises.'),
('2026', '7.4', 2, 'Vainqueur de la saison régulière (N1H, N1D, N2, N3)', '1er saison régulière', 35, 'RANKING', '{"slots":[{"categories":["N1H","N1D","N2","N3"],"ranking":"regular","place":1}],"beneficiary":"licensee_clubs"}', 'Cumulable avec le titre de champion.'),
('2026', '7.4', 3, '2ème de championnat de France/ Vainqueur Coupe de France (CF15, CFD, CFH)', '2e champ. / 1er CdF', 30, 'RANKING', '{"slots":[{"categories":["U15","U18","PO_D","PO_H"],"ranking":"final","place":2},{"categories":["CF15","CFD","CFH"],"ranking":"final","place":1}],"beneficiary":"licensee_clubs"}', ''),
('2026', '7.4', 4, '2ème de la saison régulière (N1H, N1D, N2, N3)', '2e saison régulière', 25, 'RANKING', '{"slots":[{"categories":["N1H","N1D","N2","N3"],"ranking":"regular","place":2}],"beneficiary":"licensee_clubs"}', ''),
('2026', '7.4', 5, '3ème de championnat de France/ 2ème Coupe de France', '3e champ. / 2e CdF', 25, 'RANKING', '{"slots":[{"categories":["U15","U18","PO_D","PO_H"],"ranking":"final","place":3},{"categories":["CF15","CFD","CFH"],"ranking":"final","place":2}],"beneficiary":"licensee_clubs"}', ''),
('2026', '7.4', 6, '3ème de la saison régulière (N1H, N1D, N2, N3)', '3e saison régulière', 20, 'RANKING', '{"slots":[{"categories":["N1H","N1D","N2","N3"],"ranking":"regular","place":3}],"beneficiary":"licensee_clubs"}', ''),
('2026', '7.4', 7, '4ème de championnat de France/ 3ème Coupe de France', '4e champ. / 3e CdF', 15, 'RANKING', '{"slots":[{"categories":["U15","U18","PO_D","PO_H"],"ranking":"final","place":4},{"categories":["CF15","CFD","CFH"],"ranking":"final","place":3}],"beneficiary":"licensee_clubs"}', ''),
('2026', '7.4', 8, '5ème de championnat de France/ 4ème Coupe de France', '5e champ. / 4e CdF', 12, 'RANKING', '{"slots":[{"categories":["U15","U18","PO_D","PO_H"],"ranking":"final","place":5},{"categories":["CF15","CFD","CFH"],"ranking":"final","place":4}],"beneficiary":"licensee_clubs"}', ''),
('2026', '7.4', 9, '6ème de championnat de France/ 5ème Coupe de France', '6e champ. / 5e CdF', 10, 'RANKING', '{"slots":[{"categories":["U15","U18","PO_D","PO_H"],"ranking":"final","place":6},{"categories":["CF15","CFD","CFH"],"ranking":"final","place":5}],"beneficiary":"licensee_clubs"}', ''),
('2026', '7.4', 10, '7ème de championnat de France', '7e champ.', 8, 'RANKING', '{"slots":[{"categories":["U15","U18","PO_D","PO_H"],"ranking":"final","place":7}],"beneficiary":"licensee_clubs"}', ''),
('2026', '7.4', 11, '8ème de championnat de France', '8e champ.', 6, 'RANKING', '{"slots":[{"categories":["U15","U18","PO_D","PO_H"],"ranking":"final","place":8}],"beneficiary":"licensee_clubs"}', '');
