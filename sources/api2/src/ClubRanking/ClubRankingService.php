<?php

namespace App\ClubRanking;

use App\ClubRanking\Calculator\ManualListCalculator;
use App\ClubRanking\Model\Adjustment;
use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use Doctrine\DBAL\Connection;

/**
 * Life cycle of a season of the national club ranking (PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 8): scale, calculation,
 * manual entries and adjustments while it is a draft, then validation, publication (frozen snapshot) and reopening.
 */
final class ClubRankingService
{
    public const DRAFT = 'draft';
    public const COMPUTED = 'computed';
    public const VALIDATED = 'validated';
    public const PUBLISHED = 'published';
    private const EDITABLE = [self::DRAFT, self::COMPUTED];

    public function __construct(
        private readonly ClubRankingRepository $repository,
        private readonly SeasonDataLoader $loader,
        private readonly ClubRankingEngine $engine,
        private readonly Connection $connection,
    ) {
    }

    /** @return array<string, mixed> */
    public function requireSeason(string $season): array
    {
        return $this->repository->season($season) ?? throw new ClubRankingError(sprintf('Saison %s absente du classement des clubs.', $season), 404);
    }

    public function createSeason(string $season, ?string $copyFrom): void
    {
        if ($this->repository->season($season) !== null) {
            throw new ClubRankingError(sprintf('La saison %s existe déjà.', $season), 409);
        }
        if ($copyFrom !== null) {
            $this->requireSeason($copyFrom);
            $this->repository->copyScale($copyFrom, $season);

            return;
        }
        $this->repository->createSeason($season);
    }

    /** CNC-01: the scale of another season replaces nothing; it only fills a season without scale. */
    public function copyScale(string $from, string $to): void
    {
        $this->requireEditable($to);
        $this->requireSeason($from);
        if ($this->repository->hasScale($to)) {
            throw new ClubRankingError(sprintf('La saison %s a déjà un barème.', $to), 409);
        }
        $this->repository->copyScale($from, $to);
        $this->repository->markStale($to);
    }

    /** @return list<string> configuration errors of the scale (CNC-03) */
    public function scaleErrors(string $season): array
    {
        return $this->engine->validate($this->repository->scale($season));
    }

