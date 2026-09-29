<?php

namespace App\Eligibility;

use App\Eligibility\PlayerEligibilityRules as Rules;
use Doctrine\DBAL\Connection;

/**
 * Applies PlayerEligibilityRules. Holds no rule itself: edit PlayerEligibilityRules
 * to change what "en règle" means. Stateless (safe in FrankenPHP worker mode).
 */
class PlayerEligibilityChecker
{
    public const OPERATION_ADD = 'add';
    public const OPERATION_STATUS_CHANGE = 'status';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function levelFor(string $competitionCode, ?string $codeNiveau): ?string
    {
        return Rules::levelFor($competitionCode, $codeNiveau);
    }

    /**
     * Rule set summary exposed to app4 (null when the competition has no control).
     *
     * @return array{level: string, enforcement: string, forceMaxProfile: int, forceAddStatuses: string[], forceStatusChangeStatuses: string[], playingStatuses: string[], minPagaieECA: ?string, surclassement: bool}|null
     */
    public function describe(?string $level): ?array
    {
        if ($level === null) {
            return null;
        }
        $rules = Rules::RULES[$level];

        return [
            'level' => $level,
            'enforcement' => $rules['enforcement'],
            'forceMaxProfile' => Rules::FORCE_MAX_PROFILE,
            'forceAddStatuses' => Rules::FORCE_ADD_STATUSES,
            'forceStatusChangeStatuses' => Rules::FORCE_STATUS_CHANGE_STATUSES,
            'playingStatuses' => Rules::PLAYING_STATUSES,
            'minPagaieECA' => $rules['minPagaieECA'],
            'surclassement' => (bool) $rules['surclassement'],
        ];
    }

    /**
     * Evaluate a licence row against a rule set. Pure function (no DB access).
     *
     * @param array{Origine?: ?string, Type_licence?: ?string, Etat_certificat_CK?: ?string, Pagaie_ECA?: ?string, date_surclassement?: ?string} $row
     *        Missing/null values count as not satisfied.
     * @param string $categ Age category for the competition season (for the overclassing check)
     *
     * @return string[] Error codes (Rules::ERROR_*), empty when the player is compliant
     */
    public function evaluate(array $row, string $level, string $season, string $competitionCode, string $categ): array
    {
        $rules = Rules::RULES[$level];
        $errors = [];

        if ($rules['licenceSeason'] && (string) ($row['Origine'] ?? '') < $season) {
            $errors[] = Rules::ERROR_LICENCE_SEASON;
        }

        if ($rules['licenceTypes'] !== null && !in_array($row['Type_licence'] ?? null, $rules['licenceTypes'], true)) {
            $errors[] = Rules::ERROR_LICENCE_TYPE;
        }

        if ($rules['certificateCK'] && ($row['Etat_certificat_CK'] ?? null) !== 'OUI') {
            $errors[] = Rules::ERROR_CERTIFICATE;
        }

        if ($rules['minPagaieECA'] !== null) {
            $rank = Rules::PAGAIE_RANKS[$row['Pagaie_ECA'] ?? ''] ?? 0;
            if ($rank < Rules::PAGAIE_RANKS[$rules['minPagaieECA']]) {
                $errors[] = Rules::ERROR_PAGAIE;
            }
        }

        if ($rules['surclassement']
            && $this->requiresSurclassement($competitionCode, $categ)
            && empty($row['date_surclassement'])
        ) {
            $errors[] = Rules::ERROR_SURCLASSEMENT;
        }

        return $errors;
    }

    /**
     * Decide whether a non-compliant player may take a status. Pure function.
     *
     * - ENFORCEMENT_WARN: always allowed, errors become warnings.
     * - ENFORCEMENT_BLOCK, add: refused unless forced by a profile <= FORCE_MAX_PROFILE
     *   into one of FORCE_ADD_STATUSES.
     * - ENFORCEMENT_BLOCK, status change: a non-playing status is always allowed; a
     *   playing status is refused unless forced by a profile <= FORCE_MAX_PROFILE into
     *   one of FORCE_STATUS_CHANGE_STATUSES.
     *
     * @param string[] $errors Output of evaluate()
     *
     * @return array{allowed: bool, warnings: string[], canForce: bool}
     */
    public function decide(string $level, array $errors, string $operation, string $status, bool $force, int $profile): array
    {
        if ($errors === []) {
            return ['allowed' => true, 'warnings' => [], 'canForce' => false];
        }

        $isPlayingStatus = in_array($status, Rules::PLAYING_STATUSES, true);

        if (Rules::RULES[$level]['enforcement'] === Rules::ENFORCEMENT_WARN) {
            // A status change only matters when it makes the person play
            $warn = $operation === self::OPERATION_ADD || $isPlayingStatus;
            return ['allowed' => true, 'warnings' => $warn ? $errors : [], 'canForce' => false];
        }

        if ($operation === self::OPERATION_STATUS_CHANGE && !$isPlayingStatus) {
            return ['allowed' => true, 'warnings' => [], 'canForce' => false];
        }

        $forceStatuses = $operation === self::OPERATION_ADD
            ? Rules::FORCE_ADD_STATUSES
            : Rules::FORCE_STATUS_CHANGE_STATUSES;
        $canForce = $profile <= Rules::FORCE_MAX_PROFILE;

        return [
            'allowed' => $force && $canForce && in_array($status, $forceStatuses, true),
            'warnings' => [],
            'canForce' => $canForce,
        ];
    }

