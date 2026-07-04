<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Event Cache Service
 *
 * Generates the live cache JSON files consumed by the TV / scoreboard overlays:
 *   - live/cache/event<idEvent>_pitch<pitch>.json
 *   - live/cache/<idMatch>_match_global.json
 *
 * Faithful port of the legacy live/create_cache_match.php (CacheMatch class)
 * and live/event_worker.php generation loop. The legacy daemon read its
 * configuration from kp_event_worker_config and called CacheMatch->Event();
 * this service replicates that generation so the worker can be driven from
 * the api2 console command app:event-cache-worker.
 */
class EventCacheService
{
    /** Same accent → HTML entity substitution as the legacy EndCache(). */
    private const ACCENTS_IN = [
        'è', 'é', 'ê', 'ç', 'ô', 'î', 'â', 'à',
        'È', 'É', 'Ê', 'Ç', 'Ô', 'Î', 'Â', 'À',
        'Ï', 'Ä', 'Ë', 'Ö', 'Ü',
    ];
    private const ACCENTS_OUT = [
        '&egrave;', '&eacute;', '&ecric;', '&ccedil;', '&ocirc;', '&icirc;', '&acirc;', '&agrave;',
        '&Egrave;', '&Eacute;', '&Ecric;', '&Ccedil;', '&Ocirc;', '&Icirc;', '&Acirc;', '&Agrave;',
        '&Iuml;', '&Auml;', '&Euml;', '&Ouml;', '&Uuml;',
    ];

    private readonly string $cacheDir;

    public function __construct(
        private readonly Connection $connection,
        string $liveDocumentRoot,
    ) {
        $this->cacheDir = rtrim($liveDocumentRoot, '/') . '/live/cache';
    }

    /**
     * Generate cache files for one event/date/time across the given pitches.
     *
     * Port of CacheMatch::Event(). For each pitch it computes the "best"
     * (currently playing) match and the "next" match, then writes the pitch
     * JSON file. The global match file is generated on demand for the next
     * match if missing (matching the legacy GetNextMatch behaviour).
     *
     * @param int[] $pitches list of pitch numbers (1..N), empty = all pitches
     *
     * @return array<int, array<string, mixed>> per-pitch summary (mirrors the legacy return value)
     */
    public function generateEvent(
        int $idEvent,
        string $dateMatch,
        string $hourMatchWork,
        string $realTime,
        array $pitches = [],
    ): array {
        $matches = $this->fetchEventMatches($idEvent, $dateMatch, $pitches);

        // Distinct pitches actually present in the result set (legacy uses array_unique on Terrain)
        $eventPitches = array_values(array_unique(array_column($matches, 'Terrain')));

        $timeWork = $this->hhmmToMinutes($hourMatchWork);

        $result = [];
        foreach ($eventPitches as $pitch) {
            $best = $this->getBestMatch($matches, (string) $pitch, $timeWork);
            $next = $this->getNextMatch($matches, (string) $pitch, $best, $timeWork);

            $this->writePitch($idEvent, (string) $pitch, $best['id'], $next);

            $result[] = [
                'pitch' => $pitch,
                'game'  => $best['id'],
                'num'   => $best['num'],
                'time'  => $best['time'],
                'next'  => $next,
            ];
        }

        return $result;
    }

    // ─────────────────────────────────────────────
    // File writers (port of Pitch / MatchGlobal)
    // ─────────────────────────────────────────────

    /**
     * Write event<idEvent>_pitch<pitch>.json — port of CacheMatch::Pitch().
     */
    private function writePitch(int $idEvent, string $pitch, ?int $idMatch, array $next): void
    {
        $payload = [
            'id_match' => $idMatch,
            'pitch'    => $pitch,
            'id_next'  => $next,
        ];

        $this->writeCacheFile("event{$idEvent}_pitch{$pitch}.json", $payload);
    }

