<?php

namespace App\Report;

use App\Designation\DesignationRepository;
use App\Designation\EffectiveDesignations;
use App\Designation\GamedayScope;
use App\Designation\RoleCatalog;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Données KPI d'un rapport, au-delà de l'en-tête du périmètre (FEATURE_DESIGNATIONS.md § 7, BIL-03, BIL-07) : officiels
 * désignés effectifs, arbitres désignés des matchs, matchs prévus et joués, cartons par compétition, autres rapports
 * du même périmètre. Elles sont figées à la soumission avec l'en-tête.
 */
final class ReportKpiData
{
    /** Rôles listés dans les officiels d'un bilan, dans cet ordre. */
    private const OFFICIAL_ROLES = ['DELEGUE_CNA', 'R1', 'JUGE_ARBITRE', 'ARBITRE_NJ', 'REP_EQUIPES', 'JURY_APPEL', 'RC'];
    /** Composition du jury d'appel (D27) : délégué CNA (président), R1, représentant des équipes, membres désignés. */
    private const JURY_ROLES = ['DELEGUE_CNA', 'R1', 'REP_EQUIPES', 'JURY_APPEL'];
    private const CARD_CODES = ['V' => 'green', 'J' => 'yellow', 'R' => 'red', 'D' => 'black'];
    private const PLAYED_STATUS = 'END';

    public function __construct(
        private readonly Connection $connection,
        private readonly DesignationRepository $designations,
    ) {
    }

    /** @return array<string, mixed> */
    public function of(ReportPerimeter $perimeter, ?int $excludedReportId = null): array
    {
        return $perimeter->header + [
            'officials' => $this->officials($perimeter, self::OFFICIAL_ROLES),
            'jury' => $this->officials($perimeter, self::JURY_ROLES),
            'referees' => $this->referees($perimeter->matchIds),
            'matches' => $this->matches($perimeter->matchIds),
            'cards' => $this->cards($perimeter->matchIds),
            'linked' => $this->linked($perimeter, $excludedReportId),
        ];
    }

    /**
     * @param list<string> $roles rôles retenus, dans l'ordre d'affichage
     *
     * @return list<array{role: string, name: string, matric: int}>
     */
    private function officials(ReportPerimeter $perimeter, array $roles): array
    {
        $designations = $this->designations->ofSeason($perimeter->season);
        $holders = [];
        foreach ($perimeter->gamedays as $gamedayId => $competition) {
            $effective = EffectiveDesignations::of(new GamedayScope($gamedayId, $perimeter->season, $competition, $perimeter->gamedayEvents[$gamedayId] ?? []), $designations);
            foreach ($roles as $role) {
                foreach ($effective->holders($role) as $holder) {
                    $holders["$role|{$holder->matric}"] = ['role' => $role, 'matric' => $holder->matric];
                }
            }
        }
        uasort($holders, static fn (array $a, array $b): int => array_search($a['role'], $roles, true) <=> array_search($b['role'], $roles, true));
        $names = $this->names(array_values(array_unique(array_column($holders, 'matric'))));

        return array_values(array_map(static fn (array $h): array => [
            'role' => $h['role'] === 'DELEGUE_CNA' && $roles === self::JURY_ROLES ? 'Président (délégué CNA)' : RoleCatalog::get($h['role'])->label,
            'name' => $names[$h['matric']] ?? '',
            'matric' => $h['matric'],
        ], $holders));
    }

