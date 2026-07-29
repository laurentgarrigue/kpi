-- Migration: vues de compatibilité live ↔ legacy
-- Date: 2026-07-29
-- Docs: DOC/specs/PAGE_SCORING.md §0.2 (encadré transition) · plan lot 4, étape 4.5
--
-- LE PROBLÈME. Depuis le re-routage (lot 1), l'état d'un match EN COURS vit dans
-- scoring_live_* ; kp_match / kp_match_detail ne sont écrits qu'à la consolidation
-- (Statut → END). Or plusieurs lecteurs legacy affichent le déroulement PENDANT le match :
--   * sources/admin/FeuilleMatchMulti.php  (PDF de contrôle imprimé en cours de match)
--   * sources/api/controllers/publicControllers.php + reportControllers.php (consommés
--     par app2 et les fiches publiques)
-- Sans rien faire, ces lecteurs n'afficheraient plus rien avant la fin du match.
--
-- LA SOLUTION RETENUE. Deux vues qui présentent l'état live **dans la forme legacy**, avec
-- repli automatique sur kp_* :
--   * un match qui a un état live → on sert le live ;
--   * un match qui n'en a pas (jamais touché par la console, ou déjà consolidé) → on sert
--     kp_* exactement comme avant.
-- Les lecteurs ne changent qu'UN identifiant dans leur FROM. Aucune double écriture n'est
-- introduite (le plan §4.3 l'interdit explicitement), aucune logique n'est dupliquée.
--
-- CYCLE DE VIE. Ces vues sont un échafaudage de transition : quand les lecteurs legacy
-- auront disparu (page d'incrustation unique, app4), elles se suppriment sans rien casser.

-- ---------------------------------------------------------------------------
-- 1. v_match_detail — faits de match, forme kp_match_detail
--    Colonnes identiques à kp_match_detail pour que le FROM soit la seule différence.
-- ---------------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_match_detail` AS
SELECT
    e.uid          AS `Id`,
    e.id_match     AS `Id_match`,
    e.periode      AS `Periode`,
    e.temps        AS `Temps`,
    e.code         AS `Id_evt_match`,
    e.motif        AS `motif`,
    e.id_player    AS `Competiteur`,
    e.numero       AS `Numero`,
    e.team         AS `Equipe_A_B`,
    e.created_at   AS `date_insert`
FROM scoring_live_event e
UNION ALL
SELECT
    md.`Id`, md.`Id_match`, md.`Periode`, md.`Temps`, md.`Id_evt_match`,
    md.`motif`, md.`Competiteur`, md.`Numero`, md.`Equipe_A_B`, md.`date_insert`
FROM kp_match_detail md
-- Repli : uniquement pour les matchs SANS état live (jamais dupliqué).
WHERE NOT EXISTS (
    SELECT 1 FROM scoring_live_event e2 WHERE e2.id_match = md.`Id_match`
);

-- ---------------------------------------------------------------------------
-- 2. v_match_live — entête de match, forme kp_match, avec l'état live prioritaire
--    Toutes les colonnes de kp_match sont conservées ; seules Statut, Periode,
--    ScoreDetailA/B et Heure_fin sont surchargées quand un état live existe.
-- ---------------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_match_live` AS
SELECT
    m.`Id`, m.`Id_journee`, m.`Libelle`, m.`Type`,
    COALESCE(s.statut, m.`Statut`)                AS `Statut`,
    m.`Date_match`, m.`Heure_match`,
    COALESCE(s.heure_fin, m.`Heure_fin`)          AS `Heure_fin`,
    m.`Terrain`, m.`Numero_ordre`,
    COALESCE(s.periode, m.`Periode`)              AS `Periode`,
    m.`Id_equipeA`, m.`Id_equipeB`, m.`ColorA`, m.`ColorB`,
    m.`ScoreA`, m.`ScoreB`,
    COALESCE(s.score_a, m.`ScoreDetailA`)         AS `ScoreDetailA`,
    COALESCE(s.score_b, m.`ScoreDetailB`)         AS `ScoreDetailB`,
    m.`CoeffA`, m.`CoeffB`, m.`Commentaires_officiels`, m.`Commentaires`,
    m.`Arbitre_principal`, m.`Arbitre_secondaire`,
    m.`Matric_arbitre_principal`, m.`Matric_arbitre_secondaire`,
    m.`Secretaire`, m.`Chronometre`, m.`Timeshoot`, m.`Ligne1`, m.`Ligne2`,
    m.`Publication`, m.`Code_uti`, m.`Validation`, m.`Imprime`
FROM kp_match m
LEFT JOIN scoring_live_state s ON s.id_match = m.`Id`;

-- Vérification : les deux vues doivent répondre.
SELECT 'v_match_detail' AS view_name, COUNT(*) AS rows_total FROM v_match_detail
UNION ALL
SELECT 'v_match_live', COUNT(*) FROM v_match_live;
