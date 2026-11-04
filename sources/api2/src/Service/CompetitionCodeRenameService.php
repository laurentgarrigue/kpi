<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * « Changer un code compétition » : renames a competition code in every table that references it
 * (REALIGNEMENT_STRUCTURES_FFCK.md, seam 2).
 *
 * Two modes: every season, or the active season only (the code of past seasons is kept).
 * The rename runs in one transaction with foreign key checks off (kp_competition.Code_ref → kp_groupe.Groupe
 * and the team/game-day keys are renamed one table at a time); the checks are restored on every path,
 * because FrankenPHP worker connections are persistent.
 */
class CompetitionCodeRenameService
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @param bool $targetExists all-seasons mode only: merge into a code that already exists
     * @throws \Exception when the source code is missing or the target code is taken
     */
    public function rename(string $sourceCode, string $targetCode, bool $allSeasons, bool $targetExists): void
    {
        $season = $allSeasons ? null : $this->activeSeason();

        if ($season === null) {
            $this->assertCanRenameInAllSeasons($sourceCode, $targetCode, $targetExists);
        } else {
            $this->assertCanRenameInSeason($sourceCode, $targetCode, $season);
        }

        $this->connection->beginTransaction();
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->renameReferences($sourceCode, $targetCode, $season);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function activeSeason(): string
    {
        return (string) $this->connection->fetchOne("SELECT Code FROM kp_saison WHERE Etat = 'A' LIMIT 1");
    }

    private function assertCanRenameInAllSeasons(string $sourceCode, string $targetCode, bool $targetExists): void
    {
        if ($this->competitionCount($sourceCode) < 1) {
            throw new \Exception('Source code not found');
        }
        if (!$targetExists && $this->competitionCount($targetCode) >= 1) {
            throw new \Exception('Target code already exists');
        }
    }

    private function assertCanRenameInSeason(string $sourceCode, string $targetCode, string $season): void
    {
        if ($this->competitionCount($sourceCode, $season) !== 1) {
            throw new \Exception('Source code not found in current season');
        }
        if ($this->competitionCount($targetCode, $season) >= 1) {
            throw new \Exception('Target code already exists in current season');
        }
    }

    private function competitionCount(string $code, ?string $season = null): int
    {
        [$seasonCondition, $seasonParams] = $this->seasonScope('Code_saison', $season);

        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM kp_competition WHERE Code = ?$seasonCondition",
            [$code, ...$seasonParams]
        );
    }

    /** A null season renames the references of every season. */
    private function renameReferences(string $sourceCode, string $targetCode, ?string $season): void
    {
        [$seasonCondition, $seasonParams] = $this->seasonScope('Code_saison', $season);
        $renameColumn = function (string $table, string $column, string $seasonColumn = 'Code_saison') use ($sourceCode, $targetCode, $season): void {
            [$condition, $params] = $this->seasonScope($seasonColumn, $season);
            $this->connection->executeStatement(
                "UPDATE $table SET $column = ? WHERE $column = ?$condition",
                [$targetCode, $sourceCode, ...$params]
            );
        };

        $renameColumn('kp_competition', 'Code');
        $renameColumn('kp_competition_equipe', 'Code_compet');
        $renameColumn('kp_journee', 'Code_competition');
        $renameColumn('kp_rc', 'Code_competition');
        $renameColumn('kp_form', 'competition_code', 'competition_season');

        // Reference (grouping) code, and the group it points to (foreign key)
        $renameColumn('kp_competition', 'Code_ref');
        $this->renameGroup($sourceCode, $targetCode, $season === null);

        // Aggregated competitions of multi-competitions (JSON array of codes)
        $this->connection->executeStatement(
            "UPDATE kp_competition SET multi_competitions = REPLACE(multi_competitions, ?, ?)
             WHERE multi_competitions LIKE ?$seasonCondition",
            ["\"$sourceCode\"", "\"$targetCode\"", "%\"$sourceCode\"%", ...$seasonParams]
        );

        $this->renameInCompetitionFilters('kp_user', 'Filtre_competition', 'Filtre_competition_sql', 'Filtre_saison', $sourceCode, $targetCode, $season);
        $this->renameInCompetitionFilters('kp_user_mandat', 'filtre_competition', null, 'filtre_saison', $sourceCode, $targetCode, $season);
    }

    /**
     * kp_groupe has no season. Every season: the group of the old code takes the new code (unless a group of the
     * new code already exists). Active season only: past seasons still point to the old group, so it is kept and
     * a group of the new code is created as its copy when missing.
     */
    private function renameGroup(string $sourceCode, string $targetCode, bool $allSeasons): void
    {
        $targetGroupMissing = 'NOT EXISTS (SELECT 1 FROM (SELECT Groupe FROM kp_groupe WHERE Groupe = ?) target)';

        if ($allSeasons) {
            $this->connection->executeStatement(
                "UPDATE kp_groupe SET Groupe = ? WHERE Groupe = ? AND $targetGroupMissing",
                [$targetCode, $sourceCode, $targetCode]
            );

            return;
        }

        $this->connection->executeStatement(
            "INSERT INTO kp_groupe (section, ordre, Code_niveau, Groupe, Libelle, Libelle_en, Calendar)
             SELECT section, ordre, Code_niveau, ?, Libelle, Libelle_en, Calendar FROM kp_groupe
             WHERE Groupe = ? AND $targetGroupMissing",
            [$targetCode, $sourceCode, $targetCode]
        );
    }

    /**
     * Competition filters of users and mandates: a pipe list (|CODE|) and, for users, its SQL twin ('CODE').
     * In active-season mode, only the filters of that season (or without a season filter) are renamed.
     */
    private function renameInCompetitionFilters(
        string $table,
        string $pipeColumn,
        ?string $sqlColumn,
        string $seasonColumn,
        string $sourceCode,
        string $targetCode,
        ?string $season
    ): void {
        $assignments = ["$pipeColumn = REPLACE($pipeColumn, ?, ?)"];
        $matches = ["$pipeColumn LIKE ?"];
        $assignmentParams = ["|$sourceCode|", "|$targetCode|"];
        $matchParams = ["%|$sourceCode|%"];

        if ($sqlColumn !== null) {
            $assignments[] = "$sqlColumn = REPLACE($sqlColumn, ?, ?)";
            $matches[] = "$sqlColumn LIKE ?";
            $assignmentParams = [...$assignmentParams, "'$sourceCode'", "'$targetCode'"];
            $matchParams[] = "%'$sourceCode'%";
        }

        $seasonCondition = $season === null ? '' : " AND ($seasonColumn LIKE ? OR $seasonColumn = '')";
        $seasonParams = $season === null ? [] : ["%|$season|%"];

        $this->connection->executeStatement(
            'UPDATE ' . $table . ' SET ' . implode(', ', $assignments)
                . ' WHERE (' . implode(' OR ', $matches) . ')' . $seasonCondition,
            [...$assignmentParams, ...$matchParams, ...$seasonParams]
        );
    }

    /** @return array{0: string, 1: list<string>} SQL condition and its parameters */
    private function seasonScope(string $seasonColumn, ?string $season): array
    {
        return $season === null ? ['', []] : [" AND $seasonColumn = ?", [$season]];
    }
}