    /**
     * Step 2 of § 8: computes every automatic and manual rule, replaces the lines and keeps the adjustments.
     *
     * @return array{lines: int, alerts: list<array<string, mixed>>}
     */
    public function compute(string $season, ?string $author, \DateTimeImmutable $now = new \DateTimeImmutable()): array
    {
        $this->requireEditable($season);
        $scale = $this->repository->scale($season);
        try {
            $result = $this->engine->compute($scale, $this->loader->load($scale), $now);
        } catch (InvalidScale $invalid) {
            throw new ClubRankingError('Barème invalide : ' . implode(' ', $invalid->errors), 422, $invalid->errors);
        }
        $alerts = array_map(static fn (Alert $alert): array => $alert->toArray(), $result->alerts);
        $this->connection->transactional(function () use ($season, $result, $alerts, $author, $now): void {
            $this->repository->replaceLines($season, $result->lines);
            $this->repository->updateSeason($season, [
                'status' => self::COMPUTED, 'stale' => 0, 'computed_at' => $now->format('Y-m-d H:i:s'), 'computed_by' => $author,
                'alerts' => json_encode($alerts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
        });

        return ['lines' => count($result->lines), 'alerts' => $alerts];
    }

    /** @return list<array<string, mixed>> the ranking of the current lines and adjustments, with the club details */
    public function standings(string $season): array
    {
        $clubs = $this->clubDetails();
        $rows = Standings::build($this->repository->lines($season), $this->repository->adjustments($season), static fn (string $code): string => $clubs[$code]['label'] ?? $code);

        return array_map(static fn (StandingRow $row): array => [
            'rank' => $row->rank, 'club' => $row->clubCode, 'label' => $row->label, 'department' => $clubs[$row->clubCode]['department'] ?? '',
            'region' => $clubs[$row->clubCode]['region'] ?? '', 'total' => $row->total, 'by_rule' => $row->byRule,
        ], $rows);
    }

    public function validate(string $season, ?string $author): void
    {
        $row = $this->requireSeason($season);
        if ($row['status'] !== self::COMPUTED || (int) $row['stale'] === 1) {
            throw new ClubRankingError('Seul un calcul à jour peut être validé : relancez le calcul.', 409);
        }
        $this->repository->updateSeason($season, ['status' => self::VALIDATED, 'validated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'validated_by' => $author]);
    }

    /** Step 5 of § 8: freezes the ranking, the lines and the scale in a new publication. */
    public function publish(string $season, ?string $author): int
    {
        $row = $this->requireSeason($season);
        if ($row['status'] !== self::VALIDATED) {
            throw new ClubRankingError('Seul un classement validé peut être publié.', 409);
        }
        $payload = [
            'season' => $season,
            'reference_date' => $row['computed_at'],
            'top_n' => (int) $row['top_n'],
            'rules' => array_map(static fn (array $rule): array => [
                'code' => $rule['article'] . '.' . $rule['position'], 'label' => $rule['label'], 'short_label' => $rule['short_label'], 'points' => (int) $rule['points'], 'note' => $rule['note'],
            ], $this->repository->ruleRows($season)),
            'standings' => $this->standings($season),
            'lines' => array_map(static fn ($line): array => (array) $line, $this->repository->lines($season)),
            'adjustments' => array_map(static fn (Adjustment $adjustment): array => (array) $adjustment, $this->repository->adjustments($season)),
        ];

        return $this->connection->transactional(function () use ($season, $payload, $author): int {
            $version = $this->repository->addPublication($season, $payload, $author);
            $this->repository->updateSeason($season, ['status' => self::PUBLISHED]);

            return $version;
        });
    }

    /** Back to draft (profile 1): the previous publications stay readable. */
    public function reopen(string $season): void
    {
        $this->requireSeason($season);
        $this->repository->updateSeason($season, ['status' => self::DRAFT, 'validated_at' => null, 'validated_by' => null]);
    }

    /** @param array<string, mixed> $params */
    public function saveRule(string $season, ?int $id, string $article, int $rank, string $label, string $shortLabel, int $points, RuleType $type, array $params, string $note): int
    {
        $this->requireEditable($season);
        if (!preg_match(Rule::ARTICLE_PATTERN, $article) || $rank < 1 || trim($label) === '' || $points < 0) {
            throw new ClubRankingError('Rubrique invalide : article 7.x, rang ≥ 1, libellé et points ≥ 0 requis.', 422);
        }
        $id = $this->repository->saveRule($season, $id, $article, $rank, trim($label), trim($shortLabel) !== '' ? trim($shortLabel) : mb_substr(trim($label), 0, 40), $points, $type, $params, trim($note));
        $this->repository->markStale($season);

        return $id;
    }

    public function deleteRule(string $season, int $id): void
    {
        $this->requireEditable($season);
        $this->repository->deleteRule($season, $id) > 0 || throw new ClubRankingError('Rubrique introuvable.', 404);
        $this->repository->markStale($season);
    }

    /** @param list<string> $entryCodes */
    public function saveCategory(string $season, ?int $id, string $code, string $label, array $entryCodes, ?string $finalCode, ?string $regularCode): int
    {
        $this->requireEditable($season);
        if (!preg_match('/^[A-Za-z0-9_]{1,20}$/', $code)) {
            throw new ClubRankingError('Code de catégorie invalide (lettres, chiffres, _).', 422);
        }
        foreach ([...$entryCodes, $finalCode, $regularCode] as $competition) {
            if ($competition !== null && !preg_match('/^[A-Za-z0-9_-]{1,12}\*?$/', $competition)) {
                throw new ClubRankingError(sprintf('Code de compétition invalide : %s.', $competition), 422);
            }
        }
        $id = $this->repository->saveCategory($season, $id, $code, trim($label) !== '' ? trim($label) : $code, $entryCodes, $finalCode, $regularCode);
        $this->repository->markStale($season);

        return $id;
    }

    public function deleteCategory(string $season, int $id): void
    {
        $this->requireEditable($season);
        $this->repository->deleteCategory($season, $id) > 0 || throw new ClubRankingError('Catégorie introuvable.', 404);
        $this->repository->markStale($season);
    }

    /** @param list<string> $excludedCompetitions */
    public function saveSettings(string $season, int $topN, string $nationalPrefixes, array $excludedCompetitions): void
    {
        $this->requireEditable($season);
        $prefixes = ClubRankingRepository::csv($nationalPrefixes);
        if ($topN < 1 || $prefixes === []) {
            throw new ClubRankingError('Nombre de clubs publiés ≥ 1 et au moins un préfixe de compétition nationale requis.', 422);
        }
        $this->repository->updateSeason($season, ['top_n' => $topN, 'national_prefixes' => implode(',', $prefixes), 'excluded_competitions' => implode(',', $excludedCompetitions)]);
        $this->repository->markStale($season);
    }

    /** § 7: a licensee (or directly a club) for a manual rule; 7.3.4 refuses a licensee already paid by 7.3.1 / 7.3.2 (CNC-20). */
    public function addEntry(string $season, string $ruleCode, ?int $matric, ?string $clubCode, string $detail, ?string $author): int
    {
        $this->requireEditable($season);
        $rule = $this->repository->scale($season)->rule($ruleCode);
        if ($rule === null || $rule->type !== RuleType::ManualList) {
            throw new ClubRankingError(sprintf('%s n\'est pas une rubrique saisie à la main.', $ruleCode), 422);
        }
        if ($matric === null && ($clubCode === null || $clubCode === '')) {
            throw new ClubRankingError('Un licencié ou un club est requis.', 422);
        }
        if ($matric !== null && in_array($matric, ManualListCalculator::excludedMatrics($rule, $this->repository->lines($season)), true)) {
            throw new ClubRankingError(sprintf('Ce licencié est déjà compté en %s : il est exclu de %s.', implode(' / ', $rule->stringList('exclude_rules')), $ruleCode), 409);
        }
        $id = $this->repository->addEntry($season, $ruleCode, $matric, $clubCode !== '' ? $clubCode : null, trim($detail), $author);
        $this->repository->markStale($season);

        return $id;
    }

    public function deleteEntry(string $season, int $id): void
    {
        $this->requireEditable($season);
        $this->repository->deleteEntry($season, $id) > 0 || throw new ClubRankingError('Saisie introuvable.', 404);
        $this->repository->markStale($season);
    }

    /** CNC-26: an adjustment always has a reason. */
    public function addAdjustment(string $season, Adjustment $adjustment): int
    {
        $this->requireEditable($season);
        if (trim($adjustment->reason) === '' || $adjustment->points === 0) {
            throw new ClubRankingError('Un ajustement demande un nombre de points non nul et un motif.', 422);
        }
        if ($this->repository->scale($season)->rule($adjustment->ruleCode) === null) {
            throw new ClubRankingError(sprintf('Rubrique %s inconnue.', $adjustment->ruleCode), 422);
        }
        if ($this->connection->fetchOne('SELECT Code FROM kp_club WHERE Code = ?', [$adjustment->clubCode]) === false) {
            throw new ClubRankingError(sprintf('Club %s inconnu.', $adjustment->clubCode), 422);
        }

        return $this->repository->addAdjustment($season, $adjustment);
    }

    public function deleteAdjustment(string $season, int $id): void
    {
        $this->requireEditable($season);
        $this->repository->deleteAdjustment($season, $id) > 0 || throw new ClubRankingError('Ajustement introuvable.', 404);
    }

    public function addEntente(string $season, int $teamNumber, string $clubCode): void
    {
        $this->requireEditable($season);
        if ($this->connection->fetchOne('SELECT Code FROM kp_club WHERE Code = ?', [$clubCode]) === false) {
            throw new ClubRankingError(sprintf('Club %s inconnu.', $clubCode), 422);
        }
        $this->repository->addEntente($season, $teamNumber, $clubCode);
        $this->repository->markStale($season);
    }

    public function deleteEntente(string $season, int $teamNumber, string $clubCode): void
    {
        $this->requireEditable($season);
        $this->repository->deleteEntente($season, $teamNumber, $clubCode);
        $this->repository->markStale($season);
    }

    /** N-5: a free-text name of a gameday is this licensee, for this season and the next ones. */
    public function saveAlias(string $season, string $name, int $matric): void
    {
        $this->requireEditable($season);
        if (NameNormalizer::normalize($name) === '' || $this->connection->fetchOne('SELECT Matric FROM kp_licence WHERE Matric = ?', [$matric]) === false) {
            throw new ClubRankingError('Nom vide ou licencié inconnu.', 422);
        }
        $this->repository->saveAlias($name, $matric);
        $this->repository->markStale($season);
    }

    /** @return array<string, mixed> */
    private function requireEditable(string $season): array
    {
        $row = $this->requireSeason($season);
        if (!in_array($row['status'], self::EDITABLE, true)) {
            throw new ClubRankingError('Saison validée ou publiée : repassez-la en brouillon pour la modifier.', 409);
        }

        return $row;
    }

    /** @return array<string, array{label: string, department: string, region: string}> */
    private function clubDetails(): array
    {
        $clubs = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT c.Code, c.Libelle, cd.Libelle AS cd_label, cr.Libelle AS cr_label FROM kp_club c
             LEFT JOIN kp_cd cd ON cd.Code = c.Code_comite_dep LEFT JOIN kp_cr cr ON cr.Code = cd.Code_comite_reg',
        ) as $row) {
            $clubs[(string) $row['Code']] = ['label' => (string) $row['Libelle'], 'department' => (string) ($row['cd_label'] ?? ''), 'region' => (string) ($row['cr_label'] ?? '')];
        }

        return $clubs;
    }
}
