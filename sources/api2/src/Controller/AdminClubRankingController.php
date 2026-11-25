<?php

namespace App\Controller;

use App\ClubRanking\ClubRankingError;
use App\ClubRanking\ClubRankingRepository;
use App\ClubRanking\ClubRankingService;
use App\ClubRanking\Model\Adjustment;
use App\ClubRanking\Model\RuleType;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * National club ranking (PAGE_CLASSEMENT_NATIONAL_CLUBS.md), profile 1 only (Q-1, CNC-28b): scale of each season,
 * calculation, manual entries, adjustments, validation and publication.
 */
#[Route('/admin/club-ranking')]
#[IsGranted('ROLE_SUPER_ADMIN')]
#[OA\Tag(name: 'Admin - Classement national des clubs')]
final class AdminClubRankingController extends AbstractController
{
    private const SEASON = '\d{4}';

    public function __construct(
        private readonly ClubRankingService $service,
        private readonly ClubRankingRepository $repository,
        private readonly Connection $connection,
    ) {
    }

    #[Route('/seasons', name: 'admin_club_ranking_seasons', methods: ['GET'])]
    #[OA\Get(path: '/admin/club-ranking/seasons', summary: 'Seasons of the club ranking')]
    public function seasons(): JsonResponse
    {
        return $this->json(['items' => $this->repository->seasons()]);
    }

    #[Route('/seasons', name: 'admin_club_ranking_season_create', methods: ['POST'])]
    #[OA\Post(path: '/admin/club-ranking/seasons', summary: 'Create a season, optionally with the scale of another one')]
    public function createSeason(Request $request): JsonResponse
    {
        return $this->handle(function () use ($request): JsonResponse {
            $body = self::body($request);
            $season = self::seasonOf($body['season'] ?? null);
            $copyFrom = isset($body['copy_from']) && $body['copy_from'] !== '' ? self::seasonOf($body['copy_from']) : null;
            $this->service->createSeason($season, $copyFrom);

            return $this->json(['season' => $season], Response::HTTP_CREATED);
        });
    }

    #[Route('/{season}', name: 'admin_club_ranking_overview', methods: ['GET'], requirements: ['season' => self::SEASON])]
    #[OA\Get(path: '/admin/club-ranking/{season}', summary: 'Everything of a season: settings, scale, ranking, alerts, entries')]
    public function overview(string $season): JsonResponse
    {
        return $this->handle(function () use ($season): JsonResponse {
            $row = $this->service->requireSeason($season);

            return $this->json([
                'season' => [
                    'season' => $season, 'status' => $row['status'], 'stale' => (bool) $row['stale'], 'top_n' => (int) $row['top_n'],
                    'national_prefixes' => $row['national_prefixes'], 'excluded_competitions' => $row['excluded_competitions'],
                    'computed_at' => $row['computed_at'], 'computed_by' => $row['computed_by'],
                    'validated_at' => $row['validated_at'], 'validated_by' => $row['validated_by'],
                ],
                'types' => array_map(static fn (RuleType $type): string => $type->value, RuleType::cases()),
                'rules' => array_map(static fn (array $rule): array => [
                    'id' => (int) $rule['id'], 'code' => $rule['article'] . '.' . $rule['position'], 'article' => $rule['article'], 'rank' => (int) $rule['position'],
                    'label' => $rule['label'], 'short_label' => $rule['short_label'], 'points' => (int) $rule['points'], 'type' => $rule['calc_type'],
                    'params' => json_decode((string) $rule['params'], true) ?: new \stdClass(), 'note' => $rule['note'],
                ], $this->repository->ruleRows($season)),
                'categories' => array_map(static fn (array $category): array => [
                    'id' => (int) $category['id'], 'code' => $category['code'], 'label' => $category['label'],
                    'entry_codes' => ClubRankingRepository::csv((string) $category['entry_codes']),
                    'final_code' => $category['final_code'], 'regular_code' => $category['regular_code'],
                ], $this->repository->categoryRows($season)),
                'scale_errors' => $this->service->scaleErrors($season),
                'alerts' => json_decode((string) ($row['alerts'] ?? '[]'), true) ?: [],
                'standings' => $this->service->standings($season),
                'entries' => array_map(static fn (array $entry): array => [
                    'id' => (int) $entry['id'], 'rule' => $entry['rule_code'], 'matric' => $entry['matric'] === null ? null : (int) $entry['matric'],
                    'name' => trim(($entry['Nom'] ?? '') . ' ' . ($entry['Prenom'] ?? '')), 'club' => $entry['club_code'] ?: $entry['Numero_club'],
                    'club_label' => $entry['club_label'], 'detail' => $entry['detail'], 'created_by' => $entry['created_by'], 'created_at' => $entry['created_at'],
                ], $this->repository->entries($season)),
                'adjustments' => array_map(static fn (Adjustment $adjustment): array => [
                    'id' => $adjustment->id, 'rule' => $adjustment->ruleCode, 'club' => $adjustment->clubCode, 'points' => $adjustment->points,
                    'reason' => $adjustment->reason, 'created_by' => $adjustment->author, 'created_at' => $adjustment->createdAt,
                ], $this->repository->adjustments($season)),
                'ententes' => $this->ententes($season),
                'publications' => $this->repository->publications($season),
                'competitions' => $this->connection->fetchFirstColumn('SELECT Code FROM kp_competition WHERE Code_saison = ? ORDER BY Code', [$season]),
            ]);
        });
    }