    /**
     * Write <idMatch>_match_global.json — port of CacheMatch::MatchGlobal().
     *
     * Loads the match, its journée, competition, both teams and their players
     * (auto-filling titulaires from kp_competition_equipe_joueur when no
     * kp_match_joueur rows exist yet, exactly like the legacy code).
     */
    public function generateMatchGlobal(int $idMatch): void
    {
        $rMatch = $this->connection->fetchAssociative(
            'SELECT * FROM kp_match WHERE Id = ?',
            [$idMatch]
        );
        if ($rMatch === false) {
            return;
        }

        $rJournee = $this->connection->fetchAssociative(
            'SELECT * FROM kp_journee WHERE Id = ?',
            [$rMatch['Id_journee']]
        ) ?: [];

        $rCompetition = $this->connection->fetchAssociative(
            'SELECT * FROM kp_competition WHERE Code = ? AND Code_saison = ?',
            [$rJournee['Code_competition'] ?? null, $rJournee['Code_saison'] ?? null]
        ) ?: [];

        $idEquipeA = (int) $rMatch['Id_equipeA'];
        $idEquipeB = (int) $rMatch['Id_equipeB'];

        $rEquipeA = $idEquipeA > 0 ? $this->loadTeam($idEquipeA) : null;
        $rEquipeB = $idEquipeB > 0 ? $this->loadTeam($idEquipeB) : null;
        $tJoueursA = $idEquipeA > 0 ? $this->loadPlayers($idMatch, 'A', $idEquipeA) : null;
        $tJoueursB = $idEquipeB > 0 ? $this->loadPlayers($idMatch, 'B', $idEquipeB) : null;

        $payload = [
            'id_match'           => $idMatch,
            'tick'               => uniqid(),
            'categ'              => $rCompetition['Soustitre2'] ?? null,
            'journee'            => $rJournee['Nom'] ?? null,
            'phase'              => $rJournee['Phase'] ?? null,
            'terrain'            => $rMatch['Terrain'],
            'date'               => $rMatch['Date_match'],
            'heure'              => $rMatch['Heure_match'],
            'numero_ordre'       => $rMatch['Numero_ordre'],
            'validation'         => $rMatch['Validation'],
            'statut'             => $rMatch['Statut'],
            'arbitre'            => $rMatch['Arbitre_principal'],
            'arbitre_secondaire' => $rMatch['Arbitre_secondaire'],
            'equipe1'            => [
                'id'        => $idEquipeA,
                'nom'       => $rEquipeA['Libelle'] ?? null,
                'club'      => $rEquipeA['Code_club'] ?? null,
                'logo'      => $rEquipeA['logo'] ?? null,
                'color1'    => $rEquipeA['color1'] ?? null,
                'color2'    => $rEquipeA['color2'] ?? null,
                'colortext' => $rEquipeA['colortext'] ?? null,
                'joueurs'   => $tJoueursA,
            ],
            'equipe2'            => [
                'id'        => $idEquipeB,
                'nom'       => $rEquipeB['Libelle'] ?? null,
                'club'      => $rEquipeB['Code_club'] ?? null,
                'logo'      => $rEquipeB['logo'] ?? null,
                'color1'    => $rEquipeB['color1'] ?? null,
                'color2'    => $rEquipeB['color2'] ?? null,
                'colortext' => $rEquipeB['colortext'] ?? null,
                'joueurs'   => $tJoueursB,
            ],
        ];

        $this->writeCacheFile("{$idMatch}_match_global.json", $payload);
    }

