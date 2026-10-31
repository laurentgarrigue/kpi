<?php

namespace App\Pdf\Scoresheet;

use App\Pdf\Presence\PresencePlayer;
use App\PublicResults\CompetitionRules;

/**
 * Content of one scoresheet (`scoresheet`, ex-admin/FeuilleMatchMulti.php, PDF_GOTENBERG.md § 7.3.3): same fields and
 * rules as the legacy official sheet. Pure functions, unit-tested.
 */
final class ScoresheetSheet
{
    /** Lines of each roster: 12 for an international competition, 10 otherwise. */
    public const ROSTER_LINES = 10;
    public const INTERNATIONAL_ROSTER_LINES = 12;

    /** Lines of the event grid on the first page. */
    public const EVENT_LINES = 26;

    /** Level of the referees, left out of their names (utyArbSansNiveau). */
    private const REFEREE_LEVELS = [' (Pool Arbitres 1)', ' (Pool Arbitres 2)', ' INT-A', ' INT-B', ' INT-C', ' INT-S', ' INT', ' NAT-A', ' NAT-B', ' NAT-C', ' NAT-S', ' NAT', ' REG-S', 'REG', ' OTM', ' JO'];

    /** Not appointed yet. */
    private const NO_REFEREE = '-1';

    /** Categories by age in the season (utyCodeCategorie2). */
    private const CATEGORIES = [[9, 'POU'], [11, 'BEN'], [13, 'MIN'], [15, 'CAD'], [17, 'JUN'], [19, 'SEN'], [35, 'V1'], [40, 'V2'], [45, 'V3'], [50, 'V4'], [55, 'V5'], [60, 'V6'], [65, 'V7'], [70, 'V8'], [75, 'V9']];

    /** Columns of an event of team A / team B in the grid. */
    /** Card columns of the event grid; D (definitive red, future black card) has its own. */
    private const CARD_COLUMNS = ['V' => 'green', 'J' => 'yellow', 'R' => 'red', 'D' => 'definitive'];

    private const GOAL = 'B';
    private const FIRST_PERIOD = 'M1';
    private const UNPLAYED_SCORE = '?';

    /**
     * Roster of one team, padded with empty lines: coach marked « C », captain « (Cap) », licence with its season
     * of origin, category with « (sup) » for a player who is not in the team roster.
     *
     * @param list<array<string, mixed>> $players
     * @param array<string, string>      $labels
     *
     * @return list<array{number: string, last_name: string, first_name: string, licence: string, category: string, coach: bool}>
     */
    public static function roster(array $players, string $season, bool $international, array $labels): array
    {
        $lines = [];
        foreach ($players as $player) {
            $name = PresencePlayer::lastName($player['Nom']);
            $number = (string) $player['Numero'];
            // Labels of the roles, as on the presence sheet: « (Capitaine) » after the name, « Staff » as number.
            if ($player['Capitaine'] === 'E') {
                $number = PresencePlayer::role('E', $labels);
            } elseif ($player['Capitaine'] === 'C') {
                $name .= ' (' . PresencePlayer::role('C', $labels) . ')';
            }
            $matric = (int) $player['Matric'];
            $licence = match (true) {
                $international && (int) $player['icf'] !== 0 => 'Icf-' . $player['icf'],
                $matric < PresencePlayer::PROVISIONAL_LICENCE_FROM => (string) $matric,
                default => '',
            };
            $origin = (string) $player['Origine'];
            if ($matric < PresencePlayer::PROVISIONAL_LICENCE_FROM && $origin !== '' && $origin < $season) {
                $licence .= ' (' . $origin . ')';
            }
            $category = self::category($player['Naissance'], $season);
            $lines[] = [
                'number' => $number,
                'last_name' => $name,
                'first_name' => mb_convert_case((string) $player['Prenom'], MB_CASE_TITLE, 'UTF-8'),
                'licence' => $licence,
                'category' => (int) $player['titular'] === $matric ? $category : $category . '(sup)',
                'coach' => $player['Capitaine'] === 'E',
            ];
        }
        $size = max(count($lines), $international ? self::INTERNATIONAL_ROSTER_LINES : self::ROSTER_LINES);

        return array_pad($lines, $size, ['number' => '', 'last_name' => '', 'first_name' => '', 'licence' => '', 'category' => '', 'coach' => false]);
    }