    /**
     * @param list<int> $matchIds
     *
     * @return list<array{name: string, matric: int, games: int}>
     */
    private function referees(array $matchIds): array
    {
        if ($matchIds === []) {
            return [];
        }
        $games = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT Matric_arbitre_principal AS a, Matric_arbitre_secondaire AS b FROM kp_match WHERE Id IN (?)',
            [$matchIds],
            [ArrayParameterType::INTEGER],
        ) as $row) {
            foreach ([$row['a'], $row['b']] as $matric) {
                if ((int) $matric > 0) {
                    $games[(int) $matric] = ($games[(int) $matric] ?? 0) + 1;
                }
            }
        }
        $names = $this->names(array_keys($games));
        $rows = [];
        foreach ($games as $matric => $count) {
            $rows[] = ['name' => $names[$matric] ?? '', 'matric' => $matric, 'games' => $count];
        }
        usort($rows, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $rows;
    }

    /**
     * @param list<int> $matchIds
     *
     * @return array{planned: int, played: int}
     */
    private function matches(array $matchIds): array
    {
        if ($matchIds === []) {
            return ['planned' => 0, 'played' => 0];
        }
        $played = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM kp_match WHERE Id IN (?) AND Statut = ?',
            [$matchIds, self::PLAYED_STATUS],
            [ArrayParameterType::INTEGER],
        );

        return ['planned' => count($matchIds), 'played' => $played];
    }

    /**
     * Cartons par compétition (timeline des matchs du périmètre, D25 § 6).
     *
     * @param list<int> $matchIds
     *
     * @return array<string, array{green: int, yellow: int, red: int, black: int}>
     */
    private function cards(array $matchIds): array
    {
        if ($matchIds === []) {
            return [];
        }
        $cards = [];
        foreach ($this->connection->fetchAllAssociative(
            "SELECT j.Code_competition AS competition, md.Id_evt_match AS code, COUNT(*) AS n
             FROM kp_match_detail md JOIN kp_match m ON m.Id = md.Id_match JOIN kp_journee j ON j.Id = m.Id_journee
             WHERE md.Id_match IN (?) AND md.Id_evt_match IN ('V', 'J', 'R', 'D')
             GROUP BY j.Code_competition, md.Id_evt_match",
            [$matchIds],
            [ArrayParameterType::INTEGER],
        ) as $row) {
            $cards[$row['competition']] ??= ['green' => 0, 'yellow' => 0, 'red' => 0, 'black' => 0];
            $cards[$row['competition']][self::CARD_CODES[$row['code']]] = (int) $row['n'];
        }
        ksort($cards);

        return $cards;
    }

    /**
     * Rapports rattachés au périmètre, à l'une de ses journées ou à l'un de ses matchs (BIL-07).
     *
     * @return list<array{id: int, type: string, scopeType: string, scopeId: string, state: string}>
     */
    private function linked(ReportPerimeter $perimeter, ?int $excludedReportId): array
    {
        $scopes = [[$perimeter->scope->value, $perimeter->scopeId]];
        foreach (array_keys($perimeter->gamedays) as $gamedayId) {
            $scopes[] = [ReportScope::Journee->value, (string) $gamedayId];
        }
        foreach ($perimeter->matchIds as $matchId) {
            $scopes[] = [ReportScope::Match->value, (string) $matchId];
        }
        $conditions = implode(' OR ', array_fill(0, count($scopes), '(perimetre_type = ? AND perimetre_id = ?)'));
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, type, perimetre_type, perimetre_id, etat FROM kp_report WHERE saison = ? AND ($conditions) ORDER BY id",
            [$perimeter->season, ...array_merge(...$scopes)],
        );

        return array_values(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'type' => $row['type'],
            'scopeType' => $row['perimetre_type'],
            'scopeId' => (string) $row['perimetre_id'],
            'state' => $row['etat'],
        ], array_filter($rows, static fn (array $row): bool => (int) $row['id'] !== $excludedReportId)));
    }

    /**
     * @param list<int> $matrics
     *
     * @return array<int, string>
     */
    private function names(array $matrics): array
    {
        if ($matrics === []) {
            return [];
        }
        $names = [];
        foreach ($this->connection->fetchAllAssociative('SELECT Matric, Nom, Prenom FROM kp_licence WHERE Matric IN (?)', [$matrics], [ArrayParameterType::INTEGER]) as $row) {
            $names[(int) $row['Matric']] = trim(($row['Nom'] ?? '') . ' ' . ($row['Prenom'] ?? ''));
        }

        return $names;
    }
}
