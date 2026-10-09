<?php

namespace App\Controller;

use App\Http\UnicodeJsonResponse;
use App\PublicResults\PublicCompetitionService;
use App\PublicResults\PublicResultsService;
use App\PublicResults\ResultsFormat;
use App\PublicResults\Scope\CompetitionScope;
use App\PublicResults\Stats\CompetitionStats;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints publics, en lecture seule, du site public (DOC/specs/public/API_PUBLIC_RESULTS.md § 4).
 * Ils ne renvoient que le publié ; inconnu ou non publié → 404, paramètre invalide → 400.
 */
#[OA\Tag(name: self::DOC_TAG)]
class PublicCompetitionController extends AbstractController
{
    private const DOC_TAG = '7. Site public';

    /** Données qui changent à chaque match. */
    private const LIVE_MAX_AGE = 60;

    /** Données qui changent au plus après chaque journée. */
    private const SLOW_MAX_AGE = 300;

    private const SEASON_PATTERN = '/^\d{4}$/';
    private const CODE_PATTERN = '/^[A-Za-z0-9_-]{1,12}$/';

    private const DEFAULT_STAT_LIMIT = 20;
    private const MAX_STAT_LIMIT = 100;

    public function __construct(
        private readonly PublicCompetitionService $competitions,
        private readonly PublicResultsService $results,
        private readonly CompetitionStats $stats,
    ) {
    }

    #[Route('/seasons', name: 'public_seasons', methods: ['GET'])]
    #[OA\Get(path: '/seasons', summary: 'Seasons having published competitions, and the active season', tags: [self::DOC_TAG])]
    public function seasons(): JsonResponse
    {
        return $this->cached($this->competitions->seasons(), self::SLOW_MAX_AGE);
    }

