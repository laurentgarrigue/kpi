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
-- Une ligne par indicateur. Après le script, toutes les lignes « ecarts » valent 0, sauf
-- « Remise à plat appliquée » qui vaut 1.
--
-- Le « PCE » est lu dans kp_licence (saison du dernier import = MAX(Origine)), avec les filtres de
-- l'import : Clubs '0'/'0000' exclus, structures internationales '98' exclues.
-- Les libellés ne sont pas contrôlables : l'import vide les noms de structures de kp_licence à la fin
-- de chaque exécution (updateLicencies). C'est l'import lui-même qui les a alignés sur le PCE.

SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;
SET @saison = (SELECT MAX(Origine) FROM kp_licence);

DROP TEMPORARY TABLE IF EXISTS ctl_pce_club, ctl_pce_cd, ctl_pce_cr, ctl_licence_club, ctl_licence_cd,
    ctl_limitation, ctl_ancien_cd, ctl_ancien_club, ctl_code;

CREATE TEMPORARY TABLE ctl_pce_club (code VARCHAR(6) PRIMARY KEY, cd VARCHAR(6)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT Numero_club AS code, MIN(Numero_comite_dept) AS cd
    FROM kp_licence
    WHERE Origine = @saison AND Numero_club NOT IN ('0', '0000') AND Numero_comite_reg <> '98'
      AND Numero_comite_dept IS NOT NULL
    GROUP BY Numero_club;

CREATE TEMPORARY TABLE ctl_pce_cd (code VARCHAR(6) PRIMARY KEY, cr VARCHAR(6)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT Numero_comite_dept AS code, MIN(Numero_comite_reg) AS cr
    FROM kp_licence
    WHERE Origine = @saison AND Numero_club NOT IN ('0', '0000') AND Numero_comite_reg <> '98'
      AND Numero_comite_dept IS NOT NULL
    GROUP BY Numero_comite_dept;

CREATE TEMPORARY TABLE ctl_pce_cr (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT DISTINCT Numero_comite_reg AS code
    FROM kp_licence
    WHERE Origine = @saison AND Numero_club NOT IN ('0', '0000') AND Numero_comite_reg <> '98';

CREATE TEMPORARY TABLE ctl_licence_club (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT DISTINCT Numero_club AS code FROM kp_licence WHERE Numero_club IS NOT NULL;

CREATE TEMPORARY TABLE ctl_licence_cd (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT DISTINCT Numero_comite_dept AS code FROM kp_licence WHERE Numero_comite_dept IS NOT NULL;

-- Codes cités dans les limitations d'équipes (utilisateurs et mandats), liste séparée par des virgules
CREATE TEMPORARY TABLE ctl_limitation (code VARCHAR(50) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT DISTINCT TRIM(t.code) AS code
    FROM (
        SELECT Limitation_equipe_club AS lim FROM kp_user WHERE Limitation_equipe_club <> ''
        UNION ALL
        SELECT limitation_equipe_club FROM kp_user_mandat WHERE limitation_equipe_club <> ''
    ) l,
    JSON_TABLE(CONCAT('["', REPLACE(l.lim, ',', '","'), '"]'), '$[*]' COLUMNS (code VARCHAR(50) PATH '$')) t
    WHERE TRIM(t.code) <> '';

-- Pseudo-CD d'Ancienne ligue : code CRxx rattaché à une autre CR (une Sélection régionale est rattachée à elle-même)
CREATE TEMPORARY TABLE ctl_ancien_cd (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT Code AS code FROM kp_cd WHERE Code REGEXP '^CR[0-9]{2}$' AND Code <> Code_comite_reg;

-- Pseudo-club d'Ancienne ligue : rattaché à un pseudo-CD d'Ancienne ligue, ou code CRxx qui n'est pas une Sélection
CREATE TEMPORARY TABLE ctl_ancien_club (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT c.Code AS code
    FROM kp_club c
    LEFT JOIN kp_cd d ON d.Code = c.Code_comite_dep
    WHERE c.Code_comite_dep IN (SELECT code FROM ctl_ancien_cd)
       OR (c.Code REGEXP '^CR[0-9]{2}$' AND NOT (c.Code = c.Code_comite_dep AND d.Code_comite_reg = c.Code));

-- Codes de Compétitions régionales (codes, codes de référence, groupes) et leur numéro de région
CREATE TEMPORARY TABLE ctl_code (code VARCHAR(12) PRIMARY KEY, numero CHAR(2)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
    SELECT code, SUBSTRING(code, IF(code LIKE 'T-R%', 4, IF(code LIKE 'REG%', 4, 3)), 2) AS numero
    FROM (
        SELECT Code AS code FROM kp_competition
        UNION SELECT Code_ref FROM kp_competition
        UNION SELECT Groupe FROM kp_groupe
    ) c
    WHERE code REGEXP '^(REG|T-R)[0-9]{2}' OR code REGEXP '^CR[0-9]{2}$';

SELECT @saison AS saison_pce;

SELECT 'CR du PCE absentes de KPI' AS indicateur,
       (SELECT COUNT(*) FROM ctl_pce_cr p LEFT JOIN kp_cr r ON r.Code = p.code WHERE r.Code IS NULL) AS ecarts
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
          AND d.Code NOT IN (SELECT code FROM ctl_licence_cd)
          AND d.Code NOT IN (SELECT code FROM ctl_limitation))
UNION ALL SELECT 'Clubs hors PCE non référencés',
       (SELECT COUNT(*) FROM kp_club c JOIN kp_cd d ON d.Code = c.Code_comite_dep
        WHERE d.Code_comite_reg <> '98'
          AND NOT (c.Code = c.Code_comite_dep AND d.Code_comite_reg = c.Code)
          AND c.Code NOT IN (SELECT code FROM ctl_pce_club)
          AND c.Code NOT IN (SELECT code FROM ctl_licence_club)
          AND c.Code NOT IN (SELECT code FROM ctl_limitation)
          AND c.Code NOT IN (SELECT Code_club FROM kp_equipe WHERE Code_club IS NOT NULL)
          AND c.Code NOT IN (SELECT Code_club FROM kp_competition_equipe WHERE Code_club IS NOT NULL))
UNION ALL SELECT 'Pseudo-CD d''Anciennes ligues restants',
       (SELECT COUNT(*) FROM ctl_ancien_cd)
UNION ALL SELECT 'Pseudo-clubs d''Anciennes ligues restants',
       (SELECT COUNT(*) FROM ctl_ancien_club)
UNION ALL SELECT 'Équipes sur un pseudo-club d''Ancienne ligue',
       (SELECT COUNT(*) FROM kp_equipe WHERE Code_club IN (SELECT code FROM ctl_ancien_club))
UNION ALL SELECT 'Engagements sur un pseudo-club d''Ancienne ligue',
       (SELECT COUNT(*) FROM kp_competition_equipe WHERE Code_club IN (SELECT code FROM ctl_ancien_club))
UNION ALL SELECT 'Utilisateurs et mandats limités à un pseudo-club d''Ancienne ligue',
       (SELECT COUNT(*) FROM (
            SELECT Limitation_equipe_club AS lim FROM kp_user
            UNION ALL SELECT limitation_equipe_club FROM kp_user_mandat
        ) l
        WHERE EXISTS (SELECT 1 FROM ctl_ancien_club a WHERE FIND_IN_SET(a.code, REPLACE(l.lim, ' ', ''))))
UNION ALL SELECT 'Codes REGxx/T-Rxx/CRxx (compétitions, références, groupes) hors CR actuelles',
       (SELECT COUNT(*) FROM ctl_code k WHERE CONCAT('CR', k.numero) NOT IN (SELECT Code FROM kp_cr))
UNION ALL SELECT 'Filtres utilisateur/mandat citant un code REGxx/T-Rxx hors CR actuelles',
       (SELECT COUNT(*) FROM (
            SELECT Filtre_competition AS f FROM kp_user
            UNION ALL SELECT filtre_competition FROM kp_user_mandat
        ) l
        WHERE EXISTS (SELECT 1 FROM ctl_code k
                      WHERE CONCAT('CR', k.numero) NOT IN (SELECT Code FROM kp_cr)
                        AND l.f LIKE CONCAT('%|', k.code, '|%')))
UNION ALL SELECT 'Remise à plat appliquée (1 = oui)',
       (SELECT COUNT(*) > 0 FROM kp_journal WHERE Actions = 'Remise à plat structures FFCK');

-- Détail des codes régionaux hors CR actuelles (vide après le script)
SELECT k.code AS code_hors_cr_actuelle
FROM ctl_code k
WHERE CONCAT('CR', k.numero) NOT IN (SELECT Code FROM kp_cr)
ORDER BY k.code;
