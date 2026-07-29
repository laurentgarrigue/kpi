<?php

namespace App\Controller;

use App\Service\ScoringDisplayAccessService;
use App\Service\ScoringLiveService;
use App\Service\ScoringProgramService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public read side of the live scoring — what the video overlay consumes
 * (PAGE_INCRUSTATION.md, plan lot 4).
 *
 * ⚠️ These routes carry **no user JWT** — the overlay runs unattended in a video mixer and
 * cannot hold one. They are nonetheless **not open**: access is granted by a **display
 * token** (`?token=…`), scoped to an event (optionally a single pitch), expiring and
 * revocable — see ScoringDisplayAccessService, which also explains why a same-origin check
 * alone would not be a security boundary.
 *
 * Three properties keep the exposure bounded:
 *  - **read-only**: GET only, no write path whatsoever (the former public `/wsm` write
 *    endpoints were removed for that very reason — see ScoringController);
 *  - the data served is exactly what is already displayed on the venue's screens: score,
 *    clocks, goals/cards, team names. Nothing personal beyond what the scoreboard shows;
 *  - the response carries a **Mercure subscriber JWT restricted to the token's topics**,
 *    so an overlay can never listen to another event's pitches (and it is what makes the
 *    subscription work at all in preprod/prod, where MERCURE_ANONYMOUS=0).
 *
 * Both routes are cache-friendly (ETag): the hub pushes changes, so a client that keeps
 * a stale copy for a few seconds is harmless, and the database is spared.
 */
#[Route('/scoring')]
#[OA\Tag(name: '6. Scoring (public live)')]
class ScoringLiveController extends AbstractController
{
    public function __construct(
        private readonly ScoringLiveService $live,
        private readonly ScoringProgramService $program,
        private readonly ScoringDisplayAccessService $access,
        /** Browser-facing Mercure subscribe URL (never the JWT secret). */
        private readonly string $mercurePublicUrl = ''
    ) {
    }

    /**
     * Gate of the public read path: obvious cross-site fetch → 403 (defence in depth),
     * missing/invalid display token → 401. Returns the validated scope, or a response to
     * send back.
     *
     * @return array{id:int,event:int,pitch:?string}|JsonResponse
     */
    private function authorize(Request $request, int $event, ?string $pitch): array|JsonResponse
    {
        if (!$this->access->looksSameOrigin($request)) {
            return new JsonResponse(['error' => 'Cross-origin request refused'], Response::HTTP_FORBIDDEN);
        }

        $scope = $this->access->validate($request, $event, $pitch);
        if ($scope === null) {
            return new JsonResponse(
                ['error' => 'A valid display token is required for this event'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $scope;
    }

    #[Route('/program/{event}/{pitch}', name: 'scoring_public_program', methods: ['GET'])]
    #[OA\Get(
        path: '/scoring/program/{event}/{pitch}',
        summary: 'Program of a pitch: current match, next match, display settings (public)',
        tags: ['6. Scoring (public live)'],
        responses: [
            new OA\Response(response: 200, description: 'Program + resolved display settings + Mercure addressing')
        ]
    )]
    public function program(int $event, string $pitch, Request $request): JsonResponse
    {
        $scope = $this->authorize($request, $event, $pitch);
        if ($scope instanceof JsonResponse) {
            return $scope;
        }

        $program = $this->program->getProgram($event, $pitch);
        $program['mercureUrl'] = $this->mercurePublicUrl;
        // Subscriber JWT restricted to this token's topics — the overlay cannot listen
        // to anything else, and the subscription works with MERCURE_ANONYMOUS=0.
        $program['mercureToken'] = $this->access->mintSubscriberJwt($scope);

        // The overlay boots on this call then follows Mercure: a short cache absorbs a
        // wall of screens restarting at once without hammering the database.
        $etag = 'W/"' . md5(json_encode($program)) . '"';
        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, Response::HTTP_NOT_MODIFIED);
        }

        $response = new JsonResponse($program);
        $response->headers->set('ETag', $etag);
        // PRIVATE on purpose: this payload embeds a Mercure subscriber JWT, which must
        // never sit in a shared cache. The client may still revalidate with its ETag.
        $response->setPrivate();
        $response->setMaxAge(5);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);

        return $response;
    }

    #[Route('/state/{matchId}', name: 'scoring_public_state', methods: ['GET'])]
    #[OA\Get(
        path: '/scoring/state/{matchId}',
        summary: 'Canonical live state of a match: score, clocks, facts (public, read-only)',
        tags: ['6. Scoring (public live)'],
        responses: [
            new OA\Response(response: 200, description: 'Live state'),
            new OA\Response(response: 304, description: 'Not modified (ETag = tick)'),
            new OA\Response(response: 404, description: 'No live state for this match yet')
        ]
    )]
    public function state(int $matchId, Request $request): JsonResponse
    {
        // The token is scoped to an event/pitch, the route to a match: resolve the match's
        // location and check the token against it, so a token cannot read another event.
        $location = $this->program->locateMatch($matchId);
        if ($location === null) {
            return new JsonResponse(['error' => 'Match not attached to an event/pitch'], Response::HTTP_NOT_FOUND);
        }
        $scope = $this->authorize($request, $location['event'], $location['pitch']);
        if ($scope instanceof JsonResponse) {
            return $scope;
        }

        $state = $this->live->getState($matchId);

        if ($state === null) {
            return new JsonResponse(['exists' => false, 'matchId' => $matchId], Response::HTTP_NOT_FOUND);
        }

        // The tick IS the version (plan §1.3): a 304 costs one index lookup.
        $etag = 'W/"scoring-' . $matchId . '-' . $state['tick'] . '"';
        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, Response::HTTP_NOT_MODIFIED);
        }

        $response = new JsonResponse(['exists' => true] + $state + [
            'mercureUrl' => $this->mercurePublicUrl,
            'nowServer' => time() % 86400,
        ]);
        $response->headers->set('ETag', $etag);
        // No secret in this payload, and the token sits in the URL (caches key on it):
        // a short shared cache absorbs a wall of screens restarting at once.
        $response->setPublic();
        $response->setMaxAge(2);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);

        return $response;
    }
}
