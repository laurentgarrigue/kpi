-- Migration: scoring_live_* — canonical live match state (refonte scoring, lot 1)
-- Date: 2026-07-27
-- Docs: DOC/developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md (lot 1, §4.13)
--       DOC/specs/PAGE_SCORING.md §0.2 / §0.5
--
-- Principe : ces tables portent l'état PENDANT le match ; kp_* reste la vérité des
-- résultats et n'est écrit qu'à la consolidation de fin de match (plan §4.3).
-- Elles sont NOUVELLES : aucun impact sur le legacy, qui les ignore.

-- ---------------------------------------------------------------------------
-- 1. scoring_live_state — un enregistrement par match (spec §0.5)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scoring_live_state` (
  `id_match` int(10) UNSIGNED NOT NULL,
  `score_a` int(11) NOT NULL DEFAULT 0,
  `score_b` int(11) NOT NULL DEFAULT 0,
  -- varchar, pas un enum figé : accepte M1/M2/P1..P{n}/TB (prolongations non bornées, spec §0.6)
  `periode` varchar(4) NOT NULL DEFAULT 'M1',
  `statut` enum('ATT','ON','END') NOT NULL DEFAULT 'ATT',
  `heure_fin` time DEFAULT NULL,
  -- source autorisée à écrire (plan §4.1) ; les commandes d'une autre source sont rejetées
  `active_source` enum('MANUAL','HARDWARE','SCORE_ONLY','IMPORT') NOT NULL DEFAULT 'MANUAL',
  -- horodatage de la dernière promotion de source (plan §4.1 : les commandes antérieures sont rejetées)
  `promoted_at` datetime(3) DEFAULT NULL,
  -- numéro de version, incrémenté à chaque écriture (diffusion/resync/ETag)
  `tick` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3),
  PRIMARY KEY (`id_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 2. scoring_live_clock — N horloges par match (spec §0.5, modèle 4 valeurs plan §3.1)
--    GAME / SHOTCLOCK / BREAK : team='' et slot=0 (une seule par match, via l'unicité)
--    PENALTY : team A|B, slot 1|2 (au plus 2 exclusions concurrentes par équipe, §7.4)
--    PK UUID générée par l'émetteur (console/relais) : idempotence + création hors ligne (§4.13)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scoring_live_clock` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `id_match` int(10) UNSIGNED NOT NULL,
  `kind` enum('GAME','SHOTCLOCK','PENALTY','BREAK') NOT NULL,
  `team` char(1) NOT NULL DEFAULT '',
  `slot` tinyint(4) NOT NULL DEFAULT 0,
  `id_player` varchar(25) DEFAULT NULL,
  -- carton d'origine de la pénalité (V/J/R/D) — affichage + statut joueur (spec §7.4)
  `card_code` char(1) DEFAULT NULL,
  -- modèle 4 valeurs : durée de départ, temps écoulé au dernier arrêt,
  -- heure client du dernier départ (plan §4.2), en marche ou non
  `init_ms` int(11) NOT NULL,
  `elapsed_ms` int(11) NOT NULL DEFAULT 0,
  `started_at` datetime(3) DEFAULT NULL,
  `running` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scoring_clock` (`id_match`,`kind`,`team`,`slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 3. scoring_live_event — les faits de match (spec §0.4/§0.5)
--    kind en varchar : extensible (GOAL/CARD aujourd'hui ; SHOT/PASS/SAVE… demain)
--    uid généré client (aligne optimiste/serveur/édition — même format que kp_match_detail.Id)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scoring_live_event` (
  `uid` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `id_match` int(10) UNSIGNED NOT NULL,
  `kind` varchar(10) NOT NULL,
  -- code legacy : B (but) / V/J/R/D (cartons) — compat kp_match_detail.Id_evt_match
  `code` varchar(2) DEFAULT NULL,
  `periode` varchar(4) DEFAULT NULL,
  `temps` time DEFAULT NULL,
  `team` char(1) NOT NULL,
  `id_player` varchar(25) DEFAULT NULL,
  `numero` varchar(4) DEFAULT NULL,
  `motif` varchar(10) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  PRIMARY KEY (`uid`),
  KEY `idx_scoring_event_match` (`id_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 4. scoring_outbox — file de diffusion transactionnelle (plan lot 2, spec §0.3)
--    Chaque écriture d'état dépose ici, dans la même transaction, le message à
--    publier sur Mercure ; le worker draine (published_at NULL = à publier).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scoring_outbox` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_match` int(10) UNSIGNED NOT NULL,
  -- URI Mercure de destination événement/terrain/bloc (plan §3.3),
  -- ex. /scoring/event/236/pitch/2/score
  `topic` varchar(190) NOT NULL,
  `payload` longtext NOT NULL CHECK (json_valid(`payload`)),
  `tick` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `published_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_scoring_outbox_unpublished` (`published_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 5. kp_match.uid — identifiant public court ADDITIF (décision §4.13)
--    Le legacy l'ignore (nullable, jamais requis) ; les usages futurs (offline,
--    import, adressage public) passent par lui. Id int inchangé partout.
-- ---------------------------------------------------------------------------
ALTER TABLE `kp_match`
  ADD COLUMN IF NOT EXISTS `uid` varchar(12) DEFAULT NULL AFTER `Id`,
  ADD UNIQUE KEY IF NOT EXISTS `uq_kp_match_uid` (`uid`);

-- Vérification
SELECT 'scoring_live_state' t, COUNT(*) n FROM scoring_live_state
UNION ALL SELECT 'scoring_live_clock', COUNT(*) FROM scoring_live_clock
UNION ALL SELECT 'scoring_live_event', COUNT(*) FROM scoring_live_event
UNION ALL SELECT 'scoring_outbox', COUNT(*) FROM scoring_outbox;
