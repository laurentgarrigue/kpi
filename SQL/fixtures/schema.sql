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

-- Définition complète (SHOW CREATE TABLE de prod) : les endpoints publics de résultats lisent une
-- quinzaine de colonnes (GroupOrder, Code_tour, Qualifies, Elimines, Titre_actif, Web…).
DROP TABLE IF EXISTS `kp_competition`;
CREATE TABLE `kp_competition` (
  `Code` varchar(12) NOT NULL DEFAULT '',
  `Code_saison` char(4) NOT NULL DEFAULT '',
  `Code_niveau` char(3) DEFAULT NULL,
  `Libelle` varchar(80) DEFAULT NULL,
  `Soustitre` varchar(80) DEFAULT NULL,
  `Soustitre2` varchar(80) DEFAULT NULL,
  `Web` mediumtext DEFAULT NULL,
  `BandeauLink` mediumtext NOT NULL,
  `LogoLink` mediumtext NOT NULL,
  `SponsorLink` mediumtext NOT NULL,
  `En_actif` char(1) NOT NULL DEFAULT '',
  `Titre_actif` char(1) NOT NULL DEFAULT 'O',
  `Bandeau_actif` char(1) NOT NULL,
  `Logo_actif` char(1) NOT NULL DEFAULT '',
  `Sponsor_actif` char(1) NOT NULL DEFAULT '',
  `Kpi_ffck_actif` char(1) NOT NULL DEFAULT 'O',
  `ToutGroup` char(1) NOT NULL DEFAULT '',
  `TouteSaisons` char(1) NOT NULL DEFAULT '',
  `Code_ref` varchar(10) NOT NULL,
  `GroupOrder` tinyint(4) NOT NULL DEFAULT 0,
  `Code_typeclt` varchar(8) DEFAULT NULL,
  `Age_min` smallint(6) DEFAULT NULL,
  `Age_max` smallint(6) DEFAULT NULL,
  `Sexe` char(1) DEFAULT NULL,
  `Code_tour` smallint(6) DEFAULT NULL,
  `Nb_equipes` tinyint(2) DEFAULT NULL,
  `Verrou` char(1) DEFAULT NULL,
  `Statut` varchar(3) NOT NULL DEFAULT 'ATT',
  `Qualifies` int(11) NOT NULL DEFAULT 3,
  `Elimines` int(11) NOT NULL DEFAULT 0,
  `Points` varchar(7) NOT NULL DEFAULT '4-2-1-0',
  `goalaverage` varchar(4) NOT NULL DEFAULT 'gen',
  `Date_calcul` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Mode_calcul` varchar(4) DEFAULT NULL,
  `Date_publication` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Date_publication_calcul` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Mode_publication_calcul` varchar(4) DEFAULT NULL,
  `Code_uti_calcul` varchar(8) NOT NULL DEFAULT '',
  `Code_uti_publication` varchar(8) NOT NULL DEFAULT '',
  `Publication` char(1) NOT NULL DEFAULT '',
  `Date_publi` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Code_uti_publi` varchar(8) NOT NULL DEFAULT '',
  `commentairesCompet` mediumtext DEFAULT NULL,
  `points_grid` text DEFAULT NULL COMMENT 'Grille de points pour les compétitions MULTI (format JSON)',
  `multi_competitions` text DEFAULT NULL COMMENT 'Liste des codes de compétitions sources pour MULTI (format JSON array)',
  `ranking_structure_type` varchar(10) DEFAULT 'team' COMMENT 'Type de classement pour MULTI: team, club, cd, cr, nation',
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
  `Departement` varchar(3) DEFAULT NULL,
  `Plan_eau` varchar(80) DEFAULT NULL,
  `Responsable_insc` varchar(80) DEFAULT NULL,
  `Responsable_insc_adr` varchar(40) DEFAULT NULL,
  `Responsable_insc_cp` varchar(5) DEFAULT NULL,
  `Responsable_insc_ville` varchar(40) DEFAULT NULL,
  `Responsable_R1` varchar(80) DEFAULT NULL,
  `Etat` char(1) DEFAULT NULL,
  `Type` char(1) NOT NULL DEFAULT 'C',
  `Code_organisateur` varchar(5) DEFAULT NULL,
  `Organisateur` varchar(40) DEFAULT NULL,
  `Organisateur_adr` varchar(40) DEFAULT NULL,
  `Organisateur_cp` varchar(5) DEFAULT NULL,
  `Organisateur_ville` varchar(40) DEFAULT NULL,
  `Delegue` varchar(80) DEFAULT NULL,
  `ChefArbitre` varchar(80) DEFAULT NULL,
  `Rep_athletes` varchar(80) DEFAULT NULL,
  `Arb_nj1` varchar(80) DEFAULT NULL,
  `Arb_nj2` varchar(80) DEFAULT NULL,
  `Arb_nj3` varchar(80) DEFAULT NULL,
  `Arb_nj4` varchar(80) DEFAULT NULL,
  `Arb_nj5` varchar(80) DEFAULT NULL,
  `Validation` char(1) DEFAULT NULL,
  `Code_uti` varchar(8) DEFAULT NULL,
  `Phase` varchar(30) DEFAULT NULL,
  `Niveau` smallint(6) DEFAULT NULL,
  `Etape` smallint(6) NOT NULL DEFAULT 1,
  `Nbequipes` smallint(6) NOT NULL DEFAULT 1,
  `Publication` char(1) DEFAULT NULL,
  `Id_dupli` int(11) DEFAULT NULL,
  `Public_prin` char(1) NOT NULL DEFAULT 'O',
  `Public_sec` char(1) NOT NULL DEFAULT 'O',
  PRIMARY KEY (`Id`),
  KEY `Code_saison` (`Code_saison`),
  KEY `Code_competition` (`Code_competition`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- ------------------------------------------------------------------ résultats publics (phase 2 du site public)
-- Tables lues par les endpoints de matchs, tableaux, classements et statistiques
-- (DOC/specs/public/API_PUBLIC_RESULTS.md). Définitions de prod, clés primaires comprises.

DROP TABLE IF EXISTS `kp_evenement_journee`;
CREATE TABLE `kp_evenement_journee` (
  `Id_evenement` int(11) NOT NULL,
  `Id_journee` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id_evenement`,`Id_journee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

DROP TABLE IF EXISTS `kp_competition_equipe`;
CREATE TABLE `kp_competition_equipe` (
  `Id` int(10) UNSIGNED NOT NULL,
  `Code_compet` varchar(12) NOT NULL DEFAULT '',
  `Code_saison` char(4) NOT NULL DEFAULT '',
  `Libelle` varchar(40) DEFAULT NULL,
  `Code_club` varchar(6) DEFAULT NULL,
  `logo` varchar(50) DEFAULT NULL,
  `color1` varchar(30) DEFAULT NULL,
  `color2` varchar(30) DEFAULT NULL,
  `colortext` varchar(30) DEFAULT NULL,
  `Numero` smallint(6) DEFAULT NULL,
  `Poule` varchar(3) NOT NULL DEFAULT '',
  `Tirage` tinyint(4) NOT NULL DEFAULT 0,
  `Pts` smallint(6) NOT NULL DEFAULT 0,
  `Clt` smallint(6) NOT NULL DEFAULT 0,
  `J` smallint(6) NOT NULL DEFAULT 0,
  `G` smallint(6) NOT NULL DEFAULT 0,
  `N` smallint(6) NOT NULL DEFAULT 0,
  `P` smallint(6) NOT NULL DEFAULT 0,
  `F` smallint(6) NOT NULL DEFAULT 0,
  `Plus` smallint(6) NOT NULL DEFAULT 0,
  `Moins` smallint(6) NOT NULL DEFAULT 0,
  `Diff` smallint(6) NOT NULL DEFAULT 0,
  `PtsNiveau` double NOT NULL DEFAULT 0,
  `CltNiveau` smallint(6) NOT NULL DEFAULT 0,
  `Id_dupli` int(11) DEFAULT NULL,
  `Pts_publi` smallint(6) NOT NULL DEFAULT 0,
  `Clt_publi` smallint(6) NOT NULL DEFAULT 0,
  `J_publi` smallint(6) NOT NULL DEFAULT 0,
  `G_publi` smallint(6) NOT NULL DEFAULT 0,
  `N_publi` smallint(6) NOT NULL DEFAULT 0,
  `P_publi` smallint(6) NOT NULL DEFAULT 0,
  `F_publi` smallint(6) NOT NULL DEFAULT 0,
  `Plus_publi` smallint(6) NOT NULL DEFAULT 0,
  `Moins_publi` smallint(6) NOT NULL DEFAULT 0,
  `Diff_publi` smallint(6) NOT NULL DEFAULT 0,
  `PtsNiveau_publi` double NOT NULL DEFAULT 0,
  `CltNiveau_publi` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

DROP TABLE IF EXISTS `kp_competition_equipe_journee`;
CREATE TABLE `kp_competition_equipe_journee` (
  `Id` int(10) UNSIGNED NOT NULL,
  `Id_journee` int(11) NOT NULL DEFAULT 0,
  `Pts` smallint(6) DEFAULT NULL,
  `Clt` smallint(6) DEFAULT NULL,
  `J` smallint(6) DEFAULT NULL,
  `G` smallint(6) DEFAULT NULL,
  `N` smallint(6) DEFAULT NULL,
  `P` smallint(6) DEFAULT NULL,
  `F` smallint(6) DEFAULT NULL,
  `Plus` smallint(6) DEFAULT NULL,
  `Moins` smallint(6) DEFAULT NULL,
  `Diff` smallint(6) DEFAULT NULL,
  `PtsNiveau` double UNSIGNED DEFAULT NULL,
  `CltNiveau` smallint(6) DEFAULT NULL,
  `Pts_publi` smallint(6) DEFAULT NULL,
  `Clt_publi` smallint(6) DEFAULT NULL,
  `J_publi` smallint(6) DEFAULT NULL,
  `G_publi` smallint(6) DEFAULT NULL,
  `N_publi` smallint(6) DEFAULT NULL,
  `P_publi` smallint(6) DEFAULT NULL,
  `F_publi` smallint(6) DEFAULT NULL,
  `Plus_publi` smallint(6) DEFAULT NULL,
  `Moins_publi` smallint(6) DEFAULT NULL,
  `Diff_publi` smallint(6) DEFAULT NULL,
  `PtsNiveau_publi` double DEFAULT NULL,
  `CltNiveau_publi` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`Id`,`Id_journee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

DROP TABLE IF EXISTS `kp_competition_equipe_joueur`;
CREATE TABLE `kp_competition_equipe_joueur` (
  `Id_equipe` int(10) UNSIGNED NOT NULL,
  `Matric` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `Nom` varchar(30) DEFAULT NULL,
  `Prenom` varchar(30) DEFAULT NULL,
  `Sexe` char(1) DEFAULT NULL,
  `Categ` varchar(8) DEFAULT NULL,
  `Numero` smallint(6) DEFAULT NULL,
  `Capitaine` char(1) DEFAULT '-',
  PRIMARY KEY (`Id_equipe`,`Matric`)
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
  `ColorA` varchar(20) DEFAULT NULL,
  `ColorB` varchar(20) DEFAULT NULL,
  `ScoreA` varchar(4) DEFAULT NULL,
  `ScoreB` varchar(4) DEFAULT NULL,
  `ScoreDetailA` int(11) DEFAULT NULL,
  `ScoreDetailB` int(11) DEFAULT NULL,
  `CoeffA` double NOT NULL DEFAULT 1,
  `CoeffB` double NOT NULL DEFAULT 1,
  `Commentaires_officiels` mediumtext DEFAULT NULL,
  `Commentaires` mediumtext DEFAULT NULL,
  `Arbitre_principal` varchar(50) DEFAULT NULL,
  `Arbitre_secondaire` varchar(50) DEFAULT NULL,
  `Matric_arbitre_principal` int(10) DEFAULT NULL,
  `Matric_arbitre_secondaire` int(10) DEFAULT NULL,
  `Secretaire` varchar(50) DEFAULT NULL,
  `Chronometre` varchar(50) DEFAULT NULL,
  `Timeshoot` varchar(50) DEFAULT NULL,
  `Ligne1` varchar(50) DEFAULT NULL,
  `Ligne2` varchar(50) DEFAULT NULL,
  `Publication` char(1) DEFAULT NULL,
  `Code_uti` varchar(8) DEFAULT NULL,
  `Validation` char(1) NOT NULL DEFAULT '',
  `Imprime` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`Id`),
  KEY `Id_journee` (`Id_journee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

DROP TABLE IF EXISTS `kp_match_detail`;
CREATE TABLE `kp_match_detail` (
  `Id` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT replace(uuid(),'-',''),
  `Id_match` int(10) UNSIGNED NOT NULL,
  `Periode` char(3) DEFAULT NULL,
  `Temps` time DEFAULT NULL,
  `Id_evt_match` varchar(2) DEFAULT NULL,
  `motif` varchar(10) DEFAULT NULL,
  `Competiteur` int(11) UNSIGNED DEFAULT NULL,
  `Numero` varchar(6) DEFAULT NULL,
  `Equipe_A_B` char(1) NOT NULL DEFAULT '',
  `date_insert` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`Id`),
  KEY `Id_match` (`Id_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Sous-ensemble : seules les colonnes d'identité lues (noms des arbitres). Aucune vraie licence.
DROP TABLE IF EXISTS `kp_licence`;
CREATE TABLE `kp_licence` (
  `Matric` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `Origine` varchar(6) NOT NULL DEFAULT '',
  `Nom` varchar(30) DEFAULT NULL,
  `Prenom` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`Matric`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
