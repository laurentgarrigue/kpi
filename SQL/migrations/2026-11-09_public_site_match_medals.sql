-- Migration : médailles de match et statistiques de timeline
-- Date : 2026-11-09
-- Spec : DOC/specs/public/FEATURE_MATCH_MEDALS.md § 6.2
--
-- Idempotente (CREATE TABLE IF NOT EXISTS, INSERT IGNORE) : peut être rejouée sans effet ; elle ne modifie jamais
-- un élément du catalogue déjà présent (noms, explications et surfaces se modifient ensuite dans l'admin).
-- Application : docker exec -i <db> mariadb -uroot -p<pass> <base> < SQL/migrations/2026-11-09_public_site_match_medals.sql
-- Aussi chargée par les fixtures de test d'api2 (CI et `make api2_test_fixtures`), entre schema.sql et data.sql.
-- Puis : make api2_match_medals_compute season=<saison>, saison par saison (rollout checklist).

SET NAMES utf8mb4;

-- Catalogue : médailles, étiquettes et statistiques, avec leurs noms, explications et surfaces de publication.
-- Les explications citent les seuils par variable ({bounceBackSeconds}), remplacées par les seuils de la saison.
CREATE TABLE IF NOT EXISTS `kp_medal_catalog` (
  `code` varchar(40) NOT NULL,
  `type` enum('medal','label','stat') NOT NULL,
  `scopes` varchar(3) NOT NULL DEFAULT '' COMMENT 'T équipe, P joueur, R arbitre',
  `family` varchar(20) NOT NULL,
  `data_level` char(1) NOT NULL COMMENT 'S score, T timeline fiable',
  `name_fr` varchar(80) NOT NULL,
  `name_en` varchar(80) NOT NULL,
  `explanation_fr` varchar(500) NOT NULL,
  `explanation_en` varchar(500) NOT NULL,
  `status` enum('active','retired') NOT NULL DEFAULT 'active',
  `published_app2` tinyint(1) NOT NULL DEFAULT 0,
  `published_app3` tinyint(1) NOT NULL DEFAULT 0,
  `icon` varchar(40) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(8) DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seuils figés de chaque saison calculée (§ 6.3).
CREATE TABLE IF NOT EXISTS `kp_medal_season` (
  `season` char(4) NOT NULL,
  `thresholds` text NOT NULL COMMENT 'JSON',
  `rules_version` int(11) NOT NULL,
  `computed_at` datetime NOT NULL,
  `games_count` int(11) NOT NULL DEFAULT 0,
  `reliable_games_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une ligne par match validé calculé : faits dérivés de la timeline et suivi du recalcul.
CREATE TABLE IF NOT EXISTS `kp_match_insight` (
  `match_id` int(10) unsigned NOT NULL,
  `season` char(4) NOT NULL,
  `is_eligible` tinyint(1) NOT NULL,
  `timeline_reliable` tinyint(1) NOT NULL,
  `labels` varchar(255) NOT NULL DEFAULT '[]' COMMENT 'JSON',
  `winner_side` char(1) DEFAULT NULL,
  `overtimes` tinyint(4) NOT NULL DEFAULT 0,
  `lead_changes` tinyint(4) NOT NULL DEFAULT 0,
  `equalizers` tinyint(4) NOT NULL DEFAULT 0,
  `first_goal_side` char(1) DEFAULT NULL,
  `first_goal_seconds` int(11) DEFAULT NULL,
  `max_deficit_a` tinyint(4) NOT NULL DEFAULT 0,
  `max_deficit_b` tinyint(4) NOT NULL DEFAULT 0,
  `exclusions_a` tinyint(4) NOT NULL DEFAULT 0,
  `exclusions_b` tinyint(4) NOT NULL DEFAULT 0,
  `powerplay_goals_a` tinyint(4) NOT NULL DEFAULT 0,
  `powerplay_goals_b` tinyint(4) NOT NULL DEFAULT 0,
  `penalty_killed_a` tinyint(4) NOT NULL DEFAULT 0,
  `penalty_killed_b` tinyint(4) NOT NULL DEFAULT 0,
  `goal_seconds` text NOT NULL COMMENT 'JSON [{side, seconds}] des buts, timeline fiable seulement',
  `source_hash` char(32) NOT NULL,
  `computed_at` datetime NOT NULL,
  PRIMARY KEY (`match_id`),
  KEY `idx_insight_season` (`season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Médailles attribuées (équipe, joueur, arbitre).
CREATE TABLE IF NOT EXISTS `kp_match_medal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(10) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `scope` enum('team','player','referee') NOT NULL,
  `team_side` char(1) DEFAULT NULL,
  `team_id` int(10) unsigned DEFAULT NULL,
  `person_matric` int(11) unsigned DEFAULT NULL,
  `event_detail_id` varchar(32) DEFAULT NULL,
  `game_seconds` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_medal_match` (`match_id`),
  KEY `idx_medal_team` (`team_id`, `code`),
  KEY `idx_medal_person` (`person_matric`, `code`),
  KEY `idx_medal_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cartons des matchs calculés, placés sur l'axe de temps (statistiques de discipline et d'arbitrage).
CREATE TABLE IF NOT EXISTS `kp_match_card` (
  `match_id` int(10) unsigned NOT NULL,
  `event_detail_id` varchar(32) NOT NULL,
  `team_side` char(1) NOT NULL,
  `code` varchar(2) NOT NULL,
  `motif` varchar(10) DEFAULT NULL,
  `period` varchar(3) DEFAULT NULL,
  `game_seconds` int(11) DEFAULT NULL,
  PRIMARY KEY (`match_id`, `event_detail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arbitres désignés nommément des matchs calculés (copie des désignations, entre dans source_hash).
CREATE TABLE IF NOT EXISTS `kp_match_referee` (
  `match_id` int(10) unsigned NOT NULL,
  `referee_matric` int(11) unsigned NOT NULL,
  `role` enum('principal','secondaire') NOT NULL,
  PRIMARY KEY (`match_id`, `referee_matric`),
  KEY `idx_referee_matric` (`referee_matric`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogue initial (§ 3, § 4). Premier lot publié : voir published_app2 / published_app3.
INSERT IGNORE INTO `kp_medal_catalog`
  (`code`, `type`, `scopes`, `family`, `data_level`, `name_fr`, `name_en`, `explanation_fr`, `explanation_en`, `published_app2`, `published_app3`, `icon`, `sort_order`) VALUES
('opener', 'medal', 'TP', 'elan', 'T', 'Premier but', 'Opener', 'L’équipe et le joueur qui ouvrent le score.', 'The team and the player who open the score.', 1, 1, 'opener', 10),
('wire_to_wire', 'medal', 'T', 'elan', 'T', 'De bout en bout', 'Wire to Wire', 'Ouvre le score, n’est jamais rejointe, et gagne.', 'Opens the score, is never caught up, and wins.', 0, 0, 'wire_to_wire', 20),
('scoring_run', 'medal', 'T', 'elan', 'T', 'Série', 'Scoring Run', 'Marque au moins {scoringRunGoals} buts consécutifs sans en encaisser.', 'Scores at least {scoringRunGoals} goals in a row without conceding.', 0, 0, 'scoring_run', 30),
('fast_start', 'medal', 'P', 'elan', 'T', 'Entrée express', 'Fast Start', 'Marque dans les {fastStartSeconds} premières secondes d’une mi-temps.', 'Scores in the first {fastStartSeconds} seconds of a half.', 0, 0, 'fast_start', 40),
('bounce_back', 'medal', 'TP', 'reaction', 'T', 'Réplique', 'Bounce Back', 'Marque moins de {bounceBackSeconds} secondes après avoir encaissé un but.', 'Scores less than {bounceBackSeconds} seconds after conceding a goal.', 0, 0, 'bounce_back', 50),
('comeback', 'medal', 'T', 'reaction', 'T', 'Remontée', 'Comeback', 'Menée d’au moins {comebackDeficit} buts, l’équipe finit par gagner.', 'Trailing by {comebackDeficit} goals or more, the team ends up winning.', 1, 1, 'comeback', 60),
('fightback', 'medal', 'T', 'reaction', 'T', 'Retour au score', 'Fightback', 'Menée d’au moins {comebackDeficit} buts, l’équipe obtient le match nul.', 'Trailing by {comebackDeficit} goals or more, the team gets a draw.', 0, 0, 'fightback', 70),
('game_winner', 'medal', 'P', 'reaction', 'T', 'But de la victoire', 'Game Winner', 'Le but qui donne l’avantage définitif : le vainqueur ne sera plus rejoint.', 'The goal that gives the final lead: the winner is never caught up again.', 1, 0, 'game_winner', 80),
('late_winner', 'medal', 'TP', 'reaction', 'T', 'Dernières secondes', 'Late Winner', 'But de la victoire marqué dans les {lateWindowSeconds} dernières secondes du temps réglementaire.', 'Game-winning goal scored in the last {lateWindowSeconds} seconds of regulation time.', 0, 0, 'late_winner', 90),
('lifeline', 'medal', 'TP', 'reaction', 'T', 'Égalisation salvatrice', 'Lifeline', 'Égalise dans les {lateWindowSeconds} dernières secondes et arrache la prolongation.', 'Equalises in the last {lateWindowSeconds} seconds and forces overtime.', 0, 0, 'lifeline', 100),
('extra_time', 'medal', 'T', 'endurance', 'S', 'Prolongation', 'Extra Time', 'Le match va en prolongation.', 'The game goes to overtime.', 0, 0, 'extra_time', 110),
('marathon', 'medal', 'T', 'endurance', 'S', 'Marathon', 'Marathon', 'Le match atteint au moins {marathonOvertimes} prolongations.', 'The game reaches at least {marathonOvertimes} overtimes.', 0, 0, 'marathon', 120),
('golden_goal', 'medal', 'TP', 'endurance', 'S', 'But en or', 'Golden Goal', 'Gagne en prolongation : le premier but de la prolongation termine le match.', 'Wins in overtime: the first goal of overtime ends the game.', 1, 1, 'golden_goal', 130),
('powerplay', 'medal', 'TP', 'superiorite', 'T', 'Supériorité', 'Powerplay', 'Marque pendant qu’un adversaire est exclu (supériorité numérique).', 'Scores while an opponent is excluded (power play).', 1, 1, 'powerplay', 140),
('shorthanded', 'medal', 'TP', 'superiorite', 'T', 'Infériorité', 'Shorthanded', 'Marque pendant qu’un coéquipier est exclu (infériorité numérique).', 'Scores while a teammate is excluded (shorthanded).', 0, 0, 'shorthanded', 150),
('penalty_kill', 'medal', 'T', 'superiorite', 'T', 'Résistance', 'Penalty Kill', 'Traverse au moins {penaltyKillExclusions} exclusions sans encaisser de but pendant celles-ci.', 'Goes through at least {penaltyKillExclusions} exclusions without conceding during them.', 0, 0, 'penalty_kill', 160),
('hat_trick', 'medal', 'P', 'finition', 'S', 'Triplé', 'Hat-trick', 'Marque au moins {hatTrickGoals} buts dans le match.', 'Scores at least {hatTrickGoals} goals in the game.', 1, 1, 'hat_trick', 170),
('natural_hat_trick', 'medal', 'P', 'finition', 'T', 'Triplé consécutif', 'Natural Hat-trick', 'Marque {hatTrickGoals} buts consécutifs du match, sans autre but entre eux.', 'Scores {hatTrickGoals} goals in a row of the game, with no other goal between them.', 0, 0, 'natural_hat_trick', 180),
('clean_sheet', 'medal', 'T', 'defense', 'S', 'Cage inviolée', 'Clean Sheet', 'N’encaisse aucun but.', 'Concedes no goal.', 1, 1, 'clean_sheet', 190),
('big_win', 'medal', 'T', 'defense', 'S', 'Large victoire', 'Big Win', 'Gagne avec au moins {bigWinMargin} buts d’écart.', 'Wins by {bigWinMargin} goals or more.', 0, 0, 'big_win', 200),
('fair_play', 'medal', 'T', 'esprit', 'S', 'Fair-play', 'Fair Play', 'Ne reçoit aucun carton dans le match.', 'Gets no card in the game.', 0, 1, 'fair_play', 210),
('thriller', 'label', '', 'match', 'T', 'Suspense', 'Thriller', 'Au moins {thrillerLeadChanges} changements d’équipe en tête au cours du match.', 'At least {thrillerLeadChanges} changes of leader during the game.', 1, 1, 'thriller', 300),
('seesaw', 'label', '', 'match', 'T', 'Chassé-croisé', 'Seesaw', 'Au moins {seesawEqualizers} égalisations.', 'At least {seesawEqualizers} equalisers.', 0, 0, 'seesaw', 310),
('goal_fest', 'label', '', 'match', 'S', 'Festival', 'Goal Fest', 'Total de buts parmi les {goalFestTopPercent} % les plus élevés de la compétition.', 'Total goals among the top {goalFestTopPercent} % of the competition.', 0, 0, 'goal_fest', 320),
('chess_match', 'label', '', 'match', 'S', 'Partie d’échecs', 'Chess Match', 'Au plus {chessMatchMaxGoals} buts : un match tactique, où chaque but se joue comme un coup.', 'At most {chessMatchMaxGoals} goals: a tactical game, where every goal is played like a move.', 0, 0, 'chess_match', 330),
('ref_smooth_game', 'medal', 'R', 'arbitrage', 'S', 'Match fluide', 'Smooth Game', 'Arbitre un match sans aucun carton.', 'Referees a game without any card.', 1, 1, 'ref_smooth_game', 400),
('ref_knockout', 'medal', 'R', 'arbitrage', 'S', 'Match couperet', 'Knockout', 'Arbitre un match à élimination directe.', 'Referees a knockout game.', 1, 1, 'ref_knockout', 410),
('ref_extra_time', 'medal', 'R', 'arbitrage', 'S', 'Prolongation', 'Extra Time', 'Arbitre un match qui va en prolongation.', 'Referees a game that goes to overtime.', 1, 1, 'ref_extra_time', 420),
('ref_thriller', 'medal', 'R', 'arbitrage', 'T', 'Suspense', 'Thriller', 'Arbitre un match étiqueté Suspense.', 'Referees a game labelled Thriller.', 1, 1, 'ref_thriller', 430),
('ref_golden_goal', 'medal', 'R', 'arbitrage', 'S', 'But en or', 'Golden Goal', 'Arbitre un match décidé par un but en or.', 'Referees a game decided by a golden goal.', 1, 1, 'ref_golden_goal', 440),
('medals_teams', 'stat', '', 'analyse', 'T', 'Médailles des équipes', 'Team Medals', 'Nombre de médailles de chaque équipe, par médaille.', 'Number of medals of each team, by medal.', 0, 1, 'medals_teams', 500),
('medals_players', 'stat', '', 'analyse', 'T', 'Médailles des joueurs', 'Player Medals', 'Nombre de médailles de chaque joueur, par médaille.', 'Number of medals of each player, by medal.', 1, 1, 'medals_players', 510),
('opener_wins', 'stat', '', 'elan', 'T', 'Premier but gagnant', 'First Goal Wins', 'Part des matchs gagnés par l’équipe qui ouvre le score (matchs nuls à part).', 'Share of the games won by the team that opens the score (draws aside).', 0, 1, 'opener_wins', 520),
('powerplay_rate', 'stat', '', 'superiorite', 'T', 'Supériorité %', 'Powerplay %', 'Buts marqués en supériorité numérique, rapportés aux exclusions des adversaires.', 'Power-play goals, compared with the exclusions of the opponents.', 0, 1, 'powerplay_rate', 530),
('penalty_kill_rate', 'stat', '', 'superiorite', 'T', 'Résistance %', 'Penalty Kill %', 'Part des exclusions traversées sans encaisser de but.', 'Share of the exclusions gone through without conceding.', 0, 0, 'penalty_kill_rate', 540),
('goal_timing', 'stat', '', 'analyse', 'T', 'Minutes des buts', 'Goal Timing', 'Répartition des buts par tranche de {goalTimingSliceSeconds} secondes de jeu.', 'Goals by slice of {goalTimingSliceSeconds} seconds of play.', 0, 0, 'goal_timing', 550),
('first_goal_time', 'stat', '', 'elan', 'T', 'Délai du premier but', 'First Goal Time', 'Délai moyen et médian avant le premier but ; but le plus rapide.', 'Average and median time before the first goal; fastest goal.', 0, 0, 'first_goal_time', 560),
('comebacks', 'stat', '', 'reaction', 'T', 'Remontées', 'Comebacks', 'Nombre de remontées et plus grosse remontée.', 'Number of comebacks and biggest comeback.', 0, 0, 'comebacks', 570),
('overtimes', 'stat', '', 'endurance', 'S', 'Prolongations', 'Extra Time', 'Matchs en prolongation, plus grand nombre de prolongations, buts en or.', 'Games in overtime, most overtimes, golden goals.', 0, 0, 'overtimes', 580),
('discipline', 'stat', '', 'esprit', 'S', 'Discipline', 'Discipline', 'Cartons par match et par équipe, minutes d’infériorité.', 'Cards per game and per team, minutes shorthanded.', 0, 0, 'discipline', 590),
('highlights', 'stat', '', 'match', 'T', 'Matchs à revoir', 'Highlights', 'Matchs étiquetés Suspense, Chassé-croisé ou Festival.', 'Games labelled Thriller, Seesaw or Goal Fest.', 0, 0, 'highlights', 600),
('referee_games', 'stat', '', 'arbitrage', 'S', 'Matchs arbitrés', 'Games Officiated', 'Matchs arbitrés par chaque arbitre désigné, comme principal ou secondaire.', 'Games officiated by each designated referee, as first or second referee.', 0, 1, 'referee_games', 700),
('medals_referees', 'stat', '', 'arbitrage', 'S', 'Médailles des arbitres', 'Referee Medals', 'Médailles d’arbitre de chaque arbitre désigné.', 'Referee medals of each designated referee.', 0, 1, 'medals_referees', 710),
('referee_cards', 'stat', '', 'arbitrage', 'S', 'Cartons par match', 'Cards per Game', 'Cartons des matchs arbitrés, par match, à côté de la moyenne. Les cartons d’un match comptent pour ses deux arbitres.', 'Cards of the games officiated, per game, next to the average. The cards of a game count for both its referees.', 0, 0, 'referee_cards', 720),
('referee_smooth_rate', 'stat', '', 'arbitrage', 'S', 'Matchs fluides', 'Smooth Games', 'Part des matchs arbitrés sans aucun carton.', 'Share of the games officiated without any card.', 0, 0, 'referee_smooth_rate', 730),
('referee_card_timing', 'stat', '', 'arbitrage', 'T', 'Moment des cartons', 'Card Timing', 'Répartition des cartons des matchs arbitrés dans le temps de jeu.', 'Cards of the games officiated over the playing time.', 0, 0, 'referee_card_timing', 740),
('referee_card_balance', 'stat', '', 'arbitrage', 'S', 'Répartition entre équipes', 'Card Balance', 'Écart moyen, par match, entre les cartons des deux équipes.', 'Average gap, per game, between the cards of the two teams.', 0, 0, 'referee_card_balance', 750),
('referee_card_reasons', 'stat', '', 'arbitrage', 'S', 'Motifs des cartons', 'Card Reasons', 'Répartition des cartons des matchs arbitrés par motif saisi.', 'Cards of the games officiated by recorded reason.', 0, 0, 'referee_card_reasons', 760),
('referee_powerplay', 'stat', '', 'arbitrage', 'T', 'Impact des exclusions', 'Powerplay Impact', 'Part des buts des matchs arbitrés marqués en supériorité numérique.', 'Share of the goals of the games officiated scored on the power play.', 0, 0, 'referee_powerplay', 770),
('referee_pairs', 'stat', '', 'arbitrage', 'S', 'Binômes', 'Referee Pairs', 'Binômes d’arbitres désignés ayant arbitré ensemble.', 'Pairs of designated referees who officiated together.', 0, 0, 'referee_pairs', 780);
