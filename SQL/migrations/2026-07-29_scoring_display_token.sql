-- Migration: scoring_display_token — jetons d'accès des afficheurs (incrustations)
-- Date: 2026-07-29
-- Docs: DOC/specs/PAGE_INCRUSTATION.md §11bis · plan §4.4 (jeton machine, même principe)
--
-- Pourquoi. La page d'incrustation tourne SANS opérateur dans un mélangeur vidéo : elle ne
-- peut pas porter de JWT utilisateur. Mais « public » ne doit pas vouloir dire « ouvert à
-- tout le monde, pour toujours » :
--   * le jeton est porté par l'URL configurée une fois dans OBS (coût d'exploitation nul) ;
--   * il est limité à UN événement (et éventuellement à UN terrain) ;
--   * il EXPIRE (fin de l'événement) et il est RÉVOCABLE immédiatement ;
--   * il autorise la lecture HTTP **et** sert à fabriquer le JWT d'abonné Mercure — sans
--     quoi l'incrustation ne peut pas s'abonner en preprod/prod (MERCURE_ANONYMOUS=0).

CREATE TABLE IF NOT EXISTS `scoring_display_token` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  -- Secret porté par l'URL de l'incrustation (?token=…)
  `token` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_event` int(10) UNSIGNED NOT NULL,
  -- NULL = tous les terrains de l'événement ; renseigné = un seul terrain
  `pitch` varchar(30) DEFAULT NULL,
  -- Libellé d'exploitation (« régie terrain 2 », « écran hall »…)
  `label` varchar(100) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scoring_display_token` (`token`),
  KEY `idx_scoring_display_token_event` (`id_event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vérification
SELECT COUNT(*) AS rows_scoring_display_token FROM scoring_display_token;
