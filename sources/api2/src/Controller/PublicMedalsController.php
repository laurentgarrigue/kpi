<?php

namespace App\Controller;

use App\Http\UnicodeJsonResponse;
use App\MatchMedals\Catalog\MedalCatalog;
use App\MatchMedals\Catalog\MedalSurface;
use App\MatchMedals\MatchMedalReader;
use App\PublicResults\Scope\CompetitionScope;
use App\PublicResults\Scope\EventScopeFactory;
use App\PublicResults\Scope\GroupScope;
use App\PublicResults\Scope\ResultsScope;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Public, read-only endpoints of the match medals (FEATURE_MATCH_MEDALS.md § 6.4). No licence number. */
#[OA\Tag(name: self::DOC_TAG)]
final class PublicMedalsController extends AbstractController
{
    private const DOC_TAG = '7. Site public';
    private const MAX_AGE = 300;
    private const SEASON_PATTERN = '/^\d{4}$/';

    public function __construct(private readonly MedalCatalog $catalog, private readonly MatchMedalReader $medals)
    {
    }

    #[Route('/medals/catalog', name: 'public_medals_catalog', methods: ['GET'])]
    #[OA\Get(
        path: '/medals/catalog',
        summary: 'Published medals, labels and statistics, with their explanation rendered with the thresholds of a season',
        tags: [self::DOC_TAG],
        parameters: [
            new OA\Parameter(name: 'surface', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['public', 'app2', 'app3'], default: 'public')),
            new OA\Parameter(name: 'season', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '2026')),
        ],
    )]
    public function catalog(Request $request): JsonResponse
    {
        $surface = MedalSurface::fromPublicParameter($request->query->get('surface'), MedalSurface::Public);
        $seasons = $this->catalog->computedSeasons();
        $season = $request->query->get('season', $seasons[0] ?? null);
        if ($surface === null || ($season !== null && preg_match(self::SEASON_PATTERN, (string) $season) !== 1)) {
            return new UnicodeJsonResponse(['error' => 'invalid_parameter'], 400);
        }
        $thresholds = $this->catalog->thresholdsOf($season);

        return $this->cached([
            'season' => $season,
            'seasons' => $seasons,
            'items' => array_map(fn (array $row): array => [
                ...$this->catalog->present($row, $thresholds),
                'surfaces' => array_keys(array_filter(['app2' => $row['published_app2'], 'app3' => $row['published_app3']])),
            ], $this->catalog->visible($surface)),
        ]);
    }

    #[Route('/team/{number}/medals', name: 'public_team_medals', methods: ['GET'], requirements: ['number' => '\d{1,6}'])]
    #[OA\Get(
        path: '/team/{number}/medals',
        summary: 'Medals of a team over a season, published on app3, with the games concerned',
        tags: [self::DOC_TAG],
        parameters: [new OA\Parameter(name: 'season', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: '2026'))],
    )]
    public function teamMedals(int $number, Request $request): JsonResponse
    {
        $season = (string) $request->query->get('season', '');
        if (preg_match(self::SEASON_PATTERN, $season) !== 1) {
            return new UnicodeJsonResponse(['error' => 'invalid_parameter'], 400);
        }

        return $this->cached(['season' => $season, 'medals' => $this->medals->teamSeason($number, $season, MedalSurface::App3)]);
    }

    #[Route('/event/{eventId}/team/{teamId}/medals', name: 'public_event_team_medals', methods: ['GET'], requirements: ['eventId' => '\d{1,6}', 'teamId' => '\d{1,9}'])]
    #[OA\Get(
        path: '/event/{eventId}/team/{teamId}/medals',
        summary: 'Medals of a team and of its players over an event (an id from 3000 is a gameday), published on app2',
        tags: ['2. App2 - Public'],
    )]
    public function eventTeamMedals(int $eventId, int $teamId): JsonResponse
    {
        return $this->scopedTeamMedals(EventScopeFactory::fromEventId($eventId), $teamId);
    }

    #[Route('/group/{season}/{groupCode}/team/{teamId}/medals', name: 'public_group_team_medals', methods: ['GET'], requirements: ['season' => '\d{4}', 'groupCode' => '[A-Za-z0-9_-]{1,20}', 'teamId' => '\d{1,9}'])]
    #[OA\Get(
        path: '/group/{season}/{groupCode}/team/{teamId}/medals',
        summary: 'Medals of a team and of its players over a group and a season, published on app2',
        tags: ['2. App2 - Public'],
    )]
    public function groupTeamMedals(string $season, string $groupCode, int $teamId): JsonResponse
    {
        return $this->scopedTeamMedals(new GroupScope($season, $groupCode), $teamId);
    }

    #[Route('/competition/{season}/{code}/team/{teamId}/medals', name: 'public_competition_team_medals', methods: ['GET'], requirements: ['season' => '\d{4}', 'code' => '[A-Za-z0-9_-]{1,20}', 'teamId' => '\d{1,9}'])]
    #[OA\Get(
        path: '/competition/{season}/{code}/team/{teamId}/medals',
        summary: 'Medals of a team and of its players over one competition, published on app2',
        tags: ['2. App2 - Public'],
    )]
    public function competitionTeamMedals(string $season, string $code, int $teamId): JsonResponse
    {
        return $this->scopedTeamMedals(new CompetitionScope($season, $code), $teamId);
    }

    private function scopedTeamMedals(ResultsScope $scope, int $teamId): JsonResponse
    {
        $medals = $this->medals->teamInScope($scope, $teamId, MedalSurface::App2);

        return $medals === null ? new UnicodeJsonResponse(['error' => 'not_found'], 404) : $this->cached($medals);
    }

    private function cached(array $data): JsonResponse
    {
        $response = new UnicodeJsonResponse($data);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);

        return $response;
    }
}
