<?php

namespace App\Controller;

use App\MatchMedals\Catalog\MedalCatalog;
use App\MatchMedals\Catalog\MedalSurface;
use App\MatchMedals\MatchMedalReader;
use App\MatchMedals\MatchMedalService;
use App\MatchMedals\MedalSettings;
use App\MatchMedals\MedalThresholds;
use App\MatchMedals\Stats\MedalScope;
use App\MatchMedals\Stats\MedalStats;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin of the match medals, profiles 1 and 2 only (FEATURE_MATCH_MEDALS.md § 9.3): catalogue (names, explanations,
 * surfaces, status), frozen seasons, every active statistic of the work context, medals of a game.
 */
#[Route('/admin/medals')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin - Médailles')]
final class AdminMedalsController extends AbstractController
{
    private const SEASON_PATTERN = '/^\d{4}$/';
    private const CODE_PATTERN = '/^[A-Za-z0-9_-]{1,12}$/';
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 500;

    /** @param array<string, mixed> $thresholds */
    public function __construct(
        private readonly MedalCatalog $catalog,
        private readonly MedalStats $stats,
        private readonly MatchMedalReader $reader,
        private readonly MatchMedalService $service,
        private readonly Connection $connection,
        private readonly MedalSettings $settings,
    ) {
    }

    #[Route('/catalog', name: 'admin_medals_catalog', methods: ['GET'])]
    #[OA\Get(path: '/admin/medals/catalog', summary: 'Every element of the catalogue, with its awards count over a season')]
    public function catalog(Request $request): JsonResponse
    {
        $season = $this->season($request);
        if ($season === false) {
            return $this->invalid();
        }
        $counts = $season === null ? [] : $this->awardCounts($season);
        $thresholds = $this->catalog->thresholdsOf($season);

        return $this->json([
            'season' => $season,
            'items' => array_map(fn (array $row): array => [
                ...$this->catalog->present($row, $thresholds),
                'explanation_fr_template' => $row['explanation_fr'],
                'explanation_en_template' => $row['explanation_en'],
                'status' => $row['status'],
                'published_app2' => $row['published_app2'],
                'published_app3' => $row['published_app3'],
                'sort_order' => $row['sort_order'],
                'awards' => $row['type'] === 'stat' ? null : ($counts[$row['code']] ?? 0),
                'updated_at' => $row['updated_at'],
                'updated_by' => $row['updated_by'],
            ], $this->catalog->all()),
        ]);
    }

