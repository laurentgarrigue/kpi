<?php

namespace App\Controller;

use App\Cms\App3CachePurger;
use App\Cms\ValidationErrors;
use App\Entity\User;
use App\Http\UnicodeJsonResponse;
use App\Trait\AdminLoggableTrait;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Socle des contrôleurs de la rubrique Éditorial d'app4 (FEATURE_CMS.md § 5.2) : lecture du JSON, réponses
 * d'erreur, journal d'administration (`kp_journal`, CMS-03) et purge du cache d'app3 (CMS-07).
 */
abstract class AdminCmsBaseController extends AbstractController
{
    use AdminLoggableTrait;

    public function __construct(
        protected readonly Connection $connection,
        private readonly App3CachePurger $purger,
    ) {
    }

    /** @return array<string, mixed> */
    protected static function body(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    protected function userCode(): string
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getCode() : 'system';
    }

    /** Journalise l'action (libellé ≤ 40 caractères, comme les autres écrans d'app4). */
    protected function journal(string $action, string $details): void
    {
        $this->logActionForSeason(mb_substr($action, 0, 40), null, $details);
    }

    /** @param list<string> $paths */
    protected function purge(array $paths): void
    {
        $this->purger->purge($paths);
    }

    protected static function pageNumber(Request $request): int
    {
        $page = filter_var($request->query->get('page', 1), FILTER_VALIDATE_INT);

        return is_int($page) && $page > 0 ? $page : 1;
    }

    protected function validationFailed(ValidationErrors $errors): JsonResponse
    {
        return new UnicodeJsonResponse(['error' => 'validation_failed', 'errors' => $errors->all()], 400);
    }

    protected function notFound(): JsonResponse
    {
        return new UnicodeJsonResponse(['error' => 'not_found'], 404);
    }

    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return new UnicodeJsonResponse($data, $status);
    }
}