    #[Route('/group/{season}/{code}/competitions', name: 'public_group_competitions', methods: ['GET'])]
    #[OA\Get(
        path: '/group/{season}/{code}/competitions',
        summary: 'Published competitions of a group with their compact ranking, and the linked events',
        tags: [self::DOC_TAG],
    )]
    public function groupCompetitions(string $season, string $code): JsonResponse
    {
        return $this->respond($season, $code, fn () => $this->competitions->groupCompetitions($season, $code), self::SLOW_MAX_AGE);
    }

    #[Route('/competition/{season}/{code}', name: 'public_competition', methods: ['GET'])]
    #[OA\Get(path: '/competition/{season}/{code}', summary: 'Competition header, sibling competitions and linked events', tags: [self::DOC_TAG])]
    public function competition(string $season, string $code): JsonResponse
    {
        return $this->respond($season, $code, fn () => $this->competitions->competition($season, $code));
    }

    #[Route('/competition/{season}/{code}/games', name: 'public_competition_games', methods: ['GET'])]
    #[OA\Get(path: '/competition/{season}/{code}/games', summary: 'Published games of a competition (format of /group/…/games)', tags: [self::DOC_TAG])]
    public function games(string $season, string $code): JsonResponse
    {
        return $this->respondForCompetition(
            $season,
            $code,
            fn () => $this->results->games(new CompetitionScope($season, $code), ResultsFormat::Group),
        );
    }

    #[Route('/competition/{season}/{code}/charts', name: 'public_competition_charts', methods: ['GET'])]
    #[OA\Get(path: '/competition/{season}/{code}/charts', summary: 'Rounds and phases of a competition (format of /event/{id}/charts)', tags: [self::DOC_TAG])]
    public function charts(string $season, string $code): JsonResponse
    {
        return $this->respondForCompetition(
            $season,
            $code,
            fn () => $this->results->charts(new CompetitionScope($season, $code), ResultsFormat::Event),
        );
    }

    #[Route('/competition/{season}/{code}/ranking', name: 'public_competition_ranking', methods: ['GET'])]
    #[OA\Get(path: '/competition/{season}/{code}/ranking', summary: 'General ranking of a competition, with medals', tags: [self::DOC_TAG])]
    public function ranking(string $season, string $code): JsonResponse
    {
        return $this->respond($season, $code, fn () => $this->competitions->ranking($season, $code));
    }

    #[Route('/competition/{season}/{code}/info', name: 'public_competition_info', methods: ['GET'])]
    #[OA\Get(path: '/competition/{season}/{code}/info', summary: 'Gamedays with officials, teams by pool, schema', tags: [self::DOC_TAG])]
    public function info(string $season, string $code): JsonResponse
    {
        return $this->respond($season, $code, fn () => $this->competitions->info($season, $code), self::SLOW_MAX_AGE);
    }

    #[Route('/competition/{season}/{code}/stats', name: 'public_competition_stats', methods: ['GET'])]
    #[OA\Get(path: '/competition/{season}/{code}/stats', summary: 'Available statistics of a competition', tags: [self::DOC_TAG])]
    public function statKinds(string $season, string $code): JsonResponse
    {
        return $this->respondForCompetition($season, $code, fn () => ['kinds' => $this->stats->kinds()]);
    }

    #[Route('/competition/{season}/{code}/stats/{kind}', name: 'public_competition_stat', methods: ['GET'])]
    #[OA\Get(
        path: '/competition/{season}/{code}/stats/{kind}',
        summary: 'One statistic of a competition (scorers…)',
        tags: [self::DOC_TAG],
        parameters: [new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))],
    )]
    public function stat(string $season, string $code, string $kind, Request $request): JsonResponse
    {
        $limit = filter_var(
            $request->query->get('limit', (string) self::DEFAULT_STAT_LIMIT),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => self::MAX_STAT_LIMIT]],
        );
        if ($limit === false) {
            return $this->invalidParameter();
        }
        $stat = $this->stats->find($kind);
        if ($stat === null) {
            return $this->notFound();
        }

        return $this->respondForCompetition($season, $code, fn () => [
            'kind' => $stat->kind(),
            'columns' => $stat->columns(),
            'rows' => $stat->rows(new CompetitionScope($season, $code), $limit),
        ]);
    }

    #[Route('/event/{id}/competitions', name: 'public_event_competitions', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(path: '/event/{id}/competitions', summary: 'Published event header and its published competitions', tags: [self::DOC_TAG])]
    public function eventCompetitions(int $id): JsonResponse
    {
        $data = $this->competitions->eventCompetitions($id);

        return $data === null ? $this->notFound() : $this->cached($data, self::LIVE_MAX_AGE);
    }

    /**
     * Valide saison et code, puis renvoie les données ou 404.
     *
     * @param callable(): ?array<mixed> $load
     */
    private function respond(string $season, string $code, callable $load, int $maxAge = self::LIVE_MAX_AGE): JsonResponse
    {
        if (!$this->isValid($season, $code)) {
            return $this->invalidParameter();
        }
        $data = $load();

        return $data === null ? $this->notFound() : $this->cached($data, $maxAge);
    }

    /**
     * Comme respond(), pour une ressource qui n'existe que si la compétition est publiée.
     *
     * @param callable(): array<mixed> $load
     */
    private function respondForCompetition(string $season, string $code, callable $load): JsonResponse
    {
        return $this->respond(
            $season,
            $code,
            fn () => $this->competitions->findHeader($season, $code) === null ? null : $load(),
        );
    }

    private function isValid(string $season, string $code): bool
    {
        return preg_match(self::SEASON_PATTERN, $season) === 1 && preg_match(self::CODE_PATTERN, $code) === 1;
    }

    private function cached(mixed $data, int $maxAge): JsonResponse
    {
        $response = new UnicodeJsonResponse($data);
        $response->setPublic();
        $response->setMaxAge($maxAge);

        return $response;
    }

    private function notFound(): JsonResponse
    {
        return new UnicodeJsonResponse(['error' => 'not_found'], 404);
    }

    private function invalidParameter(): JsonResponse
    {
        return new UnicodeJsonResponse(['error' => 'invalid_parameter'], 400);
    }
}