    #[Route('/{season}/lines', name: 'admin_club_ranking_lines', methods: ['GET'], requirements: ['season' => self::SEASON])]
    #[OA\Get(path: '/admin/club-ranking/{season}/lines', summary: 'Lines of detail, optionally of one club and/or one rule')]
    public function lines(string $season, Request $request): JsonResponse
    {
        $club = $request->query->getString('club');
        $rule = $request->query->getString('rule');
        $lines = array_values(array_filter(
            $this->repository->lines($season),
            static fn ($line): bool => ($club === '' || $line->clubCode === $club) && ($rule === '' || $line->ruleCode === $rule),
        ));

        return $this->json(['items' => array_map(static fn ($line): array => [
            'rule' => $line->ruleCode, 'club' => $line->clubCode, 'points' => $line->points, 'reason' => $line->reason,
            'source_type' => $line->sourceType, 'source_id' => $line->sourceId, 'matric' => $line->matric,
        ], $lines)]);
    }

    #[Route('/{season}/compute', name: 'admin_club_ranking_compute', methods: ['POST'], requirements: ['season' => self::SEASON])]
    #[OA\Post(path: '/admin/club-ranking/{season}/compute', summary: 'Compute the season (replaces the computed lines, keeps entries and adjustments)')]
    public function compute(string $season): JsonResponse
    {
        return $this->handle(fn (): JsonResponse => $this->json($this->service->compute($season, $this->author())));
    }

    #[Route('/{season}/settings', name: 'admin_club_ranking_settings', methods: ['PUT'], requirements: ['season' => self::SEASON])]
    public function settings(string $season, Request $request): JsonResponse
    {
        return $this->handle(function () use ($season, $request): JsonResponse {
            $body = self::body($request);
            $this->service->saveSettings($season, (int) ($body['top_n'] ?? 40), (string) ($body['national_prefixes'] ?? ''), ClubRankingRepository::csv((string) ($body['excluded_competitions'] ?? '')));

            return $this->json(['success' => true]);
        });
    }

    #[Route('/{season}/copy-scale', name: 'admin_club_ranking_copy_scale', methods: ['POST'], requirements: ['season' => self::SEASON])]
    public function copyScale(string $season, Request $request): JsonResponse
    {
        return $this->handle(function () use ($season, $request): JsonResponse {
            $this->service->copyScale(self::seasonOf(self::body($request)['from'] ?? null), $season);

            return $this->json(['success' => true]);
        });
    }

    #[Route('/{season}/rules', name: 'admin_club_ranking_rule_create', methods: ['POST'], requirements: ['season' => self::SEASON])]
    #[Route('/{season}/rules/{id}', name: 'admin_club_ranking_rule_update', methods: ['PUT'], requirements: ['season' => self::SEASON, 'id' => '\d+'])]
    public function saveRule(string $season, Request $request, ?int $id = null): JsonResponse
    {
        return $this->handle(function () use ($season, $request, $id): JsonResponse {
            $body = self::body($request);
            $type = RuleType::tryFrom((string) ($body['type'] ?? '')) ?? throw new ClubRankingError('Type de calcul inconnu.', 422);
            $params = $body['params'] ?? [];
            $newId = $this->service->saveRule($season, $id, (string) ($body['article'] ?? ''), (int) ($body['rank'] ?? 0), (string) ($body['label'] ?? ''),
                (string) ($body['short_label'] ?? ''), (int) ($body['points'] ?? -1), $type, is_array($params) ? $params : [], (string) ($body['note'] ?? ''));

            return $this->json(['id' => $newId], $id === null ? Response::HTTP_CREATED : Response::HTTP_OK);
        });
    }

