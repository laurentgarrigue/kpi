<?php

namespace App\Controller;

use App\Http\UnicodeJsonResponse;
use App\PublicResults\PublicCompetitionService;
use App\PublicSite\PublicSiteRules;
use App\PublicSite\PublicSiteService;
use App\PublicSite\SearchThrottle;
use App\PublicSite\Ics\IcsCalendar;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints publics transverses du site public (DOC/specs/public/API_PUBLIC_TRANSVERSE.md) : calendrier, ICS,
 * historique, équipes, clubs, recherche. Lecture seule, publié seulement ; inconnu → 404, paramètre
 * invalide → 400. Mêmes conventions que PublicCompetitionController.
 */
#[OA\Tag(name: self::DOC_TAG)]
class PublicSiteController extends AbstractController
{
    private const DOC_TAG = '7. Site public';

    /** Calendrier, équipes, recherche. */
    private const SHORT_MAX_AGE = 300;

    /** Historique, clubs, ICS : changent rarement. */
    private const LONG_MAX_AGE = 3600;

    private const SEASON_PATTERN = '/^\d{4}$/';
    private const CODE_PATTERN = '/^[A-Za-z0-9_-]{1,12}$/';
    private const CLUB_PATTERN = '/^[A-Za-z0-9_-]{1,6}$/';
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    private const MAX_CALENDAR_DAYS = 400;

    public function __construct(
        private readonly PublicSiteService $site,
        private readonly PublicCompetitionService $competitions,
        private readonly SearchThrottle $throttle,
    ) {
    }

