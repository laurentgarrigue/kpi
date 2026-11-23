<?php

namespace App\Controller;

use App\Entity\User;
use App\Report\Report;
use App\Report\ReportPdf;
use App\Report\ReportRejected;
use App\Report\ReportScope;
use App\Report\ReportScopeSearch;
use App\Report\ReportService;
use App\Report\ReportTypeCatalog;
use App\Report\ReportTypeDefinition;
use App\Trait\AdminLoggableTrait;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Rapports officiels (FEATURE_DESIGNATIONS.md § 5, § 8.3 ; ticket designations/09). Les droits dépendent du rapport
 * (rédacteur, auteur, destinataires, officiels du périmètre) : ils sont vérifiés par ReportService.
 */
#[Route('/admin/reports')]
#[OA\Tag(name: 'Admin - Rapports officiels')]
final class AdminReportsController extends AbstractController
{
    use AdminLoggableTrait;

    private const SEASON_PATTERN = '/^\d{4}$/';
    private const SCOPE_ID_PATTERN = '/^[A-Za-z0-9_-]{1,20}$/';
    private const LANGUAGES = ['fr', 'en'];
    /** Le PDF vierge sert aux officiels qui le transmettent à un arbitre (D17) : jusqu'au délégué fédéral. */
    private const BLANK_MAX_PROFILE = 5;

    public function __construct(
        private readonly ReportService $service,
        private readonly ReportPdf $pdf,
        private readonly Connection $connection,
        private readonly ReportScopeSearch $scopeSearch,
    ) {
    }

    #[Route('/scopes', name: 'admin_reports_scopes', methods: ['GET'])]
    #[OA\Get(path: '/admin/reports/scopes', summary: 'Chercher un événement, une compétition, une journée ou un match de la saison (?q=, ?types=)')]
    public function scopes(Request $request): JsonResponse
    {
        $season = (string) $request->query->get('season', '');
        $types = array_values(array_filter(array_map(
            static fn (string $type): ?ReportScope => ReportScope::tryFrom(trim($type)),
            explode(',', (string) $request->query->get('types', '')),
        )));
        if (!preg_match(self::SEASON_PATTERN, $season)) {
            return $this->error('invalid', Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['items' => $this->scopeSearch->search($season, (string) $request->query->get('q', ''), $types)]);
    }

    #[Route('/types', name: 'admin_reports_types', methods: ['GET'])]
    #[OA\Get(path: '/admin/reports/types', summary: 'Catalogue des types de rapport, toutes versions (app4 rend les formulaires depuis lui)')]
    public function types(): JsonResponse
    {
        $types = [];
        foreach (ReportTypeCatalog::all() as $code => $versions) {
            $types[$code] = [
                'current' => ReportTypeCatalog::current($code)->version,
                'versions' => array_map(static fn (ReportTypeDefinition $d): array => $d->toArray(), $versions),
            ];
        }

        return $this->json($types);
    }

    #[Route('', name: 'admin_reports_list', methods: ['GET'])]
    #[OA\Get(path: '/admin/reports', summary: "Rapports lisibles par l'utilisateur, filtrables par type, état et périmètre")]
    public function list(Request $request): JsonResponse
    {
        $season = (string) $request->query->get('season', '');
        if (!preg_match(self::SEASON_PATTERN, $season)) {
            return $this->error('invalid', Response::HTTP_BAD_REQUEST);
        }
        $filters = array_filter([
            'type' => $request->query->get('type'),
            'state' => $request->query->get('state'),
            'scopeType' => $request->query->get('scopeType'),
            'scopeId' => $request->query->get('scopeId'),
        ], static fn (mixed $value): bool => is_string($value) && $value !== '');
        $items = array_map(self::summary(...), $this->service->list($this->currentUser(), $season, $filters));

        return $this->json(['items' => $items, 'total' => count($items)]);
    }

    #[Route('/prefill', name: 'admin_reports_prefill', methods: ['GET'])]
    #[OA\Get(path: '/admin/reports/prefill', summary: 'Données KPI du périmètre, pour un nouveau rapport')]
    public function prefill(Request $request): JsonResponse
    {
        $scope = $this->scope($request->query->all());
        if ($scope === null) {
            return $this->error('invalid', Response::HTTP_BAD_REQUEST);
        }

        return $this->handle(fn (): array => $this->service->prefill($this->currentUser(), ...$scope));
    }

    #[Route('', name: 'admin_reports_create', methods: ['POST'])]
    #[OA\Post(path: '/admin/reports', summary: "Créer un brouillon (un officiel peut saisir pour un arbitre : authorMatric)")]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $scope = is_array($data) ? $this->scope($data) : null;
        $author = $data['authorMatric'] ?? null;
        if ($scope === null || !is_string($data['type'] ?? null) || ($author !== null && (!is_int($author) || $author <= 0)) || !is_array($data['answers'] ?? [])) {
            return $this->error('invalid', Response::HTTP_BAD_REQUEST);
        }

        return $this->handle(function () use ($data, $scope, $author): array {
            $report = $this->service->create($this->currentUser(), $data['type'], ...$scope, author: $author, answers: $data['answers'] ?? []);
            $this->journal('Ajout rapport', $report);

            return $this->detail($report);
        }, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_reports_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(path: '/admin/reports/{id}', summary: 'Un rapport, la définition de sa version et ses soumissions')]
    public function get(int $id): JsonResponse
    {
        return $this->handle(fn (): array => $this->detail($this->service->get($this->currentUser(), $id)));
    }

    #[Route('/{id}', name: 'admin_reports_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(path: '/admin/reports/{id}', summary: 'Enregistrer le brouillon')]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_array($data['answers'] ?? null)) {
            return $this->error('invalid', Response::HTTP_BAD_REQUEST);
        }