    #[Route('/{season}/rules/{id}', name: 'admin_club_ranking_rule_delete', methods: ['DELETE'], requirements: ['season' => self::SEASON, 'id' => '\d+'])]
    public function deleteRule(string $season, int $id): JsonResponse
    {
        return $this->handle(function () use ($season, $id): JsonResponse {
            $this->service->deleteRule($season, $id);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        });
    }

    #[Route('/{season}/categories', name: 'admin_club_ranking_category_create', methods: ['POST'], requirements: ['season' => self::SEASON])]
    #[Route('/{season}/categories/{id}', name: 'admin_club_ranking_category_update', methods: ['PUT'], requirements: ['season' => self::SEASON, 'id' => '\d+'])]
    public function saveCategory(string $season, Request $request, ?int $id = null): JsonResponse
    {
        return $this->handle(function () use ($season, $request, $id): JsonResponse {
            $body = self::body($request);
            $codes = $body['entry_codes'] ?? [];
            $newId = $this->service->saveCategory($season, $id, (string) ($body['code'] ?? ''), (string) ($body['label'] ?? ''),
                is_array($codes) ? array_values(array_filter(array_map(static fn ($code): string => trim((string) $code), $codes))) : ClubRankingRepository::csv((string) $codes),
                self::optional($body['final_code'] ?? null), self::optional($body['regular_code'] ?? null));

            return $this->json(['id' => $newId], $id === null ? Response::HTTP_CREATED : Response::HTTP_OK);
        });
    }

    #[Route('/{season}/categories/{id}', name: 'admin_club_ranking_category_delete', methods: ['DELETE'], requirements: ['season' => self::SEASON, 'id' => '\d+'])]
    public function deleteCategory(string $season, int $id): JsonResponse
    {
        return $this->handle(function () use ($season, $id): JsonResponse {
            $this->service->deleteCategory($season, $id);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        });
    }

    #[Route('/{season}/entries', name: 'admin_club_ranking_entry_create', methods: ['POST'], requirements: ['season' => self::SEASON])]
    public function addEntry(string $season, Request $request): JsonResponse
    {
        return $this->handle(function () use ($season, $request): JsonResponse {
            $body = self::body($request);
            $matric = isset($body['matric']) && (int) $body['matric'] > 0 ? (int) $body['matric'] : null;
            $id = $this->service->addEntry($season, (string) ($body['rule'] ?? ''), $matric, self::optional($body['club'] ?? null), (string) ($body['detail'] ?? ''), $this->author());

            return $this->json(['id' => $id], Response::HTTP_CREATED);
        });
    }

    #[Route('/{season}/entries/{id}', name: 'admin_club_ranking_entry_delete', methods: ['DELETE'], requirements: ['season' => self::SEASON, 'id' => '\d+'])]
    public function deleteEntry(string $season, int $id): JsonResponse
    {
        return $this->handle(function () use ($season, $id): JsonResponse {
            $this->service->deleteEntry($season, $id);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        });
    }

    #[Route('/{season}/adjustments', name: 'admin_club_ranking_adjustment_create', methods: ['POST'], requirements: ['season' => self::SEASON])]
    public function addAdjustment(string $season, Request $request): JsonResponse
    {
        return $this->handle(function () use ($season, $request): JsonResponse {
            $body = self::body($request);
            $id = $this->service->addAdjustment($season, new Adjustment((string) ($body['rule'] ?? ''), (string) ($body['club'] ?? ''), (int) ($body['points'] ?? 0), (string) ($body['reason'] ?? ''), author: $this->author()));

            return $this->json(['id' => $id], Response::HTTP_CREATED);
        });
    }

    #[Route('/{season}/adjustments/{id}', name: 'admin_club_ranking_adjustment_delete', methods: ['DELETE'], requirements: ['season' => self::SEASON, 'id' => '\d+'])]
    public function deleteAdjustment(string $season, int $id): JsonResponse
    {
        return $this->handle(function () use ($season, $id): JsonResponse {
            $this->service->deleteAdjustment($season, $id);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        });
    }

