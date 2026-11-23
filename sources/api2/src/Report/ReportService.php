<?php

namespace App\Report;

use App\Entity\User;
use App\Service\NotificationService;
use Psr\Clock\ClockInterface;

/**
 * Cycle de vie des rapports officiels (FEATURE_DESIGNATIONS.md D12 à D18, D28, D29 ; RAP-01 à RAP-07, RAP-10).
 *
 * - Rédiger : un officiel du périmètre (D15) ou un profil 1–2 ; un officiel peut saisir pour un arbitre (D17).
 * - Brouillon : lu et modifié par son rédacteur, son auteur et les profils 1–2 (RAP-05).
 * - Soumission : réponses figées (avec les données KPI et les destinataires), PDF envoyé aux destinataires (RAP-06).
 * - Réouverture : profils 1–2 seulement, avec un motif (RAP-07) ; les soumissions précédentes restent.
 */
final class ReportService
{
    private const MANAGER_MAX_PROFILE = 2;

    public function __construct(
        private readonly ReportRepository $reports,
        private readonly ReportPerimeters $perimeters,
        private readonly ReportPeople $people,
        private readonly ReportPdf $pdf,
        private readonly ReportKpiData $kpi,
        private readonly NotificationService $notifications,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $answers
     *
     * @throws ReportRejected
     */
    public function create(User $user, string $type, ReportScope $scope, string $scopeId, string $season, ?int $author, array $answers): Report
    {
        if (!ReportTypeCatalog::has($type)) {
            throw new ReportRejected(ReportRejected::TYPE_UNKNOWN, 422);
        }
        $definition = ReportTypeCatalog::current($type);
        if (!$definition->allowsScope($scope)) {
            throw new ReportRejected(ReportRejected::SCOPE_NOT_ALLOWED, 422);
        }
        $perimeter = $this->perimeter($scope, $scopeId, $season);
        if ($definition->gamedayOnlyOutsideEvent && $scope === ReportScope::Journee && ($perimeter->gamedayEvents[(int) $scopeId] ?? []) !== []) {
            throw new ReportRejected(ReportRejected::GAMEDAY_IN_EVENT, 422);
        }
        if (!$this->canWrite($user, $perimeter, $definition)) {
            throw new ReportRejected(ReportRejected::FORBIDDEN, 403);
        }
        if ($definition->uniquePerScope && $this->reports->exists($type, $scope, $perimeter->scopeId, $perimeter->season)) {
            throw new ReportRejected(ReportRejected::ALREADY_EXISTS, 409);
        }
        $author ??= self::matricOf($user);
        if ($author !== null && $author !== self::matricOf($user) && !$this->people->isOfficial($author, $perimeter)) {
            throw new ReportRejected(ReportRejected::AUTHOR_NOT_OFFICIAL, 422);
        }
        $clean = $this->answers($definition, self::seeded($definition, $answers, $this->kpi->of($perimeter)), false);

        $id = $this->reports->insert($definition, $scope, $scopeId, $perimeter->season, $author, $user->getUserIdentifier(), $clean);

        return $this->reports->find($id) ?? throw new ReportRejected(ReportRejected::NOT_FOUND, 404);
    }

    /**
     * @param array<string, mixed> $answers
     *
     * @throws ReportRejected
     */
    public function update(User $user, int $id, array $answers): Report
    {
        $report = $this->editableDraft($user, $id);
        $this->reports->saveAnswers($id, $this->answers($report->definition(), $answers, false));

        return $this->reports->find($id) ?? throw new ReportRejected(ReportRejected::NOT_FOUND, 404);
    }

    /** @throws ReportRejected */
    public function submit(User $user, int $id): Report
    {
        $report = $this->editableDraft($user, $id);
        $definition = $report->definition();
        $this->answers($definition, $report->answers, true);
        $perimeter = $this->perimeter($report->scope, $report->scopeId, $report->season);
        $now = $this->clock->now();
        $late = $definition->deadlineDays !== null && $perimeter->endDate !== null
            && $now > (new \DateTimeImmutable($perimeter->endDate))->modify(sprintf('+%d days 23:59:59', $definition->deadlineDays));
        $recipients = $this->people->recipients($definition->recipients, $perimeter, $report->authorMatric);
        $kpiData = $this->kpi->of($perimeter, $report->id);

        $number = $this->reports->submit($report, $now->format('Y-m-d H:i:s'), $late, $kpiData, $recipients, $user->getUserIdentifier());
        $submitted = $this->reports->find($id) ?? throw new ReportRejected(ReportRejected::NOT_FOUND, 404);
        $filename = self::filename($submitted, $number);
        $pdf = $this->pdf->render($definition, $submitted->season, 'fr', $submitted->answers, $kpiData, $filename);
        foreach ($this->reports->emails($recipients) as $email) {
            $this->notifications->sendReportSubmitted($email, $definition->labelFr, $perimeter->label, $id, $pdf, $filename);
        }

        return $submitted;
    }

    /** @throws ReportRejected */
    public function reopen(User $user, int $id, string $reason): Report
    {
        $report = $this->reports->find($id) ?? throw new ReportRejected(ReportRejected::NOT_FOUND, 404);
        if ($user->getEffectiveNiveau() > self::MANAGER_MAX_PROFILE) {
            throw new ReportRejected(ReportRejected::FORBIDDEN, 403);
        }
        if ($report->state !== Report::SUBMITTED) {
            throw new ReportRejected(ReportRejected::NOT_SUBMITTED, 409);
        }
        if (trim($reason) === '') {
            throw new ReportRejected(ReportRejected::REASON_REQUIRED, 422);
        }
        $this->reports->reopen($id);

        return $this->reports->find($id) ?? throw new ReportRejected(ReportRejected::NOT_FOUND, 404);
    }

    /** @throws ReportRejected */
    public function get(User $user, int $id): Report
    {
        $report = $this->reports->find($id) ?? throw new ReportRejected(ReportRejected::NOT_FOUND, 404);
        if (!$this->canRead($user, $report)) {
            throw new ReportRejected(ReportRejected::NOT_FOUND, 404);
        }

        return $report;
    }

    /**
     * @param array{type?: string, state?: string, scopeType?: string, scopeId?: string} $filters
     *
     * @return list<Report>
     */
    public function list(User $user, string $season, array $filters): array
    {
        return array_values(array_filter($this->reports->search($season, $filters), fn (Report $report): bool => $this->canRead($user, $report)));
    }

    /**
     * Données KPI du périmètre, pour pré-remplir un nouveau rapport (RAP-06 : figées à la soumission).
     *
     * @return array<string, mixed>
     *
     * @throws ReportRejected
     */
    public function prefill(User $user, ReportScope $scope, string $scopeId, string $season): array
    {
        $perimeter = $this->perimeter($scope, $scopeId, $season);
        if (!$this->canWrite($user, $perimeter, null) && !$this->canReadPerimeterReport($user, $scope, $perimeter)) {
            throw new ReportRejected(ReportRejected::FORBIDDEN, 403);
        }

        return $this->kpi->of($perimeter) + ['season' => $perimeter->season];
    }

    /**
     * PDF d'une soumission (la dernière par défaut) ou, pour un brouillon, de l'état courant.
     *
     * @return array{content: string, filename: string}
     *
     * @throws ReportRejected
     */
    public function pdf(User $user, int $id, string $lang, ?int $number): array
    {
        $report = $this->get($user, $id);
        $submissions = $this->reports->submissions($id);
        $submission = null;
        foreach ($submissions as $candidate) {
            if ($number === null || $candidate['number'] === $number) {
                $submission = $candidate;
            }
        }
        if ($number !== null && $submission === null) {
            throw new ReportRejected(ReportRejected::NOT_FOUND, 404);
        }
        if ($submission !== null && ($number !== null || $report->state === Report::SUBMITTED)) {
            $filename = self::filename($report, $submission['number']);

            return ['content' => $this->pdf->render(ReportTypeCatalog::version($report->type, $submission['version']), $report->season, $lang, $submission['answers'], $submission['kpiData'], $filename), 'filename' => $filename];
        }
        $perimeter = $this->perimeter($report->scope, $report->scopeId, $report->season);
        $filename = self::filename($report, 0);

        return ['content' => $this->pdf->render($report->definition(), $report->season, $lang, $report->answers, $this->kpi->of($perimeter, $report->id), $filename, $lang === 'en' ? 'Draft' : 'Brouillon'), 'filename' => $filename];
    }

    /** @return list<array{number: int, version: int, submittedAt: string, submittedBy: string}> */
    public function submissions(User $user, int $id): array
    {
        $this->get($user, $id);

        return array_map(static fn (array $s): array => ['number' => $s['number'], 'version' => $s['version'], 'submittedAt' => $s['submittedAt'], 'submittedBy' => $s['submittedBy']], $this->reports->submissions($id));
    }

    public function canRead(User $user, Report $report): bool
    {
        if ($user->getEffectiveNiveau() <= self::MANAGER_MAX_PROFILE || $report->enteredBy === $user->getUserIdentifier()) {
            return true;
        }
        $matric = self::matricOf($user);
        if ($matric === null) {
            return false;
        }
        if ($report->authorMatric === $matric) {
            return true;
        }
        if ($report->state !== Report::SUBMITTED) {
            return false;
        }
        $submissions = $this->reports->submissions($report->id);
        $last = end($submissions);

        return $last !== false && in_array($matric, $last['recipients'], true);
    }

    /** Rédacteur possible : profils 1–2, sinon officiel du périmètre (D15) ou son délégué CNA selon le type (BIL-02). */
    private function canWrite(User $user, ReportPerimeter $perimeter, ?ReportTypeDefinition $definition): bool
    {
        if ($user->getEffectiveNiveau() <= self::MANAGER_MAX_PROFILE) {
            return true;
        }
        $matric = self::matricOf($user);
        if ($matric === null) {
            return false;
        }

        return $definition?->writers === ReportTypeDefinition::WRITERS_DELEGATE
            ? $this->people->isDelegate($matric, $perimeter)
            : $this->people->isOfficial($matric, $perimeter);
    }

    /** Les données KPI d'un périmètre sont lisibles de qui y lit déjà un rapport (page d'un rapport, BIL-03). */
    private function canReadPerimeterReport(User $user, ReportScope $scope, ReportPerimeter $perimeter): bool
    {
        foreach ($this->reports->search($perimeter->season, ['scopeType' => $scope->value, 'scopeId' => $perimeter->scopeId]) as $report) {
            if ($this->canRead($user, $report)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valeurs pré-remplies depuis KPI quand le rédacteur ne les donne pas (BIL-03) : lignes d'un tableau
     * (`rowsFrom`), champs `prefill` (« matches.planned »).
     *
     * @param array<string, mixed> $answers
     * @param array<string, mixed> $kpi
     *
     * @return array<string, mixed>
     */
    private static function seeded(ReportTypeDefinition $definition, array $answers, array $kpi): array
    {
        foreach ($definition->fields() as $key => $field) {
            if (array_key_exists($key, $answers)) {
                continue;
            }
            $path = $field->rowsFrom ?? $field->prefill;
            if ($path === null) {
                continue;
            }
            $value = $kpi;
            foreach (explode('.', $path) as $segment) {
                $value = is_array($value) ? ($value[$segment] ?? null) : null;
            }
            if ($value !== null) {
                $answers[$key] = $value;
            }
        }

        return $answers;
    }

    /** @throws ReportRejected */
    private function editableDraft(User $user, int $id): Report
    {
        $report = $this->get($user, $id);
        if ($report->state !== Report::DRAFT) {
            throw new ReportRejected(ReportRejected::NOT_DRAFT, 409);
        }
        $matric = self::matricOf($user);
        if ($user->getEffectiveNiveau() > self::MANAGER_MAX_PROFILE && $report->enteredBy !== $user->getUserIdentifier() && ($matric === null || $report->authorMatric !== $matric)) {
            throw new ReportRejected(ReportRejected::FORBIDDEN, 403);
        }

        return $report;
    }

    /** @throws ReportRejected */
    private function perimeter(ReportScope $scope, string $scopeId, string $season): ReportPerimeter
    {
        return $this->perimeters->resolve($scope, $scopeId, $season) ?? throw new ReportRejected(ReportRejected::NOT_FOUND, 404);
    }

    /**
     * @param array<string, mixed> $answers
     *
     * @return array<string, mixed>
     *
     * @throws ReportRejected
     */
    private function answers(ReportTypeDefinition $definition, array $answers, bool $forSubmit): array
    {
        $result = ReportAnswers::normalize($definition, $answers, $forSubmit);
        if ($result['errors'] !== []) {
            throw new ReportRejected(ReportRejected::INVALID_ANSWERS, 422, $result['errors']);
        }

        return $result['answers'];
    }

    private static function filename(Report $report, int $number): string
    {
        return sprintf('rapport_%s_%d%s.pdf', strtolower($report->type), $report->id, $number > 0 ? '_' . $number : '');
    }

    private static function matricOf(User $user): ?int
    {
        $code = $user->getUserIdentifier();

        return ctype_digit($code) ? (int) $code : null;
    }
}
