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

class ChartsController extends AbstractController
{
    public function __construct(
        private readonly PublicResultsService $results
    ) {
    }

    #[Route('/event/{eventId}/charts', name: 'charts', methods: ['GET'])]
    #[OA\Get(
        path: '/event/{eventId}/charts',
        summary: 'Get rankings and brackets for an event',
        description: 'Returns complex tournament structure with pools, brackets, rankings, and game details',
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
                description: 'Returns tournament charts and rankings',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'code', type: 'string', example: 'N1'),
                            new OA\Property(property: 'libelle', type: 'string', example: 'Nationale 1'),
                            new OA\Property(property: 'type', type: 'string', example: 'CHPT'),
                            new OA\Property(property: 'rounds', type: 'object', description: 'Tournament rounds structure'),
                            new OA\Property(
                                property: 'ranking',
                                type: 'array',
                                items: new OA\Items(type: 'object'),
                                description: 'Team rankings'
                            )
                        ]
                    )
                )
            )
        ]
    )]
    public function getCharts(int $eventId): JsonResponse
    {
        return new UnicodeJsonResponse(
            $this->results->charts(EventScopeFactory::fromEventId($eventId), ResultsFormat::Event)
        );
    }
}
