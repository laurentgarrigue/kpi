<?php

namespace App\Designation;

use App\Competition\CompetitionLevel;

/**
 * Matrice de couverture et récapitulatif des Désignations (FEATURE_DESIGNATIONS.md D11, D26 ; SUI-01 à SUI-06).
 *
 * Lignes : compétitions (case RC), leurs journées (rôles de journée, héritage D9 visible) et leurs équipes (case
 * responsable d'équipe). Une case porte l'exigence du rôle, ses titulaires effectifs, d'où ils viennent et son état.
 */
final class CoverageService
{
    public const LEVEL_ALL = 'all';
    public const LEVEL_NATIONAL = 'national';
    public const LEVEL_OTHER = 'other';

    /** Colonnes des journées, dans l'ordre d'affichage. */
    public const GAMEDAY_ROLES = ['DELEGUE_CNA', 'R1', 'JUGE_ARBITRE', 'ARBITRE_NJ', 'REP_EQUIPES', 'JURY_APPEL'];
    private const COMPETITION_ROLE = 'RC';
    private const TEAM_ROLE = 'RESP_EQUIPE';

    public function __construct(
        private readonly CoverageRepository $coverage,
        private readonly DesignationRepository $designations,
    ) {
    }

    /**
     * @param list<string> $competitionCodes filtre facultatif
     *
     * @return array{season: string, gamedayRoles: list<string>, competitions: list<array<string, mixed>>}
     */
    public function matrix(string $season, string $level, array $competitionCodes, bool $onlyGaps): array
    {
        $competitions = array_values(array_filter(
            $this->coverage->competitions($season),
            static fn (array $c): bool => self::matchesLevel($c['code'], $level) && ($competitionCodes === [] || in_array($c['code'], $competitionCodes, true)),
        ));
        $codes = array_column($competitions, 'code');
        $gamedays = $this->coverage->gamedays($season, $codes);
        $teams = $this->coverage->teams($season, $codes);
        $designations = $this->designations->ofSeason($season);
        $people = $this->coverage->people(array_values(array_unique(array_map(static fn (Designation $d): int => $d->matric, $designations))));

        $teamClubs = [];
        foreach ($teams as $team) {
            if ($team['club'] !== null) {
                $teamClubs[$team['competition']][$team['club']] = true;
            }
        }

        $rows = [];
        foreach ($competitions as $competition) {
            $code = $competition['code'];
            $national = CompetitionLevel::isNational($code);
            $clubs = $teamClubs[$code] ?? [];
            $competitionHolders = array_values(array_filter($designations, static fn (Designation $d): bool => $d->role === self::COMPETITION_ROLE
                && $d->scopeType === ScopeType::Competition && $d->scopeId === $code));

            $gamedayRows = [];
            foreach ($gamedays as $gameday) {
                if ($gameday['competition'] !== $code) {
                    continue;
                }
                $effective = EffectiveDesignations::of(new GamedayScope($gameday['id'], $season, $code, $gameday['eventIds']), $designations);
                $cells = [];
                foreach (self::GAMEDAY_ROLES as $role) {
                    $cells[$role] = $this->cell($role, $national, $effective->holders($role), $people, $clubs);
                }
                $gamedayRows[] = $gameday + ['cells' => $cells];
            }

            $teamRows = [];
            foreach ($teams as $team) {
                if ($team['competition'] === $code) {
                    $holders = array_values(array_filter($designations, static fn (Designation $d): bool => $d->role === self::TEAM_ROLE
                        && $d->scopeType === ScopeType::Equipe && $d->scopeId === (string) $team['id']));
                    // un responsable d'équipe est normalement du club de son équipe : pas d'alerte de club
                    $teamRows[] = $team + ['cells' => [self::TEAM_ROLE => $this->cell(self::TEAM_ROLE, $national, $holders, $people, [])]];
                }
            }

            $row = $competition + [
                'national' => $national,
                'cells' => [self::COMPETITION_ROLE => $this->cell(self::COMPETITION_ROLE, $national, $competitionHolders, $people, $clubs)],
                'gamedays' => $gamedayRows,
                'teams' => $teamRows,
            ];
            if ($onlyGaps) {
                $row = self::onlyGaps($row);
                if ($row === null) {
                    continue;
                }
            }
            $rows[] = $row;
        }

        return ['season' => $season, 'gamedayRoles' => self::GAMEDAY_ROLES, 'competitions' => $rows];
    }

