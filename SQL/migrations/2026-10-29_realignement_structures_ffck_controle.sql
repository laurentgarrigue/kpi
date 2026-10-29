-- Requête de contrôle : réalignement des structures fédérales et des codes régionaux sur le PCE
-- Date : 2026-10-29
-- Spec : DOC/developer/in-progress/REALIGNEMENT_STRUCTURES_FFCK.md (« Script de remise à plat »)
-- ADR  : DOC/developer/reference/adr/0001-codes-regionaux-alignes-sur-les-cr-actuels.md
--
-- LECTURE SEULE (tables temporaires de session uniquement). À lancer AVANT et APRÈS
-- 2026-10-29_realignement_structures_ffck.sql, sur une base où un import PCE corrigé vient de tourner.
--
--   docker exec -i <conteneur_db> mariadb -uroot -p<pass> -t <base> \
--     < SQL/migrations/2026-10-29_realignement_structures_ffck_controle.sql
--
-- Une ligne par indicateur. Après le script, toutes les lignes « gaps » valent 0, sauf
-- « Remise à plat appliquée » qui vaut 1 (et « Clubs du PCE absents de KPI », voir la rollout checklist).
--
-- Tant que la remise à plat n'est pas appliquée, une seconde liste montre, par préfixe renuméroté (REG07…), les
-- codes compétition que le script va renommer, avec leurs saisons et leur libellé le plus récent : un code créé
-- après 2016 qui portait DÉJÀ le numéro d'une CR actuelle (ex. un REG03… breton) serait renuméroté à tort.
-- À relire avant chaque exécution du script (rollout checklist, étape « codes à renommer »).
--
-- Le « PCE » est lu dans kp_licence (saison du dernier import = MAX(Origine)), avec les filtres de
-- l'import : Clubs '0'/'0000' exclus, structures internationales '98' exclues.
-- Les libellés ne sont pas contrôlables : l'import vide les noms de structures de kp_licence à la fin
-- de chaque exécution (updateLicencies). C'est l'import lui-même qui les a alignés sur le PCE.

SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;
SET @season = (SELECT MAX(Origine) FROM kp_licence);
SET @already_applied = (SELECT COUNT(*) > 0 FROM kp_journal WHERE Actions = 'Remise à plat structures FFCK');

DROP TEMPORARY TABLE IF EXISTS ctl_pce_club, ctl_pce_cd, ctl_pce_cr, ctl_licensee_club, ctl_licensee_cd,
    ctl_limitation, ctl_old_league_cd, ctl_old_league_club, ctl_code, ctl_league, ctl_code_exception;