    #[Route('/calendar', name: 'public_calendar', methods: ['GET'])]
    #[OA\Get(
        path: '/calendar',
        summary: 'Published gamedays overlapping a period (≤ 400 days), merged by competition, dates and place',
        tags: [self::DOC_TAG],
        parameters: [
            new OA\Parameter(name: 'start', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'section', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: PublicSiteRules::SECTIONS)),
            new OA\Parameter(name: 'group', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
    )]
    public function calendar(Request $request): JsonResponse
    {
        $start = self::date($request->query->getString('start'));
        $end = self::date($request->query->getString('end'));
        $section = self::section($request->query->getString('section'));
        $group = PublicSiteRules::nullIfEmpty($request->query->getString('group'));
        if (
            $start === null || $end === null || $end < $start
            || $start->diff($end)->days > self::MAX_CALENDAR_DAYS
            || $section === false
            || ($group !== null && preg_match(self::CODE_PATTERN, $group) !== 1)
        ) {
            return $this->invalidParameter();
        }

        return $this->cached($this->site->calendar($start->format('Y-m-d'), $end->format('Y-m-d'), $section, $group), self::SHORT_MAX_AGE);
    }

    #[Route('/calendar/groups', name: 'public_calendar_groups', methods: ['GET'])]
    #[OA\Get(path: '/calendar/groups', summary: 'Groups having published competitions, by section (Divers included): calendar filter', tags: [self::DOC_TAG])]
    public function calendarGroups(): JsonResponse
    {
        return $this->cached($this->site->calendarGroups(), self::SHORT_MAX_AGE);
    }

    #[Route('/competition/{season}/{code}/calendar.ics', name: 'public_competition_ics', methods: ['GET'])]
    #[OA\Get(path: '/competition/{season}/{code}/calendar.ics', summary: 'iCalendar subscription of a competition: one event per published gameday', tags: [self::DOC_TAG])]
    public function competitionIcs(string $season, string $code): Response
    {
        if (preg_match(self::SEASON_PATTERN, $season) !== 1 || preg_match(self::CODE_PATTERN, $code) !== 1) {
            return $this->invalidParameter();
        }
        $header = $this->competitions->findHeader($season, $code);
        if ($header === null) {
            return $this->notFound();
        }

        return $this->ics($this->site->competitionIcs($season, $code, $header->displayTitle), sprintf('%s-%s.ics', $code, $season));
    }

    #[Route('/gameday/{id}.ics', name: 'public_gameday_ics', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(path: '/gameday/{id}.ics', summary: 'One published gameday, in iCalendar', tags: [self::DOC_TAG])]
    public function gamedayIcs(int $id): Response
    {
        $ics = $this->site->gamedayIcs($id);

        return $ics === null ? $this->notFound() : $this->ics($ics, sprintf('gameday-%d.ics', $id));
    }

    #[Route('/history', name: 'public_history_groups', methods: ['GET'])]
    #[OA\Get(path: '/history', summary: 'Groups having finished final-round competitions, by section', tags: [self::DOC_TAG])]
    public function historyGroups(): JsonResponse
    {
        return $this->cached($this->site->historyGroups(), self::LONG_MAX_AGE);
    }

    #[Route('/history/{group}', name: 'public_history', methods: ['GET'])]
    #[OA\Get(path: '/history/{group}', summary: 'Honours of a group, season by season, with medals', tags: [self::DOC_TAG])]
    public function history(string $group): JsonResponse
    {
        if (preg_match(self::CODE_PATTERN, $group) !== 1) {
            return $this->invalidParameter();
        }

        return $this->orNotFound($this->site->history($group), self::LONG_MAX_AGE);
    }

    #[Route('/teams', name: 'public_teams', methods: ['GET'])]
    #[OA\Get(
        path: '/teams',
        summary: 'Teams whose name contains q (or whose club code starts with q), 20 at most',
        tags: [self::DOC_TAG],
        parameters: [new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 50))],
    )]
    public function teams(Request $request): JsonResponse
    {
        $term = PublicSiteRules::searchTerm($request->query->getString('q'));

        return $term === null ? $this->invalidParameter() : $this->cached($this->site->searchTeams($term), self::SHORT_MAX_AGE);
    }

    #[Route('/team/{number}', name: 'public_team', methods: ['GET'], requirements: ['number' => '\d{1,6}'])]
    #[OA\Get(path: '/team/{number}', summary: 'Team sheet: club, colours, team photo, honours, seasons', tags: [self::DOC_TAG])]
    public function team(int $number): JsonResponse
    {
        return $this->orNotFound($this->site->team($number), self::SHORT_MAX_AGE);
    }

    #[Route('/team/{number}/roster/{season}/{code}', name: 'public_team_roster', methods: ['GET'], requirements: ['number' => '\d{1,6}'])]
    #[OA\Get(path: '/team/{number}/roster/{season}/{code}', summary: 'Roster of a team in a competition, with goals and cards (no licence, sex nor birth date)', tags: [self::DOC_TAG])]
    public function roster(int $number, string $season, string $code): JsonResponse
    {
        if (preg_match(self::SEASON_PATTERN, $season) !== 1 || preg_match(self::CODE_PATTERN, $code) !== 1) {
            return $this->invalidParameter();
        }

        return $this->orNotFound($this->site->roster($number, $season, $code), self::SHORT_MAX_AGE);
    }

    #[Route('/clubs', name: 'public_clubs', methods: ['GET'])]
    #[OA\Get(
        path: '/clubs',
        summary: 'Clubs having at least one team, with logo and position; optional filter on name or code',
        tags: [self::DOC_TAG],
        parameters: [new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 50))],
    )]
    public function clubs(Request $request): JsonResponse
    {
        $query = PublicSiteRules::nullIfEmpty($request->query->getString('q'));
        $term = $query === null ? null : PublicSiteRules::searchTerm($query);
        if ($query !== null && $term === null) {
            return $this->invalidParameter();
        }

        return $this->cached($this->site->clubs($term), self::LONG_MAX_AGE);
    }

    #[Route('/club/{code}', name: 'public_club', methods: ['GET'])]
    #[OA\Get(path: '/club/{code}', summary: 'Club sheet: committees, website, e-mail, address, position, teams', tags: [self::DOC_TAG])]
    public function club(string $code): JsonResponse
    {
        if (preg_match(self::CLUB_PATTERN, $code) !== 1) {
            return $this->invalidParameter();
        }

        return $this->orNotFound($this->site->club($code), self::LONG_MAX_AGE);
    }

    #[Route('/search', name: 'public_search', methods: ['GET'])]
    #[OA\Get(
        path: '/search',
        summary: 'Global search: competitions, events, teams, clubs (8 each, no person); 30 requests per minute per IP',
        tags: [self::DOC_TAG],
        parameters: [new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 50))],
    )]
    public function search(Request $request): JsonResponse
    {
        $retryAfter = $this->throttle->hit($request->getClientIp());
        if ($retryAfter !== null) {
            return new UnicodeJsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $retryAfter]);
        }
        $term = PublicSiteRules::searchTerm($request->query->getString('q'));

        return $term === null ? $this->invalidParameter() : $this->cached($this->site->search($term), self::SHORT_MAX_AGE);
    }

    /** Section demandée : null si absente, false si invalide. */
    private static function section(string $value): int|false|null
    {
        if ($value === '') {
            return null;
        }
        $section = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($section) && in_array($section, PublicSiteRules::SECTIONS, true) ? $section : false;
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        if (preg_match(self::DATE_PATTERN, $value) !== 1) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function ics(string $content, string $filename): Response
    {
        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => IcsCalendar::CONTENT_TYPE]);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename));
        $response->setPublic();
        $response->setMaxAge(self::LONG_MAX_AGE);

        return $response;
    }

    private function orNotFound(mixed $data, int $maxAge): JsonResponse
    {
        return $data === null ? $this->notFound() : $this->cached($data, $maxAge);
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
