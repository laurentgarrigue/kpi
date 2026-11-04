-- Remise à plat : réalignement des structures fédérales et des codes régionaux sur le PCE
-- Date : 2026-10-29
-- Spec : DOC/developer/in-progress/REALIGNEMENT_STRUCTURES_FFCK.md (« Script de remise à plat »)
-- ADR  : DOC/developer/reference/adr/0001-codes-regionaux-alignes-sur-les-cr-actuels.md
-- Déroulé par environnement : DOC/developer/in-progress/REALIGNEMENT_STRUCTURES_FFCK_ROLLOUT.md
--
-- Exécution UNIQUE par environnement, APRÈS un import PCE fait avec l'import corrigé, et après un dump :
--   make db_migrate file=SQL/migrations/2026-10-29_realignement_structures_ffck.sql
-- Contrôle avant/après : SQL/migrations/2026-10-29_realignement_structures_ffck_controle.sql
--
-- Garanties :
-- - Une seule transaction : toute erreur annule tout (gestionnaire d'erreur ROLLBACK + RESIGNAL).
-- - Garde-fou : avant toute écriture, le script s'arrête (erreur 45000) en listant les codes d'Anciennes
--   ligues ou de Compétitions régionales absents de la table de correspondance, les CR cibles absentes,
--   les collisions de codes et les codes dont la forme temporaire « ~<nouveau> » dépasserait la largeur de
--   sa colonne (kp_competition.Code, kp_groupe.Groupe : lue dans information_schema). Rien n'est modifié.
-- - Rejouable : une seconde exécution ne modifie rien. Les structures sont recalculées depuis l'état ;
--   le renommage des codes compétition ne s'applique qu'une fois (repère : ligne
--   « Remise à plat structures FFCK » dans kp_journal), car un code renommé (REG03 = Bretagne)
--   ne se distingue pas d'un ancien code (REG03 = Aquitaine).
--
-- Ce que fait le script :
-- 1. CD et Clubs : rattachement recalculé depuis les licenciés de la saison du dernier import
--    (MAX(kp_licence.Origine)), avec les filtres de l'import. Les libellés viennent de l'import corrigé
--    lui-même (précondition) : il vide les noms de structures de kp_licence à la fin de chaque exécution,
--    ils ne sont donc pas recalculables ici.
-- 2. Sélections régionales : un pseudo-CD et un pseudo-club par CR concernée, au code et au libellé de la
--    CR ; équipes, engagements (et leur logo KIP/logo/CRxx-logo.png) et limitations utilisateur des
--    pseudo-clubs d'Anciennes ligues réaffectés ;
--    pseudo-clubs et pseudo-CD d'Anciennes ligues supprimés.
-- 3. CD et Clubs hors PCE non référencés supprimés (dont les CD au format brut CDxxx). Référencé = Club
--    rattaché (CD), licencié de n'importe quelle saison, équipe, engagement, limitation utilisateur ou
--    mandat. Les structures internationales (98) sont conservées.
-- 4. Codes REGxx*/T-Rxx* (et codes de référence, groupes CRxx) renommés sur toutes les saisons selon la
--    table de l'ADR 0001, suffixe conservé ; mêmes références que l'opération « changer un code
--    compétition » (compétitions, engagements, journées, RC, Code_ref, multi-compétitions, filtres
--    utilisateur et filtres de mandat) + kp_groupe (cible de la clé étrangère de Code_ref) et kp_form.

SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;

DELIMITER //

BEGIN NOT ATOMIC
    DECLARE v_season VARCHAR(4);
    DECLARE v_already_renamed INT DEFAULT 0;
    DECLARE v_blocker_count INT DEFAULT 0;
    DECLARE v_i INT DEFAULT 0;
    DECLARE v_code_count INT DEFAULT 0;
    DECLARE v_old_code VARCHAR(12);
    DECLARE v_new_code VARCHAR(12);
    DECLARE v_token VARCHAR(12);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET FOREIGN_KEY_CHECKS = 1;
        RESIGNAL;
    END;

    SET v_season = (SELECT MAX(Origine) FROM kp_licence);
    SET v_already_renamed = (SELECT COUNT(*) FROM kp_journal WHERE Actions = 'Remise à plat structures FFCK');

    -- ------------------------------------------------------------------------------------------
    -- Tables de travail (temporaires : aucune validation implicite de transaction)
    -- ------------------------------------------------------------------------------------------
    DROP TEMPORARY TABLE IF EXISTS rsf_league, rsf_code_exception, rsf_code, rsf_old_league_cd, rsf_old_league_club,
        rsf_selection, rsf_pce_cd, rsf_pce_club, rsf_licensee_club, rsf_licensee_cd, rsf_limitation_item,
        rsf_new_limitation, rsf_limitation, rsf_blocker;

    -- Table de correspondance Ancienne ligue → CR actuelle (ADR 0001)
    CREATE TEMPORARY TABLE rsf_league (old_number CHAR(2) PRIMARY KEY, current_number CHAR(2) NOT NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
    INSERT INTO rsf_league VALUES
        ('02', '05'), ('09', '05'), ('17', '05'),
        ('03', '09'), ('16', '09'), ('22', '09'),
        ('04', '11'), ('07', '03'), ('08', '04'),
        ('12', '06'), ('21', '06'), ('13', '02'),
        ('14', '07'), ('15', '10'), ('19', '10'),
        ('18', '01'), ('20', '08'), ('23', '12'),
        ('25', '24'), ('29', '21');

    -- Exceptions de l'ADR 0001 pour les codes compétition nommés à tort en 2018
    CREATE TEMPORARY TABLE rsf_code_exception (code VARCHAR(12) PRIMARY KEY, new_code VARCHAR(12) NOT NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
    INSERT INTO rsf_code_exception VALUES
        ('REG09', 'REG09'), ('REG09B', 'REG09B'),
        ('REG23', 'REG24'), ('CR23', 'CR24');

    CREATE TEMPORARY TABLE rsf_blocker (reason VARCHAR(60) NOT NULL, code VARCHAR(60) NOT NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

    -- ------------------------------------------------------------------------------------------
    -- Codes de Compétitions régionales à renommer (une seule fois)
    -- ------------------------------------------------------------------------------------------
    CREATE TEMPORARY TABLE rsf_code (
        id INT AUTO_INCREMENT PRIMARY KEY,
        old_code VARCHAR(12) NOT NULL UNIQUE,
        new_code VARCHAR(12) NULL
    ) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
    IF v_already_renamed = 0 THEN
        INSERT INTO rsf_code (old_code, new_code)
        SELECT c.code,
               COALESCE(e.new_code, CONCAT(c.prefix, l.current_number, SUBSTRING(c.code, LENGTH(c.prefix) + 3)))
        FROM (
            SELECT code,
                   CASE WHEN code LIKE 'T-R%' THEN 'T-R' WHEN code LIKE 'REG%' THEN 'REG' ELSE 'CR' END AS prefix
            FROM (
                SELECT Code AS code FROM kp_competition
                UNION SELECT Code_ref FROM kp_competition
                UNION SELECT Groupe FROM kp_groupe
            ) u
            WHERE code REGEXP '^(REG|T-R)[0-9]{2}' OR code REGEXP '^CR[0-9]{2}$'
        ) c
        LEFT JOIN rsf_code_exception e ON e.code = c.code
        LEFT JOIN rsf_league l ON l.old_number = SUBSTRING(c.code, LENGTH(c.prefix) + 1, 2)
        ORDER BY c.code;

        INSERT INTO rsf_blocker SELECT 'Code régional hors table de correspondance', old_code
        FROM rsf_code WHERE new_code IS NULL;

        DELETE FROM rsf_code WHERE new_code = old_code;

        -- Fusion impossible : deux codes qui deviendraient identiques sur une même saison
        INSERT INTO rsf_blocker
        SELECT 'Collision de codes compétition', CONCAT(f.code, ' (', f.season, ')')
        FROM (
            SELECT COALESCE(m.new_code, c.Code) AS code, c.Code_saison AS season
            FROM kp_competition c LEFT JOIN rsf_code m ON m.old_code = c.Code
        ) f
        GROUP BY f.code, f.season HAVING COUNT(*) > 1;

        INSERT INTO rsf_blocker
        SELECT 'Collision de groupes', f.code
        FROM (SELECT COALESCE(m.new_code, g.Groupe) AS code FROM kp_groupe g LEFT JOIN rsf_code m ON m.old_code = g.Groupe) f
        GROUP BY f.code HAVING COUNT(*) > 1;

        -- Code temporaire « ~<nouveau> » (clés uniques, § 4) plus large que sa colonne : le serveur (sql_mode non
        -- strict) le tronquerait sans erreur et la seconde passe produirait un code faux
        INSERT INTO rsf_blocker
        SELECT CONCAT('Code temporaire trop long pour ', w.TABLE_NAME, '.', w.COLUMN_NAME), m.old_code
        FROM rsf_code m
        JOIN information_schema.COLUMNS w
          ON w.TABLE_SCHEMA = DATABASE()
         AND ((w.TABLE_NAME = 'kp_competition' AND w.COLUMN_NAME = 'Code'
               AND m.old_code IN (SELECT Code FROM kp_competition))
           OR (w.TABLE_NAME = 'kp_groupe' AND w.COLUMN_NAME = 'Groupe'
               AND m.old_code IN (SELECT Groupe FROM kp_groupe)))
        WHERE CHAR_LENGTH(m.new_code) + 1 > w.CHARACTER_MAXIMUM_LENGTH;
    END IF;

    -- ------------------------------------------------------------------------------------------
    -- Anciennes ligues : pseudo-CD (code CRxx rattaché à une autre CR) et pseudo-clubs
    -- ------------------------------------------------------------------------------------------
    CREATE TEMPORARY TABLE rsf_old_league_cd (code VARCHAR(6) PRIMARY KEY, current_number CHAR(2) NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT d.Code AS code, l.current_number
        FROM kp_cd d LEFT JOIN rsf_league l ON l.old_number = SUBSTRING(d.Code, 3, 2)
        WHERE d.Code REGEXP '^CR[0-9]{2}$' AND d.Code <> d.Code_comite_reg;

    CREATE TEMPORARY TABLE rsf_old_league_club (code VARCHAR(6) PRIMARY KEY, current_number CHAR(2) NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT c.Code AS code, l.current_number
        FROM kp_club c
        LEFT JOIN kp_cd d ON d.Code = c.Code_comite_dep
        LEFT JOIN rsf_league l ON l.old_number = SUBSTRING(c.Code, 3, 2)
        WHERE c.Code REGEXP '^CR[0-9]{2}$'
          AND NOT (c.Code = c.Code_comite_dep AND d.Code_comite_reg = c.Code);

    INSERT INTO rsf_blocker SELECT 'Pseudo-CD d''Ancienne ligue hors table', code FROM rsf_old_league_cd WHERE current_number IS NULL;
    INSERT INTO rsf_blocker SELECT 'Pseudo-club d''Ancienne ligue hors table', code FROM rsf_old_league_club WHERE current_number IS NULL;
    -- Club au code non CRxx rattaché à un pseudo-CD d'Ancienne ligue : à traiter à la main
    INSERT INTO rsf_blocker
    SELECT 'Club rattaché à un pseudo-CD d''Ancienne ligue', CONCAT(c.Code, ' (', c.Code_comite_dep, ')')
    FROM kp_club c
    WHERE c.Code_comite_dep IN (SELECT code FROM rsf_old_league_cd)
      AND c.Code NOT IN (SELECT code FROM rsf_old_league_club)
      AND (c.Code IN (SELECT Code_club FROM kp_equipe WHERE Code_club IS NOT NULL)
        OR c.Code IN (SELECT Code_club FROM kp_competition_equipe WHERE Code_club IS NOT NULL));

    -- Sélections régionales : CR cibles des Anciennes ligues + Sélections déjà en place
    CREATE TEMPORARY TABLE rsf_selection (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT CONCAT('CR', current_number) AS code FROM rsf_old_league_cd WHERE current_number IS NOT NULL
        UNION SELECT CONCAT('CR', current_number) FROM rsf_old_league_club WHERE current_number IS NOT NULL
        UNION SELECT c.Code FROM kp_club c JOIN kp_cd d ON d.Code = c.Code_comite_dep
              WHERE c.Code REGEXP '^CR[0-9]{2}$' AND c.Code = c.Code_comite_dep AND d.Code_comite_reg = c.Code;

    INSERT INTO rsf_blocker SELECT 'CR cible absente de kp_cr', code FROM rsf_selection WHERE code NOT IN (SELECT Code FROM kp_cr);

    -- ------------------------------------------------------------------------------------------
    -- Garde-fou : rien n'est écrit si un code n'est pas couvert
    -- ------------------------------------------------------------------------------------------
    SET v_blocker_count = (SELECT COUNT(*) FROM rsf_blocker);
    IF v_blocker_count > 0 THEN
        SELECT reason AS blocker, code FROM rsf_blocker ORDER BY reason, code;
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Garde-fou : codes non couverts par la table de correspondance, aucune modification';
    END IF;

    -- ------------------------------------------------------------------------------------------
    -- Référentiels (« PCE » = licenciés de la saison du dernier import, filtres de l'import)
    -- ------------------------------------------------------------------------------------------
    CREATE TEMPORARY TABLE rsf_pce_cd (code VARCHAR(6) PRIMARY KEY, cr VARCHAR(6)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT Numero_comite_dept AS code, MIN(Numero_comite_reg) AS cr
        FROM kp_licence
        WHERE Origine = v_season AND Numero_club NOT IN ('0', '0000') AND Numero_comite_reg <> '98'
          AND Numero_comite_dept IS NOT NULL
        GROUP BY Numero_comite_dept;

    CREATE TEMPORARY TABLE rsf_pce_club (code VARCHAR(6) PRIMARY KEY, cd VARCHAR(6)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT Numero_club AS code, MIN(Numero_comite_dept) AS cd
        FROM kp_licence
        WHERE Origine = v_season AND Numero_club NOT IN ('0', '0000') AND Numero_comite_reg <> '98'
        GROUP BY Numero_club;

    CREATE TEMPORARY TABLE rsf_licensee_club (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT DISTINCT Numero_club AS code FROM kp_licence WHERE Numero_club IS NOT NULL;

    CREATE TEMPORARY TABLE rsf_licensee_cd (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT DISTINCT Numero_comite_dept AS code FROM kp_licence WHERE Numero_comite_dept IS NOT NULL;

    -- Limitations d'équipes des utilisateurs et des mandats (codes séparés par des virgules), un code par ligne.
    -- Découpage par SUBSTRING_INDEX : aucun caractère d'un code ne peut casser le découpage.
    CREATE TEMPORARY TABLE rsf_limitation_item (
        owner_table VARCHAR(20) NOT NULL,
        owner_key VARCHAR(20) NOT NULL,
        code VARCHAR(50) NOT NULL
    ) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
    INSERT INTO rsf_limitation_item (owner_table, owner_key, code)
    WITH RECURSIVE owner_list AS (
        SELECT 'kp_user' AS owner_table, CAST(Code AS CHAR(20)) AS owner_key, CAST(Limitation_equipe_club AS CHAR(100)) AS list
        FROM kp_user WHERE Limitation_equipe_club <> ''
        UNION ALL
        SELECT 'kp_user_mandat', CAST(id AS CHAR(20)), CAST(limitation_equipe_club AS CHAR(100))
        FROM kp_user_mandat WHERE limitation_equipe_club <> ''
    ), item AS (
        SELECT owner_table, owner_key, SUBSTRING_INDEX(list, ',', 1) AS code,
               IF(LOCATE(',', list) > 0, SUBSTRING(list, LOCATE(',', list) + 1), NULL) AS rest
        FROM owner_list
        UNION ALL
        SELECT owner_table, owner_key, SUBSTRING_INDEX(rest, ',', 1),
               IF(LOCATE(',', rest) > 0, SUBSTRING(rest, LOCATE(',', rest) + 1), NULL)
        FROM item WHERE rest IS NOT NULL
    )
    SELECT owner_table, owner_key, TRIM(code) FROM item WHERE TRIM(code) <> '';

    START TRANSACTION;

    -- ------------------------------------------------------------------------------------------
    -- 1. Rattachements CD → CR et Club → CD alignés sur le PCE
    -- ------------------------------------------------------------------------------------------
    UPDATE kp_cd d JOIN rsf_pce_cd p ON p.code = d.Code
    SET d.Code_comite_reg = p.cr
    WHERE d.Code_comite_reg <> p.cr AND p.cr IN (SELECT Code FROM kp_cr);

    UPDATE kp_club c JOIN rsf_pce_club p ON p.code = c.Code
    SET c.Code_comite_dep = p.cd
    WHERE c.Code_comite_dep <> p.cd AND p.cd IN (SELECT Code FROM kp_cd);

    -- ------------------------------------------------------------------------------------------
    -- 2. Sélections régionales
    -- ------------------------------------------------------------------------------------------
    -- Pseudo-CD au code et au libellé de la CR, rattaché à la CR (écrase un ancien pseudo-CD de même code)
    INSERT INTO kp_cd (Code, Libelle, Officiel, Reserve, Code_comite_reg)
    SELECT s.code, r.Libelle, 'O', '', s.code
    FROM rsf_selection s JOIN kp_cr r ON r.Code = s.code
    ON DUPLICATE KEY UPDATE Libelle = VALUES(Libelle), Code_comite_reg = VALUES(Code_comite_reg);

    -- Pseudo-club au code et au libellé de la CR, rattaché au pseudo-CD
    INSERT INTO kp_club (Code, Libelle, Officiel, Reserve, Code_comite_dep)
    SELECT s.code, r.Libelle, 'O', '', s.code
    FROM rsf_selection s JOIN kp_cr r ON r.Code = s.code
    ON DUPLICATE KEY UPDATE Libelle = VALUES(Libelle), Code_comite_dep = VALUES(Code_comite_dep);

    -- Équipes et engagements : réaffectation simultanée (chaque ligne n'est lue qu'une fois),
    -- ce qui règle le chevauchement des numéros (ancien CR07 → CR03 pendant qu'ancien CR03 → CR09).
    -- Le logo du pseudo-club (KIP/logo/CRxx-logo.png) suit : sinon une équipe bretonne afficherait le
    -- fichier CR07-logo.png, qui devient celui de l'Île-de-France (fichiers : voir la rollout checklist).
    UPDATE kp_equipe e JOIN rsf_old_league_club a ON a.code = e.Code_club
    SET e.logo = IF(e.logo = CONCAT('KIP/logo/', a.code, '-logo.png'), CONCAT('KIP/logo/CR', a.current_number, '-logo.png'), e.logo),
        e.Code_club = CONCAT('CR', a.current_number);

    UPDATE kp_competition_equipe e JOIN rsf_old_league_club a ON a.code = e.Code_club
    SET e.logo = IF(e.logo = CONCAT('KIP/logo/', a.code, '-logo.png'), CONCAT('KIP/logo/CR', a.current_number, '-logo.png'), e.logo),
        e.Code_club = CONCAT('CR', a.current_number);

    -- Limitations d'équipes citant un pseudo-club d'Ancienne ligue, réécrites sans doublon
    CREATE TEMPORARY TABLE rsf_new_limitation (
        owner_table VARCHAR(20) NOT NULL,
        owner_key VARCHAR(20) NOT NULL,
        limitation VARCHAR(255) NOT NULL,
        PRIMARY KEY (owner_table, owner_key)
    ) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT i.owner_table, i.owner_key,
               GROUP_CONCAT(DISTINCT COALESCE(CONCAT('CR', a.current_number), i.code) SEPARATOR ',') AS limitation
        FROM rsf_limitation_item i
        LEFT JOIN rsf_old_league_club a ON a.code = i.code
        GROUP BY i.owner_table, i.owner_key
        HAVING SUM(a.code IS NOT NULL) > 0;

    UPDATE kp_user u JOIN rsf_new_limitation n ON n.owner_table = 'kp_user' AND n.owner_key = u.Code
    SET u.Limitation_equipe_club = n.limitation;

    UPDATE kp_user_mandat u JOIN rsf_new_limitation n ON n.owner_table = 'kp_user_mandat' AND n.owner_key = u.id
    SET u.limitation_equipe_club = n.limitation;

    -- Pseudo-clubs puis pseudo-CD d'Anciennes ligues (sauf ceux devenus Sélection), et Clubs non
    -- référencés encore rattachés à un pseudo-CD d'Ancienne ligue (garde-fou : aucun n'est référencé)
    DELETE FROM kp_club
    WHERE Code NOT IN (SELECT code FROM rsf_selection)
      AND (Code IN (SELECT code FROM rsf_old_league_club)
           OR Code_comite_dep IN (SELECT code FROM rsf_old_league_cd WHERE code NOT IN (SELECT code FROM rsf_selection)));

    DELETE FROM kp_cd
    WHERE Code IN (SELECT code FROM rsf_old_league_cd) AND Code NOT IN (SELECT code FROM rsf_selection);

    -- ------------------------------------------------------------------------------------------
    -- 3. Clubs puis CD hors PCE non référencés (structures 98 et Sélections conservées)
    -- ------------------------------------------------------------------------------------------
    -- Codes cités par les limitations après leur réécriture
    CREATE TEMPORARY TABLE rsf_limitation (code VARCHAR(50) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT DISTINCT COALESCE(CONCAT('CR', a.current_number), i.code) AS code
        FROM rsf_limitation_item i LEFT JOIN rsf_old_league_club a ON a.code = i.code;

    DELETE c FROM kp_club c JOIN kp_cd d ON d.Code = c.Code_comite_dep
    WHERE d.Code_comite_reg <> '98'
      AND c.Code NOT IN (SELECT code FROM rsf_selection)
      AND c.Code NOT IN (SELECT code FROM rsf_pce_club)
      AND c.Code NOT IN (SELECT code FROM rsf_licensee_club)
      AND c.Code NOT IN (SELECT code FROM rsf_limitation)
      AND c.Code NOT IN (SELECT Code_club FROM kp_equipe WHERE Code_club IS NOT NULL)
      AND c.Code NOT IN (SELECT Code_club FROM kp_competition_equipe WHERE Code_club IS NOT NULL);

    DELETE FROM kp_cd
    WHERE Code_comite_reg <> '98'
      AND Code NOT IN (SELECT code FROM rsf_pce_cd)
      AND Code NOT IN (SELECT Code_comite_dep FROM kp_club)
      AND Code NOT IN (SELECT code FROM rsf_licensee_cd)
      AND Code NOT IN (SELECT code FROM rsf_limitation);

    -- ------------------------------------------------------------------------------------------
    -- 4. Codes des Compétitions régionales (toutes saisons), une seule fois
    -- ------------------------------------------------------------------------------------------
    IF v_already_renamed = 0 THEN
        SET FOREIGN_KEY_CHECKS = 0;

        -- Clés uniques (compétitions, groupes) : deux passes par un code temporaire « ~<nouveau> »,
        -- pour que REG14 → REG07 ne heurte pas l'ancien REG07 encore en place (largeur vérifiée par le garde-fou)
        UPDATE kp_competition c JOIN rsf_code m ON m.old_code = c.Code SET c.Code = CONCAT('~', m.new_code);
        UPDATE kp_competition SET Code = SUBSTRING(Code, 2) WHERE Code LIKE '~%';
        UPDATE kp_groupe g JOIN rsf_code m ON m.old_code = g.Groupe SET g.Groupe = CONCAT('~', m.new_code);
        UPDATE kp_groupe SET Groupe = SUBSTRING(Groupe, 2) WHERE Groupe LIKE '~%';

        -- Colonnes sans contrainte d'unicité : une passe (chaque ligne n'est lue qu'une fois)
        UPDATE kp_competition c JOIN rsf_code m ON m.old_code = c.Code_ref SET c.Code_ref = m.new_code;
        UPDATE kp_competition_equipe e JOIN rsf_code m ON m.old_code = e.Code_compet SET e.Code_compet = m.new_code;
        UPDATE kp_journee j JOIN rsf_code m ON m.old_code = j.Code_competition SET j.Code_competition = m.new_code;
        UPDATE kp_rc r JOIN rsf_code m ON m.old_code = r.Code_competition SET r.Code_competition = m.new_code;
        UPDATE kp_form f JOIN rsf_code m ON m.old_code = f.competition_code SET f.competition_code = m.new_code;

        -- Codes inclus dans des listes (filtres, multi-compétitions) : passe 1 vers un jeton
        -- temporaire unique « ~<id>~ », passe 2 vers le nouveau code
        SET v_code_count = (SELECT COUNT(*) FROM rsf_code);
        SET v_i = 0;
        WHILE v_i < v_code_count DO
            SELECT old_code, CONCAT('~', id, '~') INTO v_old_code, v_token FROM rsf_code ORDER BY id LIMIT v_i, 1;
            UPDATE kp_user
            SET Filtre_competition = REPLACE(Filtre_competition, CONCAT('|', v_old_code, '|'), CONCAT('|', v_token, '|')),
                Filtre_competition_sql = REPLACE(Filtre_competition_sql, CONCAT('''', v_old_code, ''''), CONCAT('''', v_token, ''''))
            WHERE Filtre_competition LIKE CONCAT('%|', v_old_code, '|%')
               OR Filtre_competition_sql LIKE CONCAT('%''', v_old_code, '''%');
            UPDATE kp_user_mandat
            SET filtre_competition = REPLACE(filtre_competition, CONCAT('|', v_old_code, '|'), CONCAT('|', v_token, '|'))
            WHERE filtre_competition LIKE CONCAT('%|', v_old_code, '|%');
            UPDATE kp_competition
            SET multi_competitions = REPLACE(multi_competitions, CONCAT('"', v_old_code, '"'), CONCAT('"', v_token, '"'))
            WHERE multi_competitions LIKE CONCAT('%"', v_old_code, '"%');
            SET v_i = v_i + 1;
        END WHILE;

        SET v_i = 0;
        WHILE v_i < v_code_count DO
            SELECT new_code, CONCAT('~', id, '~') INTO v_new_code, v_token FROM rsf_code ORDER BY id LIMIT v_i, 1;
            UPDATE kp_user
            SET Filtre_competition = REPLACE(Filtre_competition, v_token, v_new_code),
                Filtre_competition_sql = REPLACE(Filtre_competition_sql, v_token, v_new_code)
            WHERE Filtre_competition LIKE CONCAT('%', v_token, '%') OR Filtre_competition_sql LIKE CONCAT('%', v_token, '%');
            UPDATE kp_user_mandat SET filtre_competition = REPLACE(filtre_competition, v_token, v_new_code)
            WHERE filtre_competition LIKE CONCAT('%', v_token, '%');
            UPDATE kp_competition SET multi_competitions = REPLACE(multi_competitions, v_token, v_new_code)
            WHERE multi_competitions LIKE CONCAT('%', v_token, '%');
            SET v_i = v_i + 1;
        END WHILE;

        SET FOREIGN_KEY_CHECKS = 1;

        -- Repère d'exécution (rend le renommage non rejouable)
        INSERT INTO kp_journal (Dates, Users, Actions, Journal)
        VALUES (NOW(), 'SCRIPT', 'Remise à plat structures FFCK',
                CONCAT('SQL/migrations/2026-10-29_realignement_structures_ffck.sql : ', v_code_count, ' codes régionaux renommés'));
    END IF;

    COMMIT;

    SELECT 'Remise à plat terminée' AS result, v_season AS pce_season,
           (SELECT COUNT(*) FROM rsf_selection) AS regional_selections,
           (SELECT COUNT(*) FROM rsf_code) AS renamed_codes;
END//

DELIMITER ;