    /** Category of a player by age in the season, '' when unknown. */
    public static function category(?string $birthDate, string $season): string
    {
        $year = (int) substr((string) $birthDate, 0, 4);
        if ($year === 0) {
            return '';
        }
        $age = (int) $season - $year;
        $category = '';
        foreach (self::CATEGORIES as [$from, $code]) {
            if ($age >= $from) {
                $category = $code;
            }
        }

        return $category;
    }

    /**
     * Event grid: one line per event, padded to EVENT_LINES; goals counted by team and at half time.
     *
     * @param list<array<string, mixed>> $events
     * @param array<string, string>      $labels
     *
     * @return array{lines: list<array<string, string>>, goals: array{A: int, B: int}, half_time: array{A: int, B: int}}
     */
    public static function events(array $events, array $labels): array
    {
        $lines = [];
        $goals = ['A' => 0, 'B' => 0];
        $halfTime = ['A' => 0, 'B' => 0];
        foreach ($events as $event) {
            $side = $event['Equipe_A_B'] === 'B' ? 'B' : 'A';
            $prefix = strtolower($side) . '_';
            $reason = $event['motif'] !== null && $event['motif'] !== '' ? ' (' . ($labels['scoresheet.reason_' . $event['motif']] ?? $event['motif']) . ')' : '';
            $player = $event['Nom'] !== null && $event['Nom'] !== ''
                ? sprintf('%s - %s%s', $event['Numero'], self::shortName((string) $event['Nom'], (string) $event['Prenom']), $reason)
                : $labels['scoresheet.team'] . ' ' . $side;
            $time = substr((string) $event['Temps'], 3, 5);
            $line = ['time' => $event['Periode'] . ' - ' . ($time === '00:00' ? '' : $time), $prefix . 'player' => $player];
            $type = (string) $event['Id_evt_match'];
            if ($type === self::GOAL) {
                $line[$prefix . 'goal'] = 'X';
                ++$goals[$side];
                if ($event['Periode'] === self::FIRST_PERIOD) {
                    ++$halfTime[$side];
                }
            } elseif (isset(self::CARD_COLUMNS[$type])) {
                $line[$prefix . self::CARD_COLUMNS[$type]] = 'X';
            }
            $lines[] = $line;
        }

        return [
            'lines' => array_pad($lines, max(count($lines), self::EVENT_LINES), []),
            'goals' => $goals,
            'half_time' => $halfTime,
        ];
    }

    /** « DUP. J-M. » (utyNomPrenomCourt). */
    public static function shortName(string $lastName, string $firstName): string
    {
        $initials = array_map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)), explode('-', $firstName));

        return mb_strtoupper(mb_substr($lastName, 0, 3)) . '. ' . implode('-', $initials) . '.';
    }

    /** Referee without its level; empty when not appointed yet. */
    public static function referee(?string $name): string
    {
        return $name === null || $name === self::NO_REFEREE ? '' : trim(str_replace(self::REFEREE_LEVELS, '', $name));
    }

    /** Score of a team, empty while not played. */
    public static function score(?string $score): string
    {
        return $score === null || $score === self::UNPLAYED_SCORE ? '' : $score;
    }

    /**
     * The score is « provisional » while the events do not add up to it.
     *
     * @param array{A: int, B: int} $goals
     */
    public static function isProvisional(array $goals, string $scoreA, string $scoreB): bool
    {
        return ($goals['A'] > 0 || $goals['B'] > 0) && ((string) $goals['A'] !== $scoreA || (string) $goals['B'] !== $scoreB);
    }

    /** Officials of the header: name without licence (utyGetNomPrenom). */
    public static function person(?string $value): string
    {
        return (string) CompetitionRules::personName($value);
    }
}