    /**
     * Evaluate a licensed player (by matric) for a competition.
     *
     * @return string[] Error codes, empty when compliant
     */
    public function checkPlayer(int $matric, string $level, string $season, string $competitionCode): array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT lc.Origine, lc.Type_licence, lc.Etat_certificat_CK, lc.Pagaie_ECA, lc.Naissance,
                    s.Date AS date_surclassement
             FROM kp_licence lc
             LEFT JOIN kp_surclassement s ON lc.Matric = s.Matric AND s.Saison = ?
             WHERE lc.Matric = ?",
            [$season, $matric]
        );

        return $this->evaluate(
            $row ?: [],
            $level,
            $season,
            $competitionCode,
            $this->calculateCategory($row['Naissance'] ?? null, $season)
        );
    }

    /**
     * Apply the rules to a roster that was just copied into a team.
     *
     * Blocking level: players with a playing status who are not compliant are switched
     * to COPY_INELIGIBLE_STATUS. Warning level: nothing changes, they are only reported.
     *
     * @return array{level: ?string, enforcement: ?string, count: int, players: list<array{matric: int, nom: string, prenom: string, errors: string[]}>}
     */
    public function enforceOnCopiedRoster(int $teamId): array
    {
        $team = $this->connection->fetchAssociative(
            "SELECT ce.Code_compet, ce.Code_saison, comp.Code_niveau
             FROM kp_competition_equipe ce
             LEFT JOIN kp_competition comp ON comp.Code = ce.Code_compet AND comp.Code_saison = ce.Code_saison
             WHERE ce.Id = ?",
            [$teamId]
        );
        $level = $team ? $this->levelFor($team['Code_compet'], $team['Code_niveau']) : null;
        if ($level === null) {
            return ['level' => null, 'enforcement' => null, 'count' => 0, 'players' => []];
        }

        $season = $team['Code_saison'];
        $competitionCode = $team['Code_compet'];
        $enforcement = Rules::RULES[$level]['enforcement'];

        $placeholders = implode(',', array_fill(0, count(Rules::PLAYING_STATUSES), '?'));
        $rows = $this->connection->fetchAllAssociative(
            "SELECT cej.Matric, cej.Nom, cej.Prenom,
                    lc.Origine, lc.Type_licence, lc.Etat_certificat_CK, lc.Pagaie_ECA, lc.Naissance,
                    s.Date AS date_surclassement
             FROM kp_competition_equipe_joueur cej
             LEFT JOIN kp_licence lc ON lc.Matric = cej.Matric
             LEFT JOIN kp_surclassement s ON s.Matric = cej.Matric AND s.Saison = ?
             WHERE cej.Id_equipe = ? AND cej.Capitaine IN ($placeholders)",
            array_merge([$season, $teamId], Rules::PLAYING_STATUSES)
        );

        $categories = [];
        $ineligible = [];
        foreach ($rows as $row) {
            $birthYear = $row['Naissance'] ? substr($row['Naissance'], 0, 4) : '';
            $categories[$birthYear] ??= $this->calculateCategory($row['Naissance'], $season);

            $errors = $this->evaluate($row, $level, $season, $competitionCode, $categories[$birthYear]);
            if ($errors === []) {
                continue;
            }

            if ($enforcement === Rules::ENFORCEMENT_BLOCK) {
                $this->connection->update(
                    'kp_competition_equipe_joueur',
                    ['Capitaine' => Rules::COPY_INELIGIBLE_STATUS],
                    ['Id_equipe' => $teamId, 'Matric' => $row['Matric']]
                );
            }

            $ineligible[] = [
                'matric' => (int) $row['Matric'],
                'nom' => $row['Nom'] ?? '',
                'prenom' => $row['Prenom'] ?? '',
                'errors' => $errors,
            ];
        }

        return [
            'level' => $level,
            'enforcement' => $enforcement,
            'count' => count($ineligible),
            'players' => $ineligible,
        ];
    }

    /**
     * Merge several enforceOnCopiedRoster() results (several teams copied at once).
     *
     * @param list<array{level: ?string, enforcement: ?string, count: int, players: list<array>}> $results
     *
     * @return array{enforcement: ?string, count: int}
     */
    public static function summarize(array $results): array
    {
        $enforcement = null;
        $count = 0;
        foreach ($results as $result) {
            $enforcement ??= $result['enforcement'];
            $count += $result['count'];
        }

        return ['enforcement' => $enforcement, 'count' => $count];
    }

    public function requiresSurclassement(string $competitionCode, string $categ): bool
    {
        if (in_array($categ, Rules::SURCLASSEMENT_EXEMPT_CATEGORIES, true)) {
            return false;
        }

        return in_array($competitionCode, Rules::SURCLASSEMENT_COMPETITIONS, true);
    }

    public function calculateCategory(?string $birthDate, string $season): string
    {
        if (!$birthDate) {
            return '';
        }

        $age = (int) $season - (int) substr($birthDate, 0, 4);
        $row = $this->connection->fetchAssociative(
            "SELECT id FROM kp_categorie WHERE age_min <= ? AND age_max >= ? LIMIT 1",
            [$age, $age]
        );

        return $row ? $row['id'] : '';
    }
}