        return $this->handle(fn (): array => $this->detail($this->service->update($this->currentUser(), $id, $data['answers'])));
    }

    #[Route('/{id}/submit', name: 'admin_reports_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(path: '/admin/reports/{id}/submit', summary: 'Soumettre : figé, PDF envoyé aux destinataires')]
    public function submit(int $id): JsonResponse
    {
        return $this->handle(function () use ($id): array {
            $report = $this->service->submit($this->currentUser(), $id);
            $this->journal('Soumission rapport', $report);

            return $this->detail($report);
        });
    }

    #[Route('/{id}/reopen', name: 'admin_reports_reopen', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(path: '/admin/reports/{id}/reopen', summary: 'Rouvrir un rapport soumis (profils 1–2, motif obligatoire)')]
    public function reopen(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = is_array($data) && is_string($data['reason'] ?? null) ? mb_substr($data['reason'], 0, 500) : '';

        return $this->handle(function () use ($id, $reason): array {
            $report = $this->service->reopen($this->currentUser(), $id, $reason);
            $this->journal('Réouverture rapport', $report, $reason);

            return $this->detail($report);
        });
    }

    #[Route('/{id}/pdf', name: 'admin_reports_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(path: '/admin/reports/{id}/pdf', summary: "PDF d'une soumission (la dernière par défaut, ?number=) ou du brouillon")]
    public function pdf(int $id, Request $request): Response
    {
        $lang = (string) $request->query->get('lang', 'fr');
        $number = $request->query->has('number') ? $request->query->getInt('number') : null;
        if (!in_array($lang, self::LANGUAGES, true)) {
            return $this->error('invalid', Response::HTTP_BAD_REQUEST);
        }
        try {
            $pdf = $this->service->pdf($this->currentUser(), $id, $lang, $number);
        } catch (ReportRejected $rejected) {
            return $this->error($rejected->error, $rejected->status);
        }

        return self::pdfResponse($pdf['content'], $pdf['filename']);
    }

    #[Route('/blank/{type}', name: 'admin_reports_blank', methods: ['GET'])]
    #[OA\Get(path: '/admin/reports/blank/{type}', summary: "PDF vierge d'un type de rapport, à remplir à la main (D17, D29)")]
    public function blank(string $type, Request $request): Response
    {
        $lang = (string) $request->query->get('lang', 'fr');
        $season = (string) $request->query->get('season', '');
        if (!ReportTypeCatalog::has($type) || !in_array($lang, self::LANGUAGES, true) || !preg_match(self::SEASON_PATTERN, $season)) {
            return $this->error('invalid', Response::HTTP_BAD_REQUEST);
        }
        if ($this->currentUser()->getEffectiveNiveau() > self::BLANK_MAX_PROFILE) {
            return $this->error(ReportRejected::FORBIDDEN, Response::HTTP_FORBIDDEN);
        }
        $definition = ReportTypeCatalog::current($type);

        return self::pdfResponse($this->pdf->blank($definition, $season, $lang), sprintf('%s_vierge.pdf', strtolower($type)));
    }

    /**
     * @param array<mixed> $data
     *
     * @return array{scope: ReportScope, scopeId: string, season: string}|null
     */
    private function scope(array $data): ?array
    {
        $scope = is_string($data['scopeType'] ?? null) ? ReportScope::tryFrom($data['scopeType']) : null;
        $scopeId = $data['scopeId'] ?? null;
        $season = $data['season'] ?? null;
        if ($scope === null || !is_string($scopeId) || !preg_match(self::SCOPE_ID_PATTERN, $scopeId) || !is_string($season) || !preg_match(self::SEASON_PATTERN, $season)) {
            return null;
        }

        return ['scope' => $scope, 'scopeId' => $scopeId, 'season' => $season];
    }

    /** @return array<string, mixed> */
    private static function summary(Report $report): array
    {
        return [
            'id' => $report->id,
            'type' => $report->type,
            'version' => $report->version,
            'scopeType' => $report->scope->value,
            'scopeId' => $report->scopeId,
            'season' => $report->season,
            'authorMatric' => $report->authorMatric,
            'enteredBy' => $report->enteredBy,
            'state' => $report->state,
            'submittedAt' => $report->submittedAt,
            'late' => $report->late,
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Report $report): array
    {
        $user = $this->currentUser();
        $manager = $user->getEffectiveNiveau() <= 2;

        return self::summary($report) + [
            'answers' => $report->answers,
            'definition' => $report->definition()->toArray(),
            'submissions' => $this->service->submissions($user, $report->id),
            'canEdit' => $report->state === Report::DRAFT && ($manager || $report->enteredBy === $user->getUserIdentifier() || (string) $report->authorMatric === $user->getUserIdentifier()),
            'canReopen' => $report->state === Report::SUBMITTED && $manager,
        ];
    }

    private function journal(string $action, Report $report, string $reason = ''): void
    {
        $this->logActionForSeason($action, $report->season, trim(sprintf('%s n° %d (%s %s) %s', $report->type, $report->id, $report->scope->value, $report->scopeId, $reason)));
    }

    /** @param callable(): array<mixed> $operation */
    private function handle(callable $operation, int $status = Response::HTTP_OK): JsonResponse
    {
        try {
            return $this->json($operation(), $status);
        } catch (ReportRejected $rejected) {
            return $this->json(array_filter(['error' => $rejected->error, 'fields' => $rejected->fieldErrors]), $rejected->status);
        }
    }

    private static function pdfResponse(string $content, string $filename): Response
    {
        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function error(string $code, int $status): JsonResponse
    {
        return $this->json(['error' => $code], $status);
    }
}
