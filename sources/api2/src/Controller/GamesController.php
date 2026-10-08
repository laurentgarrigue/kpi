<?php

namespace App\Controller;

use App\Http\UnicodeJsonResponse;
use App\PublicResults\PublicResultsService;
use App\PublicResults\ResultsFormat;
use App\PublicResults\Scope\EventScopeFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GamesController extends AbstractController
{
    public function __construct(
        private readonly PublicResultsService $results
    ) {
    }

    #[Route('/event/{eventId}/games', name: 'games', methods: ['GET'])]
    #[OA\Get(
        path: '/event/{eventId}/games',
        summary: 'Get games for an event',
        tags: ['2. App2 - Public'],
        parameters: [
            new OA\Parameter(
                name: 'eventId',
                in: 'path',
                required: true,
                description: 'Event ID',
                schema: new OA\Schema(type: 'integer', example: 123)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of games for the event',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'g_id', type: 'integer', example: 456),
                            new OA\Property(property: 'c_code', type: 'string', example: 'N1'),
                            new OA\Property(property: 'd_phase', type: 'string', example: 'Poules'),
                            new OA\Property(property: 't_a_label', type: 'string', example: 'Team A'),
                            new OA\Property(property: 't_b_label', type: 'string', example: 'Team B'),
                            new OA\Property(property: 'g_score_a', type: 'integer', nullable: true, example: 5),
                            new OA\Property(property: 'g_score_b', type: 'integer', nullable: true, example: 3),
                            new OA\Property(property: 'g_date', type: 'string', example: '2025-01-15'),
                            new OA\Property(property: 'g_time', type: 'string', example: '10:00:00')
                        ]
                    )
                )
            )
        ]
    )]
    public function getGames(int $eventId): JsonResponse
    {
        return new UnicodeJsonResponse(
            $this->results->games(EventScopeFactory::fromEventId($eventId), ResultsFormat::Event)
        );
    }
}
