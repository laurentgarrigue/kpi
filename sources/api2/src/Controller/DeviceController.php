<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\KnownDeviceService;
use App\Security\RefreshTokenService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion utilisateur des appareils et sessions — AUTH_HARDENING.md § 3bis (phase 1b-bis).
 *
 * Complète la révocation par lien email (`AdminAuthController::revokeDevice`) : ces endpoints-ci
 * exigent un JWT, et scopent toujours la lecture et l'écriture au compte authentifié. L'id d'un
 * appareil d'un autre utilisateur n'existe pas pour nous — 404 (pas 403, qui divulguerait son
 * existence).
 */
#[Route('/auth/devices')]
#[OA\Tag(name: '20. App4 - Authentication')]
class DeviceController extends AbstractController
{
    public function __construct(
        private readonly KnownDeviceService $knownDeviceService,
        private readonly RefreshTokenService $refreshTokenService,
    ) {
    }

    /**
     * Liste les appareils actifs du compte, celui qui porte la requête marqué `isCurrent: true`.
     * Pas de refresh token exposé — on ne vend pas la surface de rejeu dans la réponse.
     */
    #[Route('', name: 'api_auth_devices_list', methods: ['GET'])]
    #[OA\Get(path: '/auth/devices', summary: 'List active devices of the authenticated user', tags: ['20. App4 - Authentication'])]
    #[OA\Response(response: 200, description: 'Devices list')]
    public function list(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $currentFp = $this->knownDeviceService->currentFingerprint($request);
        $devices = $this->knownDeviceService->listActiveFor($user->getCode());

        $items = array_map(fn(array $d) => [
            'id' => $d['id'],
            'label' => $d['label'],
            'createdAt' => $d['created_at'],
            'lastSeenAt' => $d['last_seen_at'],
            'isCurrent' => $d['fingerprint'] === $currentFp,
        ], $devices);

        return $this->json(['devices' => $items]);
    }

    /**
     * Révoque un appareil du compte authentifié. Idempotent : 200 si déjà révoqué, 404 si l'id
     * est inconnu OU appartient à un autre utilisateur (anti-énumération).
     */
    #[Route('/{id}/revoke', name: 'api_auth_devices_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(path: '/auth/devices/{id}/revoke', summary: 'Revoke a specific device of the authenticated user', tags: ['20. App4 - Authentication'])]
    #[OA\Response(response: 200, description: 'Device revoked (or already was)')]
    #[OA\Response(response: 404, description: 'Device not found for this user')]
    public function revoke(int $id, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $currentFp = $this->knownDeviceService->currentFingerprint($request);

        $revoked = $this->knownDeviceService->revokeById(
            $id,
            $user->getCode(),
            $this->refreshTokenService,
            'Révocation ciblée depuis /profile/security',
        );

        if (!$revoked) {
            // Peut être « déjà révoqué » : on vérifie que la ligne existe pour cet utilisateur
            // sinon on renvoie 404. On ne divulgue jamais l'existence d'une ligne d'un autre user.
            $exists = (int) $this->knownDeviceService->countAnyFor($user->getCode(), $id);
            if ($exists === 0) {
                return $this->json(['message' => 'Device not found'], Response::HTTP_NOT_FOUND);
            }
        }

        // La révocation de l'appareil courant déconnecte immédiatement l'utilisateur du navigateur
        // qui porte cette requête — on renvoie l'info à l'app4 pour qu'elle efface l'état local.
        return $this->json([
            'revoked' => true,
            'currentDeviceRevoked' => $this->currentDeviceRevokedById($user->getCode(), $id, $currentFp),
        ]);
    }

    /**
     * Révoque tous les appareils actifs du compte sauf celui qui porte la requête (AUTH-DV-05).
     */
    #[Route('/revoke-others', name: 'api_auth_devices_revoke_others', methods: ['POST'])]
    #[OA\Post(path: '/auth/devices/revoke-others', summary: 'Revoke every device of the user except the current one', tags: ['20. App4 - Authentication'])]
    #[OA\Response(response: 200, description: 'Other devices revoked (count)')]
    public function revokeOthers(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $currentFp = $this->knownDeviceService->currentFingerprint($request);
        $count = $this->knownDeviceService->revokeAllOthersFor($user->getCode(), $currentFp, $this->refreshTokenService);

        return $this->json(['revoked' => $count]);
    }

    private function requireUser(): User
    {
        /** @var User|null $u */
        $u = $this->getUser();
        if (!$u) {
            throw $this->createAccessDeniedException();
        }
        return $u;
    }

    /**
     * L'appareil qu'on vient de révoquer est-il celui depuis lequel l'utilisateur a appelé ?
     * Si oui, app4 doit nettoyer l'état local et proposer un relogin.
     */
    private function currentDeviceRevokedById(string $userCode, int $deviceId, string $currentFingerprint): bool
    {
        $fp = $this->knownDeviceService->fingerprintOf($userCode, $deviceId);
        return $fp !== null && $fp === $currentFingerprint;
    }
}
