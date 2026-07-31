-- Fixtures de test api2 — SCHÉMA (Phase 4 du plan CI/CD).
--
-- Sous-ensemble des tables réellement interrogées par la suite `integration`.
-- Types et valeurs par défaut recopiés de la production (SHOW CREATE TABLE) :
-- c'est ce qui garantit que le SQL des contrôleurs se comporte à l'identique
-- (char(1) pour les drapeaux O/N, char(4) pour les codes de saison, dates
-- « zéro » MySQL autorisées, collation utf8mb3…).
--
-- Les CONTRAINTES DE CLÉ ÉTRANGÈRE de la prod sont volontairement OMISES : elles
-- pointent vers des tables hors de ce périmètre (clubs, licences, users…) et il
-- faudrait recopier la moitié du schéma pour les satisfaire. Les tests n'en ont
-- pas besoin — ils valident la lecture, pas l'intégrité référentielle.
--
-- Idempotent : rejouable sur une base existante.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `kp_evenement`;
CREATE TABLE `kp_evenement` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `Libelle` varchar(50) DEFAULT NULL,
  `Lieu` varchar(50) DEFAULT NULL,
  `Date_debut` date DEFAULT NULL,
  `Date_fin` date DEFAULT NULL,
  `Publication` char(1) NOT NULL DEFAULT '',
  `Date_publi` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Code_uti_publi` varchar(8) NOT NULL DEFAULT '',
  `logo` varchar(50) DEFAULT NULL,
  `app` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

DROP TABLE IF EXISTS `kp_saison`;
CREATE TABLE `kp_saison` (
  `Code` char(4) NOT NULL DEFAULT '',
  `Etat` char(1) NOT NULL DEFAULT '',
  `Nat_debut` date NOT NULL DEFAULT '0000-00-00',
  `Nat_fin` date NOT NULL DEFAULT '0000-00-00',
  `Inter_debut` date NOT NULL DEFAULT '0000-00-00',
  `Inter_fin` date NOT NULL DEFAULT '0000-00-00',
  PRIMARY KEY (`Code`),
  KEY `Etat` (`Etat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

