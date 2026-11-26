<?php

namespace App\Controller;

use App\Discipline\CardSuspensionRepository;
use App\Discipline\CardSuspensionService;
use App\Discipline\SuspensionCatalog;
use App\Discipline\SuspensionLiftRejected;
use App\Trait\AdminLoggableTrait;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Suspensions détectées par les cumuls de cartons (FEATURE_CARD_SUSPENSIONS.md S14, SUS-17) : profils 1–2. */
#[Route('/admin/card-suspensions')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin - Cumuls de cartons')]
final class AdminCardSuspensionsController extends AbstractController
{
    use AdminLoggableTrait;

    private const SEASON_PATTERN = '/^\d{4}$/';

    public function __construct(
        private readonly CardSuspensionRepository $suspensions,
        private readonly CardSuspensionService $service,
        private readonly Connection $connection,
    ) {
    }

    #[Route('/{id}/lift', name: 'admin_card_suspensions_lift', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(path: '/admin/card-suspensions/{id}/lift', summary: "Lever une suspension en vigueur : le joueur retrouve son statut d'avant (S19)")]
    public function lift(int $id): JsonResponse
    {
        try {
            $row = $this->service->lift($id, $this->getUser()?->getUserIdentifier() ?? '');
        } catch (SuspensionLiftRejected $e) {
            return $this->json(['error' => $e->reason], $e->status);
        }
        $this->logActionForCompetition('Levée suspension', (string) $row['saison'], (string) $row['code_competition'], sprintf('Suspension %d - Joueur %d', $id, $row['matric']));

        return $this->json(['id' => $id, 'state' => CardSuspensionRepository::state($row), 'liftedAt' => $row['levee_le'], 'liftedBy' => $row['levee_par']]);
    }

    #[Route('', name: 'admin_card_suspensions_list', methods: ['GET'])]
    #[OA\Get(path: '/admin/card-suspensions', summary: 'Suspensions de la saison (en vigueur et annulées) et règles du catalogue en vigueur')]
    #[OA\Parameter(name: 'season', in: 'query', required: true, schema: new OA\Schema(type: 'string', pattern: '^\d{4}$'))]
    public function list(Request $request): JsonResponse
    {
        $season = (string) $request->query->get('season', '');
        if (!preg_match(self::SEASON_PATTERN, $season)) {
            return $this->json(['error' => 'invalid'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'season' => $season,
            'catalog' => SuspensionCatalog::forSeason($season)?->toArray(),
            'suspensions' => $this->suspensions->list($season),
        ]);
    }
}