    private function loadTeam(int $idEquipe): ?array
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM kp_competition_equipe WHERE Id = ?',
            [$idEquipe]
        ) ?: null;
    }

    /**
     * Load players for a team in a match, auto-filling titulaires if missing.
     * Port of the kp_match_joueur loading + REPLACE INTO fallback.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadPlayers(int $idMatch, string $equipe, int $idEquipe): array
    {
        $sql = 'SELECT a.matric, a.Numero, a.Capitaine, b.Nom, b.Prenom, b.Sexe, b.Naissance
                FROM kp_match_joueur a, kp_licence b
                WHERE a.Id_match = ? AND a.Equipe = ? AND a.Matric = b.matric
                ORDER BY a.Numero';

        $players = $this->connection->fetchAllAssociative($sql, [$idMatch, $equipe]);

        if (count($players) === 0) {
            // Seed titulaires from the team roster (legacy REPLACE INTO behaviour)
            $this->connection->executeStatement(
                'REPLACE INTO kp_match_joueur
                    SELECT ?, Matric, Numero, ?, Capitaine
                    FROM kp_competition_equipe_joueur
                    WHERE Id_equipe = ? AND Capitaine <> ? AND Capitaine <> ?',
                [$idMatch, $equipe, $idEquipe, 'X', 'A']
            );

            $players = $this->connection->fetchAllAssociative($sql, [$idMatch, $equipe]);
        }

        return $players;
    }

    // ─────────────────────────────────────────────
    // Match selection (port of GetBestMatch / GetNextMatch)
    // ─────────────────────────────────────────────

    /**
     * @param array<int, array<string, mixed>> $matches
     *
     * @return array{id: int|null, time: string|null, num: int|null}
     */
    private function getBestMatch(array $matches, string $pitch, int $time): array
    {
        // Current match on a pitch = the one flagged Statut = 'ON'. The live Statut
        // is authoritative (staff flip matches ON/END as they play), so it stays
        // correct even when the real programme runs late. If none is ON yet (warm-up
        // before the first game), fall back to the latest scheduled match at or
        // before the working time so the pitch file is not empty.
        $fallbackIdx  = -1;
        $fallbackTime = 0;

        foreach ($matches as $i => $m) {
            if ((string) $m['Terrain'] !== $pitch) {
                continue;
            }
            if ($m['Statut'] === 'ON') {
                return [
                    'id'   => (int) $m['Id'],
                    'time' => $m['Heure_match'],
                    'num'  => $m['Numero_ordre'] !== null ? (int) $m['Numero_ordre'] : null,
                ];
            }
            if ($m['Statut'] === 'ATT') {
                continue;
            }
            $t = $this->hhmmToMinutes($m['Heure_match']);
            if ($t <= $time && ($fallbackIdx === -1 || $fallbackTime < $t)) {
                $fallbackIdx  = $i;
                $fallbackTime = $t;
            }
        }

        if ($fallbackIdx === -1) {
            return ['id' => null, 'time' => null, 'num' => null];
        }

        return [
            'id'   => (int) $matches[$fallbackIdx]['Id'],
            'time' => $matches[$fallbackIdx]['Heure_match'],
            'num'  => $matches[$fallbackIdx]['Numero_ordre'] !== null ? (int) $matches[$fallbackIdx]['Numero_ordre'] : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     *
     * @return array{id: int|null, time: string|null, num: int|null}
     */
    private function getNextMatch(array $matches, string $pitch, array $current, int $time): array
    {
        // Next match = the first ATT match scheduled after the current one. Anchored
        // to the current match's Heure_match (not the simulated clock) so a
        // late-running programme still shows the real upcoming game. Rows are ordered
        // by Heure_match ASC. If there is no current match, anchor on the working time.
        $anchor = $current['time'] !== null ? $this->hhmmToMinutes($current['time']) : $time;

        $nextIdx = -1;

        foreach ($matches as $i => $m) {
            if ((string) $m['Terrain'] !== $pitch || $m['Statut'] !== 'ATT') {
                continue;
            }
            $t = $this->hhmmToMinutes($m['Heure_match']);
            if ($nextIdx === -1 && $t > $anchor) {
                $nextIdx = $i;
            }
        }

        if ($nextIdx === -1) {
            return ['id' => null, 'time' => null, 'num' => null];
        }

        $idNext   = (int) $matches[$nextIdx]['Id'];
        $nextFile = $this->cacheDir . '/' . $idNext . '_match_global.json';

        // Generate the global file for the upcoming match if not yet present, or
        // refresh it if it was written too early (before the teams were assigned)
        // and both teams are now known in the database. Without this, a next-game
        // overlay generated before the draw stays stuck with equipe1/equipe2 = null
        // forever, since the file already exists.
        if (!is_file($nextFile) || $this->matchGlobalMissingTeams($nextFile, $matches[$nextIdx])) {
            $this->generateMatchGlobal($idNext);
        }

        return [
            'id'   => $idNext,
            'time' => $matches[$nextIdx]['Heure_match'],
            'num'  => $matches[$nextIdx]['Numero_ordre'] !== null ? (int) $matches[$nextIdx]['Numero_ordre'] : null,
        ];
    }

    /**
     * True when an already-written match_global.json is missing its teams
     * (equipe1/equipe2 ids null) while the match row now has both teams assigned.
     * Used to refresh a "next game" overlay generated before the draw.
     *
     * @param array<string, mixed> $matchRow the kp_match row for the next match
     */
    private function matchGlobalMissingTeams(string $file, array $matchRow): bool
    {
        // Nothing to refresh unless the database now has both teams
        if ((int) $matchRow['Id_equipeA'] <= 0 || (int) $matchRow['Id_equipeB'] <= 0) {
            return false;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return false;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            // Unreadable/corrupt file → let it be regenerated
            return true;
        }

        return empty($data['equipe1']['id']) || empty($data['equipe2']['id']);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    /**
     * @param int[] $pitches
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchEventMatches(int $idEvent, string $dateMatch, array $pitches): array
    {
        if (count($pitches) > 0) {
            $in = implode(',', array_fill(0, count($pitches), '?'));

            return $this->connection->fetchAllAssociative(
                "SELECT a.*
                 FROM kp_match a, kp_journee b, kp_evenement_journee c
                 WHERE a.Id_journee = b.Id
                   AND b.Id = c.Id_journee
                   AND c.Id_evenement = ?
                   AND a.Date_match = ?
                   AND a.Publication = 'O'
                   AND a.Terrain IN ($in)
                 ORDER BY a.Heure_match, a.Terrain",
                array_merge([$idEvent, $dateMatch], $pitches)
            );
        }

        return $this->connection->fetchAllAssociative(
            "SELECT a.*
             FROM kp_match a, kp_journee b, kp_evenement_journee c
             WHERE a.Id_journee = b.Id
               AND b.Id = c.Id_journee
               AND c.Id_evenement = ?
               AND a.Date_match = ?
               AND a.Publication = 'O'
             ORDER BY a.Heure_match, a.Terrain",
            [$idEvent, $dateMatch]
        );
    }

    private function writeCacheFile(string $fileName, array $payload): void
    {
        if (!is_dir($this->cacheDir) || !is_writable($this->cacheDir)) {
            return;
        }

        $json    = json_encode($payload);
        $content = str_replace(self::ACCENTS_IN, self::ACCENTS_OUT, $json);

        file_put_contents($this->cacheDir . '/' . $fileName, $content);
    }

    /** 10:30 => 630, 10:30:00 => 630 (port of utyHHMM_To_MM). */
    public function hhmmToMinutes(string $hour): int
    {
        $parts = explode(':', $hour);
        if (count($parts) >= 2) {
            return (int) $parts[0] * 60 + (int) $parts[1];
        }

        return (int) $hour;
    }

    /** 630 => "10:30" (port of utyMM_To_HHMM). */
    public function minutesToHhmm(int $time): string
    {
        $h = intdiv($time, 60);

        return sprintf('%02d:%02d', $h, $time - 60 * $h);
    }
}