    #[Route('/catalog/{code}', name: 'admin_medals_catalog_update', methods: ['PATCH'], requirements: ['code' => '[a-z_]{1,40}'])]
    #[OA\Patch(path: '/admin/medals/catalog/{code}', summary: 'Edit names, explanations, surfaces, status or order of an element (journalled)')]
    public function update(string $code, Request $request): JsonResponse
    {
        $changes = json_decode($request->getContent(), true);
        if (!is_array($changes)) {
            return $this->invalid();
        }
        try {
            $row = $this->catalog->update($code, $changes, $this->getUser()?->getUserIdentifier() ?? 'system');
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $row === null ? $this->json(['error' => 'not_found'], Response::HTTP_NOT_FOUND) : $this->json($row);
    }

    #[Route('/seasons', name: 'admin_medals_seasons', methods: ['GET'])]
    #[OA\Get(path: '/admin/medals/seasons', summary: 'Computed seasons: frozen thresholds, rules version, counts')]
    public function seasons(): JsonResponse
    {
        $current = $this->settings->thresholds();

        return $this->json([
            'rules_version' => $this->service->rulesVersion(),
            'thresholds' => $current,
            'default_thresholds' => $this->settings->fileThresholds(),
            'overrides' => $this->settings->overrides(),
            'limits' => array_map(static fn (array $range): array => ['min' => $range[0], 'max' => $range[1]], MedalThresholds::limits()),
            'card_codes' => MedalThresholds::CARD_CODES,
            'uncomputed' => $this->connection->fetchFirstColumn('SELECT Code FROM kp_saison WHERE Code NOT IN (SELECT season FROM kp_medal_season) ORDER BY Code DESC'),
            'seasons' => array_map(fn (array $s): array => [
                'season' => $s['season'],
                'thresholds' => $frozen = [...$current, ...json_decode((string) $s['thresholds'], true)],
                'differs' => $frozen !== $current,
                'rules_version' => (int) $s['rules_version'],
                'outdated' => (int) $s['rules_version'] < $this->service->rulesVersion(),
                'computed_at' => $s['computed_at'],
                'games_count' => (int) $s['games_count'],
                'reliable_games_count' => (int) $s['reliable_games_count'],
            ], $this->connection->fetchAllAssociative('SELECT * FROM kp_medal_season ORDER BY season DESC')),
        ]);
    }

    #[Route('/thresholds', name: 'admin_medals_thresholds_update', methods: ['PUT'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[OA\Put(path: '/admin/medals/thresholds', summary: 'Saves thresholds (partial, validated, journalled); profile 1 only. Computed seasons keep theirs')]
    public function updateThresholds(Request $request): JsonResponse
    {
        $changes = json_decode($request->getContent(), true);
        if (!is_array($changes) || !is_array($changes['changes'] ?? null)) {
            return $this->invalid();
        }
        try {
            $this->settings->saveThresholds($changes['changes'], $this->user());
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->thresholdsResponse();
    }

    #[Route('/thresholds', name: 'admin_medals_thresholds_reset', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[OA\Delete(path: '/admin/medals/thresholds', summary: 'Goes back to the values of match_medals.yaml; profile 1 only')]
    public function resetThresholds(): JsonResponse
    {
        $this->settings->resetThresholds($this->user());

        return $this->thresholdsResponse();
    }

    #[Route('/seasons/{season}/compute', name: 'admin_medals_season_compute', methods: ['POST'], requirements: ['season' => '\d{4}'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[OA\Post(path: '/admin/medals/seasons/{season}/compute', summary: 'Full computation of a season with the current thresholds (as make api2_match_medals_compute); profile 1 only')]
    public function computeSeason(string $season): JsonResponse
    {
        if ($this->connection->fetchOne('SELECT 1 FROM kp_saison WHERE Code = ?', [$season]) === false) {
            return $this->json(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }
        set_time_limit(0);
        $result = $this->service->computeSeason($season, $this->user());
        $this->settings->journalSeason($this->user(), sprintf('Saison %s recalculée : %d matchs', $season, $result['computed']));

        return $this->json($result);
    }

    #[Route('/seasons/{season}', name: 'admin_medals_season_delete', methods: ['DELETE'], requirements: ['season' => '\d{4}'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[OA\Delete(path: '/admin/medals/seasons/{season}', summary: 'Deletes every calculation of a season; it is not computed again until a full computation; profile 1 only')]
    public function deleteSeason(string $season): JsonResponse
    {
        if (!$this->service->seasonIsComputed($season)) {
            return $this->json(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }
        $result = $this->service->deleteSeason($season, $this->user());
        $this->settings->journalSeason($this->user(), sprintf('Saison %s : calculs supprimés (%d matchs)', $season, $result['removed']));

        return $this->json($result);
    }

    private function thresholdsResponse(): JsonResponse
    {
        return $this->json(['thresholds' => $this->settings->thresholds(), 'overrides' => $this->settings->overrides()]);
    }

    private function user(): string
    {
        return $this->getUser()?->getUserIdentifier() ?? 'system';
    }

    #[Route('/stats', name: 'admin_medals_stat_kinds', methods: ['GET'])]
    #[OA\Get(path: '/admin/medals/stats', summary: 'Every active timeline statistic, with its surfaces')]
    public function statKinds(): JsonResponse
    {
        return $this->json(['kinds' => array_map(fn (string $code): array => [
            'code' => $code,
            'surfaces' => array_keys(array_filter(['app2' => $this->catalog->find($code)['published_app2'], 'app3' => $this->catalog->find($code)['published_app3']])),
        ], $this->stats->visibleKinds(MedalSurface::Admin))]);
    }

    #[Route('/stats/{kind}', name: 'admin_medals_stat', methods: ['GET'], requirements: ['kind' => '[a-z_]{1,40}'])]
    #[OA\Get(path: '/admin/medals/stats/{kind}', summary: 'One timeline statistic over a season and a set of competitions (all active elements)')]
    public function stat(string $kind, Request $request): JsonResponse
    {
        $season = $this->season($request);
        $competitions = array_values(array_filter(explode(',', (string) $request->query->get('competitions', ''))));
        $limit = filter_var($request->query->get('limit', (string) self::DEFAULT_LIMIT), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_LIMIT]]);
        if ($season === false || $season === null || $competitions === [] || $limit === false
            || array_filter($competitions, static fn (string $c): bool => preg_match(self::CODE_PATTERN, $c) !== 1) !== []) {
            return $this->invalid();
        }
        $result = $this->stats->run($kind, new MedalScope($season, $competitions), MedalSurface::Admin, $limit);

        return $result === null ? $this->json(['error' => 'not_found'], Response::HTTP_NOT_FOUND) : $this->json($result);
    }

    #[Route('/games/{id}', name: 'admin_medals_game', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(path: '/admin/medals/games/{id}', summary: 'Medals and labels of a game (every active element)')]
    public function game(int $id): JsonResponse
    {
        return $this->json($this->reader->forGame($id, MedalSurface::Admin));
    }

    /** @return string|null|false null when absent, false when invalid */
    private function season(Request $request): string|null|false
    {
        $season = $request->query->get('season');
        if ($season === null || $season === '') {
            return null;
        }

        return preg_match(self::SEASON_PATTERN, (string) $season) === 1 ? (string) $season : false;
    }

    /** @return array<string, int> awards of a season by code (medals and labels) */
    private function awardCounts(string $season): array
    {
        $counts = array_map('intval', $this->connection->fetchAllKeyValue(
            'SELECT md.code, COUNT(*) FROM kp_match_medal md INNER JOIN kp_match_insight i ON (i.match_id = md.match_id) WHERE i.season = ? GROUP BY md.code',
            [$season],
        ));
        foreach ($this->connection->fetchFirstColumn("SELECT labels FROM kp_match_insight WHERE season = ? AND labels <> '[]'", [$season]) as $labels) {
            foreach (json_decode((string) $labels, true) as $label) {
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function invalid(): JsonResponse
    {
        return $this->json(['error' => 'invalid_parameter'], Response::HTTP_BAD_REQUEST);
    }
}