DROP TABLE IF EXISTS `kp_groupe`;
CREATE TABLE `kp_groupe` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section` int(11) NOT NULL,
  `ordre` int(11) NOT NULL,
  `Code_niveau` char(3) NOT NULL DEFAULT 'NAT',
  `Groupe` varchar(10) NOT NULL DEFAULT '',
  `Libelle` mediumtext NOT NULL,
  `Libelle_en` varchar(255) DEFAULT NULL,
  `Calendar` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Groupe` (`Groupe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Seules les colonnes lues par les contrôleurs testés + celles NOT NULL sans
-- défaut utilisable sont conservées ; la table de prod en compte une
-- cinquantaine (grilles de points MULTI, dates de calcul/publication…) qui
-- n'interviennent dans aucune requête de la suite.
DROP TABLE IF EXISTS `kp_competition`;
CREATE TABLE `kp_competition` (
  `Code` varchar(12) NOT NULL DEFAULT '',
  `Code_saison` char(4) NOT NULL DEFAULT '',
  `Libelle` varchar(80) DEFAULT NULL,
  `Soustitre` varchar(80) DEFAULT NULL,
  `Soustitre2` varchar(80) DEFAULT NULL,
  `BandeauLink` mediumtext NOT NULL,
  `LogoLink` mediumtext NOT NULL,
  `SponsorLink` mediumtext NOT NULL,
  `Bandeau_actif` char(1) NOT NULL DEFAULT '',
  `Logo_actif` char(1) NOT NULL DEFAULT '',
  `Sponsor_actif` char(1) NOT NULL DEFAULT '',
  `Code_ref` varchar(10) NOT NULL,
  `Code_typeclt` varchar(8) DEFAULT NULL,
  `Verrou` char(1) DEFAULT NULL,
  `Statut` varchar(3) NOT NULL DEFAULT 'ATT',
  `Publication` char(1) NOT NULL DEFAULT '',
  PRIMARY KEY (`Code`,`Code_saison`),
  KEY `fk_competitions_saison` (`Code_saison`),
  KEY `fk_competitions_groupes` (`Code_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

DROP TABLE IF EXISTS `kp_journee`;
CREATE TABLE `kp_journee` (
  `Id` int(11) NOT NULL DEFAULT 0,
  `Code_competition` varchar(12) NOT NULL DEFAULT '',
  `Code_saison` char(4) NOT NULL DEFAULT '',
  `Date_debut` date DEFAULT NULL,
  `Date_fin` date DEFAULT NULL,
  `Nom` varchar(80) DEFAULT NULL,
  `Libelle` varchar(80) DEFAULT NULL,
  `Lieu` varchar(40) DEFAULT NULL,
  `Consolidation` varchar(1) DEFAULT NULL,
  `Etat` char(1) DEFAULT NULL,
  `Type` char(1) NOT NULL DEFAULT 'C',
  `Phase` varchar(30) DEFAULT NULL,
  `Niveau` smallint(6) DEFAULT NULL,
  `Etape` smallint(6) NOT NULL DEFAULT 1,
  `Nbequipes` smallint(6) NOT NULL DEFAULT 1,
  `Publication` char(1) DEFAULT NULL,
  PRIMARY KEY (`Id`),
  KEY `Code_saison` (`Code_saison`),
  KEY `Code_competition` (`Code_competition`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- ---------------------------------------------------------------------------
-- Refonte scoring (lot 4) : ce qu'il faut pour exercer le CONTRÔLE D'ACCÈS des
-- incrustations. On n'ajoute que le strict nécessaire : le rattachement d'un
-- match à un événement/terrain (c'est la portée d'un jeton) et les jetons
-- eux-mêmes. L'état live n'est pas nécessaire ici — les tests d'accès doivent
-- échouer AVANT de lire la moindre donnée de match.
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS `kp_evenement_journee`;
CREATE TABLE `kp_evenement_journee` (
  `Id_evenement` int(10) UNSIGNED NOT NULL,
  `Id_journee` int(11) NOT NULL,
  PRIMARY KEY (`Id_evenement`,`Id_journee`),
  KEY `fk_evenements_journee` (`Id_journee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

DROP TABLE IF EXISTS `kp_match`;
CREATE TABLE `kp_match` (
  `Id` int(10) UNSIGNED NOT NULL,
  `Id_journee` int(11) NOT NULL DEFAULT 0,
  `Libelle` varchar(30) DEFAULT NULL,
  `Type` char(1) NOT NULL DEFAULT 'C',
  `Statut` varchar(3) NOT NULL DEFAULT 'ATT',
  `Date_match` date DEFAULT NULL,
  `Heure_match` varchar(6) DEFAULT NULL,
  `Heure_fin` time DEFAULT NULL,
  `Terrain` varchar(30) DEFAULT NULL,
  `Numero_ordre` int(10) UNSIGNED DEFAULT NULL,
  `Periode` varchar(3) DEFAULT NULL,
  `Id_equipeA` int(10) UNSIGNED DEFAULT NULL,
  `Id_equipeB` int(10) UNSIGNED DEFAULT NULL,
  `ScoreA` varchar(4) DEFAULT NULL,
  `ScoreB` varchar(4) DEFAULT NULL,
  `ScoreDetailA` int(11) DEFAULT NULL,
  `ScoreDetailB` int(11) DEFAULT NULL,
  `Publication` char(1) DEFAULT NULL,
  `Validation` char(1) NOT NULL DEFAULT '',
  PRIMARY KEY (`Id`),
  KEY `Id_journee` (`Id_journee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

DROP TABLE IF EXISTS `scoring_display_token`;
CREATE TABLE `scoring_display_token` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `token` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_event` int(10) UNSIGNED NOT NULL,
  `pitch` varchar(30) DEFAULT NULL,
  `label` varchar(100) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scoring_display_token` (`token`),
  KEY `idx_scoring_display_token_event` (`id_event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `scoring_display_settings`;
CREATE TABLE `scoring_display_settings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_event` int(10) UNSIGNED NOT NULL,
  `pitch` varchar(30) DEFAULT NULL,
  `halftime_score_delay` int(11) DEFAULT NULL,
  `final_score_delay` int(11) DEFAULT NULL,
  `final_score_duration` int(11) DEFAULT NULL,
  `next_game_delay` int(11) DEFAULT NULL,
  `next_game_duration` int(11) DEFAULT NULL,
  `background` varchar(30) DEFAULT NULL,
  `style_id` varchar(50) DEFAULT NULL,
  `updated_at` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scoring_display` (`id_event`,`pitch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `scoring_live_state`;
CREATE TABLE `scoring_live_state` (
  `id_match` int(10) UNSIGNED NOT NULL,
  `score_a` int(11) NOT NULL DEFAULT 0,
  `score_b` int(11) NOT NULL DEFAULT 0,
  `periode` varchar(4) NOT NULL DEFAULT 'M1',
  `statut` enum('ATT','ON','END') NOT NULL DEFAULT 'ATT',
  `heure_fin` time DEFAULT NULL,
  `active_source` enum('MANUAL','HARDWARE','SCORE_ONLY','IMPORT') NOT NULL DEFAULT 'MANUAL',
  `promoted_at` datetime(3) DEFAULT NULL,
  `tick` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3),
  PRIMARY KEY (`id_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
