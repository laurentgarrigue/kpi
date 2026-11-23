<?php

namespace App\Report;

use Doctrine\DBAL\Connection;

/** Accès à kp_report et kp_report_submission. */
final class ReportRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function find(int $id): ?Report
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_report WHERE id = ?', [$id]);

        return $row === false ? null : self::hydrate($row);
    }

    /**
     * @param array{type?: string, state?: string, scopeType?: string, scopeId?: string} $filters
     *
     * @return list<Report>
     */
    public function search(string $season, array $filters): array
    {
        $qb = $this->connection->createQueryBuilder()->select('*')->from('kp_report')->where('saison = :season')->setParameter('season', $season)
            ->orderBy('updated_at', 'DESC')->addOrderBy('id', 'DESC');
        foreach (['type' => 'type', 'state' => 'etat', 'scopeType' => 'perimetre_type', 'scopeId' => 'perimetre_id'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $qb->andWhere("$column = :$filter")->setParameter($filter, $filters[$filter]);
            }
        }

        return array_map(self::hydrate(...), $qb->executeQuery()->fetchAllAssociative());
    }

    public function exists(string $type, ReportScope $scope, string $scopeId, string $season): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM kp_report WHERE type = ? AND perimetre_type = ? AND perimetre_id = ? AND saison = ?',
            [$type, $scope->value, $scopeId, $season],
        );
    }

    /** @param array<string, mixed> $answers */
    public function insert(ReportTypeDefinition $definition, ReportScope $scope, string $scopeId, string $season, ?int $author, string $enteredBy, array $answers): int
    {
        $this->connection->insert('kp_report', [
            'type' => $definition->code,
            'version_modele' => $definition->version,
            'perimetre_type' => $scope->value,
            'perimetre_id' => $scopeId,
            'saison' => $season,
            'auteur_matric' => $author,
            'saisi_par' => $enteredBy,
            'reponses' => json_encode($answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $answers */
    public function saveAnswers(int $id, array $answers): void
    {
        $this->connection->update('kp_report', ['reponses' => json_encode($answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)], ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $kpiData
     * @param list<int>            $recipients
     */
    public function submit(Report $report, string $submittedAt, bool $late, array $kpiData, array $recipients, string $by): int
    {
        $number = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(numero), 0) + 1 FROM kp_report_submission WHERE report_id = ?', [$report->id]);
        $this->connection->insert('kp_report_submission', [
            'report_id' => $report->id,
            'numero' => $number,
            'version_modele' => $report->version,
            'reponses' => json_encode($report->answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'donnees_kpi' => json_encode($kpiData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'destinataires' => json_encode($recipients, JSON_THROW_ON_ERROR),
            'submitted_at' => $submittedAt,
            'submitted_by' => $by,
        ]);
        $this->connection->update('kp_report', ['etat' => Report::SUBMITTED, 'soumis_le' => $submittedAt, 'hors_delai' => (int) $late], ['id' => $report->id]);

        return $number;
    }

    public function reopen(int $id): void
    {
        $this->connection->update('kp_report', ['etat' => Report::DRAFT], ['id' => $id]);
    }

    /** @return list<array{number: int, version: int, answers: array<string, mixed>, kpiData: array<string, mixed>, recipients: list<int>, submittedAt: string, submittedBy: string}> */
    public function submissions(int $reportId): array
    {
        return array_map(static fn (array $row): array => [
            'number' => (int) $row['numero'],
            'version' => (int) $row['version_modele'],
            'answers' => json_decode($row['reponses'], true, 512, JSON_THROW_ON_ERROR),
            'kpiData' => json_decode($row['donnees_kpi'], true, 512, JSON_THROW_ON_ERROR),
            'recipients' => json_decode($row['destinataires'], true, 512, JSON_THROW_ON_ERROR),
            'submittedAt' => $row['submitted_at'],
            'submittedBy' => $row['submitted_by'],
        ], $this->connection->fetchAllAssociative('SELECT * FROM kp_report_submission WHERE report_id = ? ORDER BY numero', [$reportId]));
    }

    /**
     * @param list<int> $matrics
     *
     * @return array<int, string> licence => email des comptes KPI
     */
    public function emails(array $matrics): array
    {
        if ($matrics === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            sprintf("SELECT Code, Mail FROM kp_user WHERE Code IN (%s) AND Mail <> ''", implode(',', array_fill(0, count($matrics), '?'))),
            array_map('strval', $matrics),
        );

        return array_column($rows, 'Mail', 'Code');
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): Report
    {
        return new Report(
            (int) $row['id'],
            $row['type'],
            (int) $row['version_modele'],
            ReportScope::from($row['perimetre_type']),
            (string) $row['perimetre_id'],
            $row['saison'],
            $row['auteur_matric'] === null ? null : (int) $row['auteur_matric'],
            $row['saisi_par'],
            $row['etat'],
            $row['soumis_le'],
            (bool) $row['hors_delai'],
            json_decode($row['reponses'] ?: '{}', true, 512, JSON_THROW_ON_ERROR) ?: [],
        );
    }
}
