<?php

namespace App\Controller;

use App\Http\UnicodeJsonResponse;
use App\MatchMedals\Catalog\MedalCatalog;
use App\MatchMedals\Catalog\MedalSurface;
use App\MatchMedals\MatchMedalReader;
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

    private function cached(array $data): JsonResponse
    {
        $response = new UnicodeJsonResponse($data);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);

        return $response;
    }
}
