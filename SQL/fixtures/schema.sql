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

SET FOREIGN_KEY_CHECKS = 1;
