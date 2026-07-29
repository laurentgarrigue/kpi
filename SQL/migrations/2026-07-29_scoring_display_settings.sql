-- Migration: scoring_display_settings — réglages d'enchaînement de l'incrustation
-- Date: 2026-07-29
-- Docs: DOC/specs/PAGE_INCRUSTATION.md §7 · plan lot 4 (étape 4.4)
--
-- Principe (décision 2026-07-29) : les délais et options d'affichage ont des VALEURS PAR
-- DÉFAUT côté serveur, surchargeables **par événement**, puis **par terrain**.
-- Résolution : défauts → événement (id_pitch NULL) → terrain (id_pitch renseigné),
-- le plus spécifique gagne. Une ligne ne porte que les clés réellement surchargées :
-- une valeur NULL signifie « hériter », jamais « zéro ».

CREATE TABLE IF NOT EXISTS `scoring_display_settings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_event` int(10) UNSIGNED NOT NULL,
  -- NULL = réglage de l'ÉVÉNEMENT (tous les terrains) ; renseigné = surcharge du terrain.
  -- kp_match.Terrain est un varchar : on garde le même type pour éviter toute conversion.
  `pitch` varchar(30) DEFAULT NULL,

  -- Délais d'enchaînement, en secondes (NULL = hériter du niveau au-dessus)
  `halftime_score_delay` int(11) DEFAULT NULL,
  `final_score_delay` int(11) DEFAULT NULL,
  `final_score_duration` int(11) DEFAULT NULL,
  `next_game_delay` int(11) DEFAULT NULL,
  `next_game_duration` int(11) DEFAULT NULL,

  -- Habillage (NULL = hériter) : fond `transparent` (alpha OBS), `magenta`/couleur CSS
  -- pour du chromakey, et style visuel de l'événement.
  `background` varchar(30) DEFAULT NULL,
  `style_id` varchar(50) DEFAULT NULL,

  `updated_at` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3),
  PRIMARY KEY (`id`),
  -- Une seule ligne par (événement, terrain) — et une seule pour l'événement lui-même.
  UNIQUE KEY `uq_scoring_display` (`id_event`,`pitch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vérification
SELECT COUNT(*) AS rows_scoring_display_settings FROM scoring_display_settings;
