<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\CompetitionSchemaService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Schema Controller
 *
 * Read-only visualization of competition phases: pool rankings and elimination brackets (CompetitionSchemaService).
 * Migrated from GestionSchema.php; its PDF is the `schema` document of PdfController.
 *
 * ROLE_VIEWER (niveau <= 8, consultation) explicitly, not a class-level #[IsGranted]: this
 * used to carry #[IsGranted('ROLE_DIVISION')] at class level, wrongly blocking niveau 8
 * (mandate or base profile) even for this read-only visualization. See
 * DOC/developer/reference/PROFILE_ROLES.md for the general #[IsGranted] cumulative pitfall.
 */
#[Route('/admin/schema')]
#[OA\Tag(name: '31. App4 - Schema')]
class AdminSchemaController extends AbstractController
{
    public function __construct(
        private readonly CompetitionSchemaService $schemas,
    ) {
    }

    // ─────────────────────────────────────────────
    // GET /admin/schema — Read competition schema
    // ─────────────────────────────────────────────

    #[Route('', name: 'admin_schema_list', methods: ['GET'])]
    #[IsGranted('ROLE_VIEWER')]
    public function list(Request $request): JsonResponse
    {
        $season = $request->query->get('season', '');
        $competition = $request->query->get('competition', '');
        $lang = $request->query->get('lang', 'fr');
        $includeEmpty = $request->query->getBoolean('includeEmpty');

        if (empty($season) || empty($competition)) {
            return $this->json(['message' => 'Season and competition are required'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $allowedCompetitions = $user?->getAllowedCompetitions();
        if ($allowedCompetitions !== null && !in_array($competition, $allowedCompetitions)) {
            return $this->json(['message' => 'Access denied to this competition'], Response::HTTP_FORBIDDEN);
        }

        $schema = $this->schemas->schema($season, $competition, $lang, $includeEmpty);
        if ($schema === null) {
            return $this->json(['message' => 'Competition not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($schema);
    }
}
