<?php

namespace App\Controller;

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
 * ⚠️ These routes are **public on purpose**, and that is a deliberate, bounded decision:
 *  - the overlay runs unattended in a video mixer (OBS) on any machine; it cannot hold a
 *    JWT, and the current PHP overlays are public too — this is not a regression;
 *  - the data served is exactly what is already displayed on the venue's screens: score,
 *    clocks, goals/cards, team names. Nothing personal beyond what the scoreboard shows;
 *  - **read-only**: GET only, no write path whatsoever. (The former public `/wsm` write
 *    endpoints were removed for that very reason — see ScoringController.)
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
        /** Browser-facing Mercure subscribe URL (never the JWT secret). */
        private readonly string $mercurePublicUrl = ''
    ) {
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
        $program = $this->program->getProgram($event, $pitch);
        $program['mercureUrl'] = $this->mercurePublicUrl;

        // The overlay boots on this call then follows Mercure: a short cache absorbs a
        // wall of screens restarting at once without hammering the database.
        $etag = 'W/"' . md5(json_encode($program)) . '"';
        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, Response::HTTP_NOT_MODIFIED);
        }

        $response = new JsonResponse($program);
        $response->headers->set('ETag', $etag);
        $response->setPublic();
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
        $response->setPublic();
        $response->setMaxAge(2);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);

        return $response;
    }
}
