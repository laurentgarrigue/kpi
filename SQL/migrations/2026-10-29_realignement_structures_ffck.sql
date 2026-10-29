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
--   ligues ou de Compétitions régionales absents de la table de correspondance, les CR cibles absentes et
--   les collisions de codes. Rien n'est modifié.
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
    DECLARE v_saison VARCHAR(4);
    DECLARE v_deja_renomme INT DEFAULT 0;
    DECLARE v_nb_bloquants INT DEFAULT 0;
    DECLARE v_i INT DEFAULT 0;
    DECLARE v_nb_codes INT DEFAULT 0;
    DECLARE v_ancien VARCHAR(12);
    DECLARE v_nouveau VARCHAR(12);
    DECLARE v_temp VARCHAR(12);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET FOREIGN_KEY_CHECKS = 1;
        RESIGNAL;
    END;

    SET v_saison = (SELECT MAX(Origine) FROM kp_licence);
    SET v_deja_renomme = (SELECT COUNT(*) FROM kp_journal WHERE Actions = 'Remise à plat structures FFCK');

    -- ------------------------------------------------------------------------------------------
    -- Tables de travail (temporaires : aucune validation implicite de transaction)
    -- ------------------------------------------------------------------------------------------
    DROP TEMPORARY TABLE IF EXISTS rsf_ligue, rsf_code_exception, rsf_code, rsf_ancien_cd, rsf_ancien_club,
        rsf_selection, rsf_pce_cd, rsf_pce_club, rsf_licence_club, rsf_licence_cd, rsf_limitation, rsf_bloquant;

    -- Table de correspondance Ancienne ligue → CR actuelle (ADR 0001)
    CREATE TEMPORARY TABLE rsf_ligue (ancien CHAR(2) PRIMARY KEY, actuel CHAR(2) NOT NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
    INSERT INTO rsf_ligue VALUES
        ('02', '05'), ('09', '05'), ('17', '05'),
        ('03', '09'), ('16', '09'), ('22', '09'),
        ('04', '11'), ('07', '03'), ('08', '04'),
        ('12', '06'), ('21', '06'), ('13', '02'),
        ('14', '07'), ('15', '10'), ('19', '10'),
        ('18', '01'), ('20', '08'), ('23', '12'),
        ('25', '24'), ('29', '21');

    -- Exceptions de l'ADR 0001 pour les codes compétition nommés à tort en 2018
    CREATE TEMPORARY TABLE rsf_code_exception (code VARCHAR(12) PRIMARY KEY, nouveau VARCHAR(12) NOT NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
    INSERT INTO rsf_code_exception VALUES
        ('REG09', 'REG09'), ('REG09B', 'REG09B'),
        ('REG23', 'REG24'), ('CR23', 'CR24');

    CREATE TEMPORARY TABLE rsf_bloquant (motif VARCHAR(60) NOT NULL, code VARCHAR(60) NOT NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

    -- ------------------------------------------------------------------------------------------
    -- Codes de Compétitions régionales à renommer (une seule fois)
    -- ------------------------------------------------------------------------------------------
    CREATE TEMPORARY TABLE rsf_code (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ancien VARCHAR(12) NOT NULL UNIQUE,
        nouveau VARCHAR(12) NULL
    ) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
    IF v_deja_renomme = 0 THEN
        INSERT INTO rsf_code (ancien, nouveau)
        SELECT c.code,
               COALESCE(e.nouveau, CONCAT(c.prefixe, l.actuel, SUBSTRING(c.code, LENGTH(c.prefixe) + 3)))
        FROM (
            SELECT code,
                   CASE WHEN code LIKE 'T-R%' THEN 'T-R' WHEN code LIKE 'REG%' THEN 'REG' ELSE 'CR' END AS prefixe
            FROM (
                SELECT Code AS code FROM kp_competition
                UNION SELECT Code_ref FROM kp_competition
                UNION SELECT Groupe FROM kp_groupe
            ) u
            WHERE code REGEXP '^(REG|T-R)[0-9]{2}' OR code REGEXP '^CR[0-9]{2}$'
        ) c
        LEFT JOIN rsf_code_exception e ON e.code = c.code
        LEFT JOIN rsf_ligue l ON l.ancien = SUBSTRING(c.code, LENGTH(c.prefixe) + 1, 2)
        ORDER BY c.code;

        INSERT INTO rsf_bloquant SELECT 'Code régional hors table de correspondance', ancien
        FROM rsf_code WHERE nouveau IS NULL;

        DELETE FROM rsf_code WHERE nouveau = ancien;

        -- Fusion impossible : deux codes qui deviendraient identiques sur une même saison
        INSERT INTO rsf_bloquant
        SELECT 'Collision de codes compétition', CONCAT(f.code, ' (', f.saison, ')')
        FROM (
            SELECT COALESCE(m.nouveau, c.Code) AS code, c.Code_saison AS saison
            FROM kp_competition c LEFT JOIN rsf_code m ON m.ancien = c.Code
        ) f
        GROUP BY f.code, f.saison HAVING COUNT(*) > 1;

        INSERT INTO rsf_bloquant
        SELECT 'Collision de groupes', f.code
        FROM (SELECT COALESCE(m.nouveau, g.Groupe) AS code FROM kp_groupe g LEFT JOIN rsf_code m ON m.ancien = g.Groupe) f
        GROUP BY f.code HAVING COUNT(*) > 1;
    END IF;

    -- ------------------------------------------------------------------------------------------
    -- Anciennes ligues : pseudo-CD (code CRxx rattaché à une autre CR) et pseudo-clubs
    -- ------------------------------------------------------------------------------------------
    CREATE TEMPORARY TABLE rsf_ancien_cd (code VARCHAR(6) PRIMARY KEY, actuel CHAR(2) NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT d.Code AS code, l.actuel
        FROM kp_cd d LEFT JOIN rsf_ligue l ON l.ancien = SUBSTRING(d.Code, 3, 2)
        WHERE d.Code REGEXP '^CR[0-9]{2}$' AND d.Code <> d.Code_comite_reg;

    CREATE TEMPORARY TABLE rsf_ancien_club (code VARCHAR(6) PRIMARY KEY, actuel CHAR(2) NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT c.Code AS code, l.actuel
        FROM kp_club c
        LEFT JOIN kp_cd d ON d.Code = c.Code_comite_dep
        LEFT JOIN rsf_ligue l ON l.ancien = SUBSTRING(c.Code, 3, 2)
        WHERE c.Code REGEXP '^CR[0-9]{2}$'
          AND NOT (c.Code = c.Code_comite_dep AND d.Code_comite_reg = c.Code);

    INSERT INTO rsf_bloquant SELECT 'Pseudo-CD d''Ancienne ligue hors table', code FROM rsf_ancien_cd WHERE actuel IS NULL;
    INSERT INTO rsf_bloquant SELECT 'Pseudo-club d''Ancienne ligue hors table', code FROM rsf_ancien_club WHERE actuel IS NULL;
    -- Club au code non CRxx rattaché à un pseudo-CD d'Ancienne ligue : à traiter à la main
    INSERT INTO rsf_bloquant
    SELECT 'Club rattaché à un pseudo-CD d''Ancienne ligue', CONCAT(c.Code, ' (', c.Code_comite_dep, ')')
    FROM kp_club c
    WHERE c.Code_comite_dep IN (SELECT code FROM rsf_ancien_cd)
      AND c.Code NOT IN (SELECT code FROM rsf_ancien_club)
      AND (c.Code IN (SELECT Code_club FROM kp_equipe WHERE Code_club IS NOT NULL)
        OR c.Code IN (SELECT Code_club FROM kp_competition_equipe WHERE Code_club IS NOT NULL));

    -- Sélections régionales : CR cibles des Anciennes ligues + Sélections déjà en place
    CREATE TEMPORARY TABLE rsf_selection (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT CONCAT('CR', actuel) AS code FROM rsf_ancien_cd WHERE actuel IS NOT NULL
        UNION SELECT CONCAT('CR', actuel) FROM rsf_ancien_club WHERE actuel IS NOT NULL
        UNION SELECT c.Code FROM kp_club c JOIN kp_cd d ON d.Code = c.Code_comite_dep
              WHERE c.Code REGEXP '^CR[0-9]{2}$' AND c.Code = c.Code_comite_dep AND d.Code_comite_reg = c.Code;

    INSERT INTO rsf_bloquant SELECT 'CR cible absente de kp_cr', code FROM rsf_selection WHERE code NOT IN (SELECT Code FROM kp_cr);

    -- ------------------------------------------------------------------------------------------
    -- Garde-fou : rien n'est écrit si un code n'est pas couvert
    -- ------------------------------------------------------------------------------------------
    SET v_nb_bloquants = (SELECT COUNT(*) FROM rsf_bloquant);
    IF v_nb_bloquants > 0 THEN
        SELECT motif AS garde_fou, code FROM rsf_bloquant ORDER BY motif, code;
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Garde-fou : codes non couverts par la table de correspondance, aucune modification';
    END IF;

    -- ------------------------------------------------------------------------------------------
    -- Référentiels (« PCE » = licenciés de la saison du dernier import, filtres de l'import)
    -- ------------------------------------------------------------------------------------------
    CREATE TEMPORARY TABLE rsf_pce_cd (code VARCHAR(6) PRIMARY KEY, cr VARCHAR(6)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT Numero_comite_dept AS code, MIN(Numero_comite_reg) AS cr
        FROM kp_licence
        WHERE Origine = v_saison AND Numero_club NOT IN ('0', '0000') AND Numero_comite_reg <> '98'
          AND Numero_comite_dept IS NOT NULL
        GROUP BY Numero_comite_dept;

    CREATE TEMPORARY TABLE rsf_pce_club (code VARCHAR(6) PRIMARY KEY, cd VARCHAR(6)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT Numero_club AS code, MIN(Numero_comite_dept) AS cd
        FROM kp_licence
        WHERE Origine = v_saison AND Numero_club NOT IN ('0', '0000') AND Numero_comite_reg <> '98'
        GROUP BY Numero_club;

    CREATE TEMPORARY TABLE rsf_licence_club (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT DISTINCT Numero_club AS code FROM kp_licence WHERE Numero_club IS NOT NULL;

    CREATE TEMPORARY TABLE rsf_licence_cd (code VARCHAR(6) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT DISTINCT Numero_comite_dept AS code FROM kp_licence WHERE Numero_comite_dept IS NOT NULL;

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
    UPDATE kp_equipe e JOIN rsf_ancien_club a ON a.code = e.Code_club
    SET e.logo = IF(e.logo = CONCAT('KIP/logo/', a.code, '-logo.png'), CONCAT('KIP/logo/CR', a.actuel, '-logo.png'), e.logo),
        e.Code_club = CONCAT('CR', a.actuel);

    UPDATE kp_competition_equipe e JOIN rsf_ancien_club a ON a.code = e.Code_club
    SET e.logo = IF(e.logo = CONCAT('KIP/logo/', a.code, '-logo.png'), CONCAT('KIP/logo/CR', a.actuel, '-logo.png'), e.logo),
        e.Code_club = CONCAT('CR', a.actuel);

    -- Limitations d'équipes (liste de codes séparés par des virgules), réécrites sans doublon
    UPDATE kp_user u
    JOIN (
        SELECT x.Code, GROUP_CONCAT(DISTINCT COALESCE(CONCAT('CR', a.actuel), TRIM(t.code)) SEPARATOR ',') AS lim
        FROM kp_user x
        JOIN JSON_TABLE(CONCAT('["', REPLACE(x.Limitation_equipe_club, ',', '","'), '"]'), '$[*]'
                        COLUMNS (code VARCHAR(50) PATH '$')) t
        LEFT JOIN rsf_ancien_club a ON a.code = TRIM(t.code)
        WHERE EXISTS (SELECT 1 FROM rsf_ancien_club b WHERE FIND_IN_SET(b.code, REPLACE(x.Limitation_equipe_club, ' ', '')))
        GROUP BY x.Code
    ) n ON n.Code = u.Code
    SET u.Limitation_equipe_club = n.lim;

    UPDATE kp_user_mandat u
    JOIN (
        SELECT x.id, GROUP_CONCAT(DISTINCT COALESCE(CONCAT('CR', a.actuel), TRIM(t.code)) SEPARATOR ',') AS lim
        FROM kp_user_mandat x
        JOIN JSON_TABLE(CONCAT('["', REPLACE(x.limitation_equipe_club, ',', '","'), '"]'), '$[*]'
                        COLUMNS (code VARCHAR(50) PATH '$')) t
        LEFT JOIN rsf_ancien_club a ON a.code = TRIM(t.code)
        WHERE EXISTS (SELECT 1 FROM rsf_ancien_club b WHERE FIND_IN_SET(b.code, REPLACE(x.limitation_equipe_club, ' ', '')))
        GROUP BY x.id
    ) n ON n.id = u.id
    SET u.limitation_equipe_club = n.lim;

    -- Pseudo-clubs puis pseudo-CD d'Anciennes ligues (sauf ceux devenus Sélection), et Clubs non
    -- référencés encore rattachés à un pseudo-CD d'Ancienne ligue (garde-fou : aucun n'est référencé)
    DELETE FROM kp_club
    WHERE Code NOT IN (SELECT code FROM rsf_selection)
      AND (Code IN (SELECT code FROM rsf_ancien_club)
           OR Code_comite_dep IN (SELECT code FROM rsf_ancien_cd WHERE code NOT IN (SELECT code FROM rsf_selection)));

    DELETE FROM kp_cd
    WHERE Code IN (SELECT code FROM rsf_ancien_cd) AND Code NOT IN (SELECT code FROM rsf_selection);

    -- ------------------------------------------------------------------------------------------
    -- 3. Clubs puis CD hors PCE non référencés (structures 98 et Sélections conservées)
    -- ------------------------------------------------------------------------------------------
    CREATE TEMPORARY TABLE rsf_limitation (code VARCHAR(50) PRIMARY KEY) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci AS
        SELECT DISTINCT TRIM(t.code) AS code
        FROM (
            SELECT Limitation_equipe_club AS lim FROM kp_user WHERE Limitation_equipe_club <> ''
            UNION ALL
            SELECT limitation_equipe_club FROM kp_user_mandat WHERE limitation_equipe_club <> ''
        ) l,
        JSON_TABLE(CONCAT('["', REPLACE(l.lim, ',', '","'), '"]'), '$[*]' COLUMNS (code VARCHAR(50) PATH '$')) t
        WHERE TRIM(t.code) <> '';

    DELETE c FROM kp_club c JOIN kp_cd d ON d.Code = c.Code_comite_dep
    WHERE d.Code_comite_reg <> '98'
      AND c.Code NOT IN (SELECT code FROM rsf_selection)
      AND c.Code NOT IN (SELECT code FROM rsf_pce_club)
      AND c.Code NOT IN (SELECT code FROM rsf_licence_club)
      AND c.Code NOT IN (SELECT code FROM rsf_limitation)
      AND c.Code NOT IN (SELECT Code_club FROM kp_equipe WHERE Code_club IS NOT NULL)
      AND c.Code NOT IN (SELECT Code_club FROM kp_competition_equipe WHERE Code_club IS NOT NULL);

    DELETE FROM kp_cd
    WHERE Code_comite_reg <> '98'
      AND Code NOT IN (SELECT code FROM rsf_pce_cd)
      AND Code NOT IN (SELECT Code_comite_dep FROM kp_club)
      AND Code NOT IN (SELECT code FROM rsf_licence_cd)
      AND Code NOT IN (SELECT code FROM rsf_limitation);

    -- ------------------------------------------------------------------------------------------
    -- 4. Codes des Compétitions régionales (toutes saisons), une seule fois
    -- ------------------------------------------------------------------------------------------
    IF v_deja_renomme = 0 THEN
        SET FOREIGN_KEY_CHECKS = 0;

        -- Clés uniques (compétitions, groupes) : deux passes par un code temporaire « ~<nouveau> »,
        -- pour que REG14 → REG07 ne heurte pas l'ancien REG07 encore en place
        UPDATE kp_competition c JOIN rsf_code m ON m.ancien = c.Code SET c.Code = CONCAT('~', m.nouveau);
        UPDATE kp_competition SET Code = SUBSTRING(Code, 2) WHERE Code LIKE '~%';
        UPDATE kp_groupe g JOIN rsf_code m ON m.ancien = g.Groupe SET g.Groupe = CONCAT('~', m.nouveau);
        UPDATE kp_groupe SET Groupe = SUBSTRING(Groupe, 2) WHERE Groupe LIKE '~%';

        -- Colonnes sans contrainte d'unicité : une passe (chaque ligne n'est lue qu'une fois)
        UPDATE kp_competition c JOIN rsf_code m ON m.ancien = c.Code_ref SET c.Code_ref = m.nouveau;
        UPDATE kp_competition_equipe e JOIN rsf_code m ON m.ancien = e.Code_compet SET e.Code_compet = m.nouveau;
        UPDATE kp_journee j JOIN rsf_code m ON m.ancien = j.Code_competition SET j.Code_competition = m.nouveau;
        UPDATE kp_rc r JOIN rsf_code m ON m.ancien = r.Code_competition SET r.Code_competition = m.nouveau;
        UPDATE kp_form f JOIN rsf_code m ON m.ancien = f.competition_code SET f.competition_code = m.nouveau;

        -- Codes inclus dans des listes (filtres, multi-compétitions) : passe 1 vers un jeton
        -- temporaire unique « ~<id>~ », passe 2 vers le nouveau code
        SET v_nb_codes = (SELECT COUNT(*) FROM rsf_code);
        SET v_i = 0;
        WHILE v_i < v_nb_codes DO
            SELECT ancien, CONCAT('~', id, '~') INTO v_ancien, v_temp FROM rsf_code ORDER BY id LIMIT v_i, 1;
            UPDATE kp_user
            SET Filtre_competition = REPLACE(Filtre_competition, CONCAT('|', v_ancien, '|'), CONCAT('|', v_temp, '|')),
                Filtre_competition_sql = REPLACE(Filtre_competition_sql, CONCAT('''', v_ancien, ''''), CONCAT('''', v_temp, ''''))
            WHERE Filtre_competition LIKE CONCAT('%|', v_ancien, '|%')
               OR Filtre_competition_sql LIKE CONCAT('%''', v_ancien, '''%');
            UPDATE kp_user_mandat
            SET filtre_competition = REPLACE(filtre_competition, CONCAT('|', v_ancien, '|'), CONCAT('|', v_temp, '|'))
            WHERE filtre_competition LIKE CONCAT('%|', v_ancien, '|%');
            UPDATE kp_competition
            SET multi_competitions = REPLACE(multi_competitions, CONCAT('"', v_ancien, '"'), CONCAT('"', v_temp, '"'))
            WHERE multi_competitions LIKE CONCAT('%"', v_ancien, '"%');
            SET v_i = v_i + 1;
        END WHILE;

        SET v_i = 0;
        WHILE v_i < v_nb_codes DO
            SELECT nouveau, CONCAT('~', id, '~') INTO v_nouveau, v_temp FROM rsf_code ORDER BY id LIMIT v_i, 1;
            UPDATE kp_user
            SET Filtre_competition = REPLACE(Filtre_competition, v_temp, v_nouveau),
                Filtre_competition_sql = REPLACE(Filtre_competition_sql, v_temp, v_nouveau)
            WHERE Filtre_competition LIKE CONCAT('%', v_temp, '%') OR Filtre_competition_sql LIKE CONCAT('%', v_temp, '%');
            UPDATE kp_user_mandat SET filtre_competition = REPLACE(filtre_competition, v_temp, v_nouveau)
            WHERE filtre_competition LIKE CONCAT('%', v_temp, '%');
            UPDATE kp_competition SET multi_competitions = REPLACE(multi_competitions, v_temp, v_nouveau)
            WHERE multi_competitions LIKE CONCAT('%', v_temp, '%');
            SET v_i = v_i + 1;
        END WHILE;

        SET FOREIGN_KEY_CHECKS = 1;

        -- Repère d'exécution (rend le renommage non rejouable)
        INSERT INTO kp_journal (Dates, Users, Actions, Journal)
        VALUES (NOW(), 'SCRIPT', 'Remise à plat structures FFCK',
                CONCAT('SQL/migrations/2026-10-29_realignement_structures_ffck.sql : ', v_nb_codes, ' codes régionaux renommés'));
    END IF;

    COMMIT;

    SELECT 'Remise à plat terminée' AS resultat, v_saison AS saison_pce,
           (SELECT COUNT(*) FROM rsf_selection) AS selections_regionales,
           (SELECT COUNT(*) FROM rsf_code) AS codes_renommes;
END//

DELIMITER ;