CREATE TEMPORARY TABLE ctl_pce_club (code VARCHAR(6) PRIMARY KEY, cd VARCHAR(6)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT Numero_club AS code, MIN(Numero_comite_dept) AS cd
    FROM kp_licence
    WHERE Origine = @season AND Numero_club NOT IN ('0', '0000') AND Numero_comite_reg <> '98'
      AND Numero_comite_dept IS NOT NULL
    GROUP BY Numero_club;

CREATE TEMPORARY TABLE ctl_pce_cd (code VARCHAR(6) PRIMARY KEY, cr VARCHAR(6)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT Numero_comite_dept AS code, MIN(Numero_comite_reg) AS cr
    FROM kp_licence
    WHERE Origine = @season AND Numero_club NOT IN ('0', '0000') AND Numero_comite_reg <> '98'
      AND Numero_comite_dept IS NOT NULL
    GROUP BY Numero_comite_dept;

CREATE TEMPORARY TABLE ctl_pce_cr (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT DISTINCT Numero_comite_reg AS code
    FROM kp_licence
    WHERE Origine = @season AND Numero_club NOT IN ('0', '0000') AND Numero_comite_reg <> '98';

CREATE TEMPORARY TABLE ctl_licensee_club (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT DISTINCT Numero_club AS code FROM kp_licence WHERE Numero_club IS NOT NULL;

CREATE TEMPORARY TABLE ctl_licensee_cd (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT DISTINCT Numero_comite_dept AS code FROM kp_licence WHERE Numero_comite_dept IS NOT NULL;

-- Codes cités dans les limitations d'équipes (utilisateurs et mandats), liste séparée par des virgules
-- (même découpage que le script)
CREATE TEMPORARY TABLE ctl_limitation (code VARCHAR(50) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    WITH RECURSIVE owner_list AS (
        SELECT CAST(Limitation_equipe_club AS CHAR(100)) AS list FROM kp_user WHERE Limitation_equipe_club <> ''
        UNION ALL
        SELECT CAST(limitation_equipe_club AS CHAR(100)) FROM kp_user_mandat WHERE limitation_equipe_club <> ''
    ), item AS (
        SELECT SUBSTRING_INDEX(list, ',', 1) AS code,
               IF(LOCATE(',', list) > 0, SUBSTRING(list, LOCATE(',', list) + 1), NULL) AS rest
        FROM owner_list
        UNION ALL
        SELECT SUBSTRING_INDEX(rest, ',', 1), IF(LOCATE(',', rest) > 0, SUBSTRING(rest, LOCATE(',', rest) + 1), NULL)
        FROM item WHERE rest IS NOT NULL
    )
    SELECT DISTINCT TRIM(code) AS code FROM item WHERE TRIM(code) <> '';

-- Pseudo-CD d'Ancienne ligue : code CRxx rattaché à une autre CR (une Sélection régionale est rattachée à elle-même)
CREATE TEMPORARY TABLE ctl_old_league_cd (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT Code AS code FROM kp_cd WHERE Code REGEXP '^CR[0-9]{2}$' AND Code <> Code_comite_reg;

-- Pseudo-club d'Ancienne ligue : rattaché à un pseudo-CD d'Ancienne ligue, ou code CRxx qui n'est pas une Sélection
CREATE TEMPORARY TABLE ctl_old_league_club (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT c.Code AS code
    FROM kp_club c
    LEFT JOIN kp_cd d ON d.Code = c.Code_comite_dep
    WHERE c.Code_comite_dep IN (SELECT code FROM ctl_old_league_cd)
       OR (c.Code REGEXP '^CR[0-9]{2}$' AND NOT (c.Code = c.Code_comite_dep AND d.Code_comite_reg = c.Code));

-- Codes de Compétitions régionales (codes, codes de référence, groupes) et leur numéro de région
CREATE TEMPORARY TABLE ctl_code (code VARCHAR(12) PRIMARY KEY, number CHAR(2)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT code, SUBSTRING(code, IF(code LIKE 'T-R%', 4, IF(code LIKE 'REG%', 4, 3)), 2) AS number
    FROM (
        SELECT Code AS code FROM kp_competition
        UNION SELECT Code_ref FROM kp_competition
        UNION SELECT Groupe FROM kp_groupe
    ) c
    WHERE code REGEXP '^(REG|T-R)[0-9]{2}' OR code REGEXP '^CR[0-9]{2}$';

-- Table de correspondance et exceptions de l'ADR 0001 : copie de celles du script, à garder identiques
CREATE TEMPORARY TABLE ctl_league (old_number CHAR(2) PRIMARY KEY, current_number CHAR(2) NOT NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO ctl_league VALUES
    ('02', '05'), ('09', '05'), ('17', '05'),
    ('03', '09'), ('16', '09'), ('22', '09'),
    ('04', '11'), ('07', '03'), ('08', '04'),
    ('12', '06'), ('21', '06'), ('13', '02'),
    ('14', '07'), ('15', '10'), ('19', '10'),
    ('18', '01'), ('20', '08'), ('23', '12'),
    ('25', '24'), ('29', '21');
CREATE TEMPORARY TABLE ctl_code_exception (code VARCHAR(12) PRIMARY KEY, new_code VARCHAR(12) NOT NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO ctl_code_exception VALUES ('REG09', 'REG09'), ('REG09B', 'REG09B'), ('REG23', 'REG24'), ('CR23', 'CR24');

SELECT @season AS pce_season;

SELECT 'CR du PCE absentes de KPI' AS indicator,
       (SELECT COUNT(*) FROM ctl_pce_cr p LEFT JOIN kp_cr r ON r.Code = p.code WHERE r.Code IS NULL) AS gaps
UNION ALL SELECT 'CD du PCE absents de KPI',
       (SELECT COUNT(*) FROM ctl_pce_cd p LEFT JOIN kp_cd d ON d.Code = p.code WHERE d.Code IS NULL)
UNION ALL SELECT 'CD rattachés à une autre CR que dans le PCE',
       (SELECT COUNT(*) FROM ctl_pce_cd p JOIN kp_cd d ON d.Code = p.code WHERE d.Code_comite_reg <> p.cr)
UNION ALL SELECT 'Clubs du PCE absents de KPI',
       (SELECT COUNT(*) FROM ctl_pce_club p LEFT JOIN kp_club c ON c.Code = p.code WHERE c.Code IS NULL)
UNION ALL SELECT 'Clubs rattachés à un autre CD que dans le PCE',
       (SELECT COUNT(*) FROM ctl_pce_club p JOIN kp_club c ON c.Code = p.code WHERE c.Code_comite_dep <> p.cd)
UNION ALL SELECT 'CD hors PCE non référencés (dont format brut CDxxx)',
       (SELECT COUNT(*) FROM kp_cd d
        WHERE d.Code_comite_reg <> '98'
          AND d.Code NOT IN (SELECT code FROM ctl_pce_cd)
          AND d.Code NOT IN (SELECT Code_comite_dep FROM kp_club)
          AND d.Code NOT IN (SELECT code FROM ctl_licensee_cd)
          AND d.Code NOT IN (SELECT code FROM ctl_limitation))
UNION ALL SELECT 'Clubs hors PCE non référencés',
       (SELECT COUNT(*) FROM kp_club c JOIN kp_cd d ON d.Code = c.Code_comite_dep
        WHERE d.Code_comite_reg <> '98'
          AND NOT (c.Code = c.Code_comite_dep AND d.Code_comite_reg = c.Code)
          AND c.Code NOT IN (SELECT code FROM ctl_pce_club)
          AND c.Code NOT IN (SELECT code FROM ctl_licensee_club)
          AND c.Code NOT IN (SELECT code FROM ctl_limitation)
          AND c.Code NOT IN (SELECT Code_club FROM kp_equipe WHERE Code_club IS NOT NULL)
          AND c.Code NOT IN (SELECT Code_club FROM kp_competition_equipe WHERE Code_club IS NOT NULL))
UNION ALL SELECT 'Pseudo-CD d''Anciennes ligues restants',
       (SELECT COUNT(*) FROM ctl_old_league_cd)
UNION ALL SELECT 'Pseudo-clubs d''Anciennes ligues restants',
       (SELECT COUNT(*) FROM ctl_old_league_club)
UNION ALL SELECT 'Équipes sur un pseudo-club d''Ancienne ligue',
       (SELECT COUNT(*) FROM kp_equipe WHERE Code_club IN (SELECT code FROM ctl_old_league_club))
UNION ALL SELECT 'Engagements sur un pseudo-club d''Ancienne ligue',
       (SELECT COUNT(*) FROM kp_competition_equipe WHERE Code_club IN (SELECT code FROM ctl_old_league_club))
UNION ALL SELECT 'Utilisateurs et mandats limités à un pseudo-club d''Ancienne ligue',
       (SELECT COUNT(*) FROM (
            SELECT Limitation_equipe_club AS limitation FROM kp_user
            UNION ALL SELECT limitation_equipe_club FROM kp_user_mandat
        ) l
        WHERE EXISTS (SELECT 1 FROM ctl_old_league_club a WHERE FIND_IN_SET(a.code, REPLACE(l.limitation, ' ', ''))))
UNION ALL SELECT 'Codes REGxx/T-Rxx/CRxx (compétitions, références, groupes) hors CR actuelles',
       (SELECT COUNT(*) FROM ctl_code k WHERE CONCAT('CR', k.number) NOT IN (SELECT Code FROM kp_cr))
UNION ALL SELECT 'Filtres utilisateur/mandat citant un code REGxx/T-Rxx hors CR actuelles',
       (SELECT COUNT(*) FROM (
            SELECT Filtre_competition AS filter FROM kp_user
            UNION ALL SELECT filtre_competition FROM kp_user_mandat
        ) l
        WHERE EXISTS (SELECT 1 FROM ctl_code k
                      WHERE CONCAT('CR', k.number) NOT IN (SELECT Code FROM kp_cr)
                        AND l.filter LIKE CONCAT('%|', k.code, '|%')))
UNION ALL SELECT 'Remise à plat appliquée (1 = oui)', @already_applied;

-- Détail des codes régionaux hors CR actuelles (vide après le script)
SELECT k.code AS regional_code_outside_current_cr
FROM ctl_code k
WHERE CONCAT('CR', k.number) NOT IN (SELECT Code FROM kp_cr)
ORDER BY k.code;

-- Codes compétition que le script va renommer, par préfixe renuméroté (vide une fois la remise à plat appliquée).
-- À relire : un libellé d'une autre région que celle de l'Ancienne ligue (ex. « Bretagne » sous REG03, ancienne
-- Aquitaine) signale un code créé avec le numéro d'une CR actuelle, que le script renumérotera à tort.
SELECT r.renamed_prefix, r.code, r.new_code, r.seasons, r.latest_label
FROM (
    SELECT CONCAT(p.prefix, SUBSTRING(p.code, LENGTH(p.prefix) + 1, 2)) AS renamed_prefix,
           p.code,
           COALESCE(e.new_code, CONCAT(p.prefix, l.current_number, SUBSTRING(p.code, LENGTH(p.prefix) + 3))) AS new_code,
           p.seasons,
           p.latest_label
    FROM (
        SELECT c.Code AS code,
               CASE WHEN c.Code LIKE 'T-R%' THEN 'T-R' WHEN c.Code LIKE 'REG%' THEN 'REG' ELSE 'CR' END AS prefix,
               GROUP_CONCAT(c.Code_saison ORDER BY c.Code_saison SEPARATOR ' ') AS seasons,
               SUBSTRING_INDEX(GROUP_CONCAT(c.Libelle ORDER BY c.Code_saison DESC SEPARATOR '\n'), '\n', 1) AS latest_label
        FROM kp_competition c
        WHERE c.Code REGEXP '^(REG|T-R)[0-9]{2}' OR c.Code REGEXP '^CR[0-9]{2}$'
        GROUP BY c.Code
    ) p
    LEFT JOIN ctl_code_exception e ON e.code = p.code
    LEFT JOIN ctl_league l ON l.old_number = SUBSTRING(p.code, LENGTH(p.prefix) + 1, 2)
) r
WHERE @already_applied = 0 AND (r.new_code IS NULL OR r.new_code <> r.code)
ORDER BY r.renamed_prefix, r.code;