    #[Route('/{season}/ententes', name: 'admin_club_ranking_entente_create', methods: ['POST'], requirements: ['season' => self::SEASON])]
    public function addEntente(string $season, Request $request): JsonResponse
    {
        return $this->handle(function () use ($season, $request): JsonResponse {
            $body = self::body($request);
            $this->service->addEntente($season, (int) ($body['team_number'] ?? 0), (string) ($body['club'] ?? ''));

            return $this->json(['success' => true], Response::HTTP_CREATED);
        });
    }

    #[Route('/{season}/ententes/{teamNumber}/{club}', name: 'admin_club_ranking_entente_delete', methods: ['DELETE'], requirements: ['season' => self::SEASON, 'teamNumber' => '\d+'])]
    public function deleteEntente(string $season, int $teamNumber, string $club): JsonResponse
    {
        return $this->handle(function () use ($season, $teamNumber, $club): JsonResponse {
            $this->service->deleteEntente($season, $teamNumber, $club);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        });
    }

    #[Route('/{season}/aliases', name: 'admin_club_ranking_alias_create', methods: ['POST'], requirements: ['season' => self::SEASON])]
    #[OA\Post(path: '/admin/club-ranking/{season}/aliases', summary: 'Match a free-text official name of a gameday to a licensee')]
    public function saveAlias(string $season, Request $request): JsonResponse
    {
        return $this->handle(function () use ($season, $request): JsonResponse {
            $body = self::body($request);
            $this->service->saveAlias($season, (string) ($body['name'] ?? ''), (int) ($body['matric'] ?? 0));

            return $this->json(['success' => true], Response::HTTP_CREATED);
        });
    }

    #[Route('/{season}/validate', name: 'admin_club_ranking_validate', methods: ['POST'], requirements: ['season' => self::SEASON])]
    public function validate(string $season): JsonResponse
    {
        return $this->handle(function () use ($season): JsonResponse {
            $this->service->validate($season, $this->author());

            return $this->json(['success' => true]);
        });
    }

    #[Route('/{season}/publish', name: 'admin_club_ranking_publish', methods: ['POST'], requirements: ['season' => self::SEASON])]
    public function publish(string $season): JsonResponse
    {
        return $this->handle(fn (): JsonResponse => $this->json(['version' => $this->service->publish($season, $this->author())]));
    }

    #[Route('/{season}/reopen', name: 'admin_club_ranking_reopen', methods: ['POST'], requirements: ['season' => self::SEASON])]
    public function reopen(string $season): JsonResponse
    {
        return $this->handle(function () use ($season): JsonResponse {
            $this->service->reopen($season);

            return $this->json(['success' => true]);
        });
    }

    #[Route('/{season}/publications/{version}', name: 'admin_club_ranking_publication', methods: ['GET'], requirements: ['season' => self::SEASON, 'version' => '\d+'])]
    public function publication(string $season, int $version): JsonResponse
    {
        $payload = $this->repository->publication($season, $version);

        return $payload === null ? $this->json(['message' => 'Publication introuvable.'], Response::HTTP_NOT_FOUND) : $this->json($payload);
    }

    /** @return list<array{team_number: int, label: string, club: string, partners: list<string>}> */
    private function ententes(string $season): array
    {
        $ententes = [];
        foreach ($this->repository->ententes($season) as $teamNumber => $clubs) {
            $team = $this->connection->fetchAssociative('SELECT Libelle, Code_club FROM kp_equipe WHERE Numero = ?', [$teamNumber]) ?: ['Libelle' => '?', 'Code_club' => ''];
            $ententes[] = ['team_number' => $teamNumber, 'label' => (string) $team['Libelle'], 'club' => (string) $team['Code_club'], 'partners' => $clubs];
        }

        return $ententes;
    }

    /** @param callable(): JsonResponse $action */
    private function handle(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (ClubRankingError $error) {
            return $this->json(['message' => $error->getMessage(), 'errors' => $error->errors], $error->status);
        }
    }

    /** @return array<string, mixed> */
    private static function body(Request $request): array
    {
        $body = json_decode($request->getContent(), true);

        return is_array($body) ? $body : throw new ClubRankingError('Corps JSON attendu.', 400);
    }

    private static function seasonOf(mixed $value): string
    {
        return is_string($value) && preg_match('/^' . self::SEASON . '$/', $value) ? $value : throw new ClubRankingError('Saison AAAA attendue.', 422);
    }

    private static function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function author(): ?string
    {
        return $this->getUser()?->getUserIdentifier();
    }
}
