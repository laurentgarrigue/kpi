<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class TokenAuthService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get and validate token from request
     *
     * Checks for token in:
     * 1. X-Auth-Token header
     * 2. Cookie (kpi_app)
     * 3. URL parameter (token)
     *
     * @param Request $request
     * @param string|null $tokenFromUrl Token from URL parameter (optional)
     * @param int|null $eventId Event ID to check access (optional)
     * @param int|null $maxProfile Maximum profile level allowed (inclusive, lower = higher privilege)
     * @return array{user: string, token: string, profile: int}|null Returns user data or null if invalid/unauthorized
     * @throws \RuntimeException with code 403 if token is valid but event or profile access is denied
     */
    public function validateToken(Request $request, ?string $tokenFromUrl = null, ?int $eventId = null, ?int $maxProfile = null): ?array
    {
        // Get token from multiple sources (priority order)
        $token = $request->headers->get('X-Auth-Token')
            ?? $request->cookies->get('kpi_app')
            ?? $tokenFromUrl;

        if (!$token) {
            return null;
        }

        $conn = $this->entityManager->getConnection();

        // Query user by token
        $sql = "SELECT ut.user, ut.token, ut.generated_at, u.Id_Evenement, u.Niveau
            FROM kp_user_token ut
            INNER JOIN kp_user u ON (ut.user = u.Code)
            WHERE ut.token = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $token);
        $result = $stmt->executeQuery();
        $row = $result->fetchAssociative();

        if (!$row) {
            return null;
        }

        // Check token expiration (10 days)
        $generatedAt = new \DateTime($row['generated_at']);
        $generatedAt->add(new \DateInterval('P10D'));
        $now = new \DateTime();

        if ($generatedAt < $now) {
            return null; // Token expired
        }

        // Check event access if eventId provided
        if ($eventId !== null) {
            $grantedEvents = array_filter(explode('|', trim($row['Id_Evenement'] ?? '', '|')));
            if (!in_array((string)$eventId, $grantedEvents, true)) {
                // Token is valid but user has no access to this event → 403, not 401
                throw new \RuntimeException('Forbidden', 403);
            }
        }

        // Check profile level if maxProfile provided (lower Niveau = higher privilege)
        if ($maxProfile !== null && (int)$row['Niveau'] > $maxProfile) {
            throw new \RuntimeException('Forbidden', 403);
        }

        return [
            'user' => $row['user'],
            'token' => $row['token'],
            'profile' => (int)$row['Niveau'],
        ];
    }

    /**
     * Create unauthorized response
     */
    public function createUnauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return new JsonResponse(['error' => $message], 401);
    }

    /**
     * Create forbidden response
     */
    public function createForbiddenResponse(string $message = 'Forbidden - Access denied to this event'): JsonResponse
    {
        return new JsonResponse(['error' => $message], 403);
    }
}