    /**
     * Récapitulatif par personne et par club (SUI-06) : Désignations par rôle et journées couvertes (effectives,
     * héritage compris), sur la saison ou sur une période de dates de journée.
     *
     * @return array{people: list<array<string, mixed>>, clubs: list<array<string, mixed>>}
     */
    public function summary(string $season, ?string $from, ?string $to): array
    {
        $designations = $this->designations->ofSeason($season);
        $codes = array_column($this->coverage->competitions($season), 'code');
        $gamedays = array_filter(
            $this->coverage->gamedays($season, $codes),
            static fn (array $g): bool => ($from === null || ($g['end'] ?? $g['start']) >= $from) && ($to === null || $g['start'] <= $to),
        );

        $people = [];
        $add = static function (Designation $d) use (&$people): void {
            $people[$d->matric] ??= ['matric' => $d->matric, 'club' => $d->club, 'designations' => [], 'gamedays' => []];
        };
        foreach ($designations as $designation) {
            $add($designation);
            $people[$designation->matric]['designations'][$designation->role] = ($people[$designation->matric]['designations'][$designation->role] ?? 0) + 1;
        }
        foreach ($gamedays as $gameday) {
            $effective = EffectiveDesignations::of(new GamedayScope($gameday['id'], $season, $gameday['competition'], $gameday['eventIds']), $designations);
            foreach (self::GAMEDAY_ROLES as $role) {
                foreach ($effective->holders($role) as $holder) {
                    $people[$holder->matric]['gamedays'][$role][$gameday['id']] = true;
                }
            }
        }

        $names = $this->coverage->people(array_keys($people));
        $peopleRows = [];
        $clubs = [];
        foreach ($people as $matric => $person) {
            $gamedaysByRole = array_map('count', $person['gamedays']);
            $peopleRows[] = [
                'matric' => $matric,
                'name' => $names[$matric]['name'] ?? '',
                'club' => $person['club'],
                'designations' => $person['designations'],
                'gamedays' => $gamedaysByRole,
            ];
            $club = $person['club'] ?? '';
            $clubs[$club] ??= ['club' => $person['club'], 'people' => 0, 'designations' => [], 'gamedays' => []];
            $clubs[$club]['people']++;
            foreach ($person['designations'] as $role => $count) {
                $clubs[$club]['designations'][$role] = ($clubs[$club]['designations'][$role] ?? 0) + $count;
            }
            foreach ($gamedaysByRole as $role => $count) {
                $clubs[$club]['gamedays'][$role] = ($clubs[$club]['gamedays'][$role] ?? 0) + $count;
            }
        }
        usort($peopleRows, static fn (array $a, array $b): int => [$a['name'], $a['matric']] <=> [$b['name'], $b['matric']]);
        ksort($clubs);

        return ['people' => $peopleRows, 'clubs' => array_values($clubs)];
    }

    /**
     * @param list<Designation>                                $holders
     * @param array<int, array{name: string, hasEmail: bool}> $people
     * @param array<string, true>                              $engagedClubs
     *
     * @return array<string, mixed>
     */
    private function cell(string $role, bool $national, array $holders, array $people, array $engagedClubs): array
    {
        $coverageHolders = [];
        $rows = [];
        foreach ($holders as $designation) {
            $holder = new CoverageHolder(
                $people[$designation->matric]['hasEmail'] ?? false,
                $designation->club !== null && isset($engagedClubs[$designation->club]),
            );
            $coverageHolders[] = $holder;
            $rows[] = [
                'designationId' => $designation->id,
                'matric' => $designation->matric,
                'name' => $people[$designation->matric]['name'] ?? '',
                'club' => $designation->club,
                'origin' => $designation->scopeType->value,
                'warnings' => $holder->warnings(),
            ];
        }
        $requirement = RoleCatalog::get($role)->requirement($national);

        return [
            'requirement' => $requirement->value,
            'status' => CoverageStatus::of($requirement, $coverageHolders)->value,
            'holders' => $rows,
        ];
    }

    /**
     * SUI-05 : seulement les lignes qui ont au moins une case rouge ; la compétition reste si elle en a une, ou si
     * l'une de ses journées ou équipes en a une.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>|null
     */
    private static function onlyGaps(array $row): ?array
    {
        $hasRed = static fn (array $line): bool => in_array(CoverageStatus::Red->value, array_column($line['cells'], 'status'), true);
        $row['gamedays'] = array_values(array_filter($row['gamedays'], $hasRed));
        $row['teams'] = array_values(array_filter($row['teams'], $hasRed));

        return $hasRed($row) || $row['gamedays'] !== [] || $row['teams'] !== [] ? $row : null;
    }

    private static function matchesLevel(string $code, string $level): bool
    {
        return match ($level) {
            self::LEVEL_NATIONAL => CompetitionLevel::isNational($code),
            self::LEVEL_OTHER => !CompetitionLevel::isNational($code),
            default => true,
        };
    }
}
