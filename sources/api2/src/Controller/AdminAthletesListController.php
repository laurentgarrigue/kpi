<?php

namespace App\Controller;

use App\Athlete\AthletesListFilters;
use App\Athlete\AthletesListRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Paginated list of athletes, to find the players whose birth date is still to be completed
 * (CATEGORIE_AGE.md stories 28-30, PAGE_ATHLETES.md § 2.6 and § 6.8).
 * Profile <= 10 (ROLE_USER) like the athlete sheet and GET /admin/athletes/search: PAGE_ATHLETES.md § 2.6 and § 11.1.
 */
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: '28. App4 - Athletes')]
final class AdminAthletesListController extends AbstractController
{
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 200;

    public function __construct(private readonly AthletesListRepository $athletes)
    {
    }

    #[Route('/admin/athletes', name: 'admin_athletes_list', methods: ['GET'])]
    #[OA\Get(path: '/admin/athletes', summary: 'Paginated list of athletes with combinable filters and the total', tags: ['28. App4 - Athletes'])]
    #[OA\Parameter(name: 'q', in: 'query', required: false, description: 'Name, first name or licence number (min 2 chars)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'unknown_birthdate', in: 'query', required: false, description: '1 = birth date unknown (NULL or a conventional 1901-01-01 / 0000-00-00 date)', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'international', in: 'query', required: false, description: '1 = international player (Matric >= 2000000)', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'engaged_this_season', in: 'query', required: false, description: '1 = in a composition of a team of the active season', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number (default 1)', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Page size (default 50, max 200)', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: '{items, total, page, limit}')]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt('limit', self::DEFAULT_LIMIT)));
        $filters = new AthletesListFilters(
            search: $request->query->getString('q'),
            unknownBirthDate: $request->query->getBoolean('unknown_birthdate'),
            international: $request->query->getBoolean('international'),
            engagedThisSeason: $request->query->getBoolean('engaged_this_season'),
        );

        return $this->json($this->athletes->page($filters, $page, $limit) + ['page' => $page, 'limit' => $limit]);
    }
}
