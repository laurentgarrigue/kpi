<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\EmailOtpSendResult;
use App\Security\LoginAttemptLogger;
use App\Security\LoginCompletion;
use App\Security\SecondFactorService;
use App\Security\SecondFactorToken;
use App\Security\TrustedDevices;
use App\Security\UserProvider;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * Second facteur (AUTH_HARDENING.md § 5).
 *
 * - Routes publiques (preuve = jeton signé remis par /auth/login) : /verify, /email/send, /enroll/*.
 * - Routes JWT (gestion depuis /profile/security) : état, enrôlement, codes de récupération, désactivation,
 *   appareils de confiance, réinitialisation par un super-admin.
 */
#[Route('/auth/2fa')]
#[OA\Tag(name: '20. App4 - Authentication')]
class SecondFactorController extends AbstractController
{
    private const SUPER_ADMIN_PROFILE = 1;

    public function __construct(
        private readonly SecondFactorService $secondFactors,
        private readonly SecondFactorToken $tokens,
        private readonly LoginCompletion $loginCompletion,
        private readonly LoginAttemptLogger $loginLogger,
        private readonly UserProvider $userProvider,
        private readonly TrustedDevices $trustedDevices,
    ) {
    }

    // ================================================================== connexion (jeton de défi)

    #[Route('/verify', name: 'api_auth_2fa_verify', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/verify', summary: 'Prove the second factor (TOTP, e-mail or recovery code) and open the session', tags: ['20. App4 - Authentication'])]
    #[OA\Response(response: 200, description: 'Session opened, same payload and cookies as /auth/login')]
    #[OA\Response(response: 401, description: 'INVALID_TOKEN or INVALID_CODE')]
    #[OA\Response(response: 403, description: 'PASSWORD_RENEWAL_REQUIRED (renewalToken)')]
    #[OA\Response(response: 423, description: 'Account locked after too many failures')]
    public function verify(Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $user = $this->userFromToken((string) ($data['challengeToken'] ?? ''), SecondFactorToken::CHALLENGE);
        if ($user === null) {
            return $this->invalidToken();
        }
        $lockSecondsLeft = $this->loginLogger->lockSecondsLeft($user->getCode());
        if ($lockSecondsLeft !== null) {
            $this->loginLogger->logFailure($user->getCode(), LoginAttemptLogger::REASON_ACCOUNT_LOCKED, $request);
            return $this->json(['message' => 'Account temporarily locked after too many failed attempts', 'code' => 'ACCOUNT_LOCKED'], Response::HTTP_LOCKED, ['Retry-After' => (string) $lockSecondsLeft]);
        }

        $method = $this->secondFactors->prove($user->getCode(), (string) ($data['code'] ?? ''));
        if ($method === null) {
            $this->loginLogger->logFailure($user->getCode(), LoginAttemptLogger::REASON_BAD_SECOND_FACTOR, $request, $this->failedMethodOf($user->getCode()));
            return $this->invalidCode();
        }

        $response = $this->loginCompletion->afterSecondFactor($user, $request, $method);
        if (($data['trustDevice'] ?? false) === true && $response->isSuccessful()) {
            $response->headers->setCookie($this->trustedDevices->trust($user->getCode(), $request));
        }

        return $response;
    }

    #[Route('/email/send', name: 'api_auth_2fa_email_send', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/email/send', summary: 'Send a new e-mail code during sign-in (1 per minute, 5 per hour)', tags: ['20. App4 - Authentication'])]
    public function sendLoginCode(Request $request): JsonResponse
    {
        $user = $this->userFromToken((string) ($this->payload($request)['challengeToken'] ?? ''), SecondFactorToken::CHALLENGE);
        if ($user === null) {
            return $this->invalidToken();
        }

        return $this->sendResult($this->secondFactors->sendProofCode($user->getCode()), $user->getCode());
    }

    // ================================================================== enrôlement exigé (jeton d'enrôlement)

    #[Route('/enroll/totp/setup', name: 'api_auth_2fa_enroll_totp_setup', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/enroll/totp/setup', summary: 'Start a TOTP enrollment with the enrollment token', tags: ['20. App4 - Authentication'])]
    public function enrollTotpSetup(Request $request): JsonResponse
    {
        $user = $this->enrollingUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return $this->totpSetup($user->getCode());
    }

    #[Route('/enroll/totp/activate', name: 'api_auth_2fa_enroll_totp_activate', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/enroll/totp/activate', summary: 'Confirm the TOTP enrollment and open the session (returns the recovery codes)', tags: ['20. App4 - Authentication'])]
    public function enrollTotpActivate(Request $request): JsonResponse
    {
        $user = $this->enrollingUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $codes = $this->secondFactors->activateTotp($user->getCode(), (string) ($this->payload($request)['code'] ?? ''));
        if ($codes === null) {
            return $this->invalidCode();
        }

        return $this->loginCompletion->afterSecondFactor($user, $request, LoginAttemptLogger::METHOD_TOTP, ['recoveryCodes' => $codes]);
    }

    #[Route('/enroll/email/setup', name: 'api_auth_2fa_enroll_email_setup', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/enroll/email/setup', summary: 'Start an e-mail enrollment with the enrollment token (sends a code)', tags: ['20. App4 - Authentication'])]
    public function enrollEmailSetup(Request $request): JsonResponse
    {
        $user = $this->enrollingUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return $this->sendResult($this->secondFactors->beginEmail($user->getCode()), $user->getCode());
    }

    #[Route('/enroll/email/activate', name: 'api_auth_2fa_enroll_email_activate', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/enroll/email/activate', summary: 'Confirm the e-mail enrollment and open the session (returns the recovery codes)', tags: ['20. App4 - Authentication'])]
    public function enrollEmailActivate(Request $request): JsonResponse
    {
        $user = $this->enrollingUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $codes = $this->secondFactors->activateEmail($user->getCode(), (string) ($this->payload($request)['code'] ?? ''));
        if ($codes === null) {
            return $this->invalidCode();
        }

        return $this->loginCompletion->afterSecondFactor($user, $request, LoginAttemptLogger::METHOD_EMAIL_OTP, ['recoveryCodes' => $codes]);
    }

    // ================================================================== gestion depuis /profile/security (JWT)

    #[Route('', name: 'api_auth_2fa_status', methods: ['GET'])]
    #[OA\Get(path: '/auth/2fa', summary: 'Second factor status of the current account', tags: ['20. App4 - Authentication'])]
    public function status(): JsonResponse
    {
        $user = $this->currentUser();
        $code = $user->getCode();

        return $this->json([
            ...$this->secondFactors->summary($user),
            'maxProfile' => $this->secondFactors->maxProfile($user),
            'enforcedFrom' => $this->secondFactors->enforcedFrom()?->format('Y-m-d'),
            'canDisable' => $this->secondFactors->canDisable($user),
            'totpAvailable' => $this->secondFactors->isTotpAvailable(),
            'maskedEmail' => $this->secondFactors->maskedEmail($code),
            'recoveryCodesLeft' => $this->secondFactors->remainingRecoveryCodes($code),
            'trustedDevices' => $this->trustedDevices->listActive($code),
        ]);
    }

    #[Route('/email/code', name: 'api_auth_2fa_email_code', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/email/code', summary: 'Send an e-mail code proving the current factor (before a change, a deactivation or new recovery codes)', tags: ['20. App4 - Authentication'])]
    public function sendProofCode(): JsonResponse
    {
        $code = $this->currentUser()->getCode();

        return $this->sendResult($this->secondFactors->sendProofCode($code), $code);
    }

    #[Route('/totp/setup', name: 'api_auth_2fa_totp_setup', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/totp/setup', summary: 'Start a TOTP enrollment (currentCode required when a factor is already active)', tags: ['20. App4 - Authentication'])]
    public function totpSetupForCurrentUser(Request $request): JsonResponse
    {
        $code = $this->currentUser()->getCode();
        $refusal = $this->refuseWithoutCurrentFactor($code, $request);

        return $refusal ?? $this->totpSetup($code);
    }

    #[Route('/totp/activate', name: 'api_auth_2fa_totp_activate', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/totp/activate', summary: 'Confirm the TOTP enrollment (returns the recovery codes)', tags: ['20. App4 - Authentication'])]
    public function totpActivate(Request $request): JsonResponse
    {
        $codes = $this->secondFactors->activateTotp($this->currentUser()->getCode(), (string) ($this->payload($request)['code'] ?? ''));

        return $codes === null ? $this->invalidCode() : $this->json(['recoveryCodes' => $codes]);
    }

    #[Route('/email/setup', name: 'api_auth_2fa_email_setup', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/email/setup', summary: 'Start an e-mail enrollment (currentCode required when a factor is already active)', tags: ['20. App4 - Authentication'])]
    public function emailSetup(Request $request): JsonResponse
    {
        $code = $this->currentUser()->getCode();
        $refusal = $this->refuseWithoutCurrentFactor($code, $request);

        return $refusal ?? $this->sendResult($this->secondFactors->beginEmail($code), $code);
    }

    #[Route('/email/activate', name: 'api_auth_2fa_email_activate', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/email/activate', summary: 'Confirm the e-mail enrollment (returns the recovery codes)', tags: ['20. App4 - Authentication'])]
    public function emailActivate(Request $request): JsonResponse
    {
        $codes = $this->secondFactors->activateEmail($this->currentUser()->getCode(), (string) ($this->payload($request)['code'] ?? ''));

        return $codes === null ? $this->invalidCode() : $this->json(['recoveryCodes' => $codes]);
    }

    #[Route('/recovery-codes', name: 'api_auth_2fa_recovery_codes', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/recovery-codes', summary: 'Replace the recovery codes (code of the current factor required)', tags: ['20. App4 - Authentication'])]
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $userCode = $this->currentUser()->getCode();
        if ($this->secondFactors->prove($userCode, (string) ($this->payload($request)['code'] ?? '')) === null) {
            return $this->invalidCode();
        }

        return $this->json(['recoveryCodes' => $this->secondFactors->regenerateRecoveryCodes($userCode)]);
    }

    #[Route('/disable', name: 'api_auth_2fa_disable', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/disable', summary: 'Turn the second factor off (refused up to profile 4, mandates included)', tags: ['20. App4 - Authentication'])]
    #[OA\Response(response: 409, description: 'SECOND_FACTOR_MANDATORY')]
    public function disable(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        if (!$this->secondFactors->canDisable($user)) {
            return $this->json(['message' => 'The second factor is mandatory for this account', 'code' => 'SECOND_FACTOR_MANDATORY'], Response::HTTP_CONFLICT);
        }
        if ($this->secondFactors->prove($user->getCode(), (string) ($this->payload($request)['code'] ?? '')) === null) {
            return $this->invalidCode();
        }
        $this->secondFactors->disable($user->getCode());

        return $this->json(['message' => 'Second factor disabled']);
    }

    #[Route('/trusted-devices/{id}/revoke', name: 'api_auth_2fa_trusted_device_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(path: '/auth/2fa/trusted-devices/{id}/revoke', summary: 'Stop trusting a device (the code will be asked again)', tags: ['20. App4 - Authentication'])]
    public function revokeTrustedDevice(int $id): JsonResponse
    {
        if (!$this->trustedDevices->revoke($this->currentUser()->getCode(), $id)) {
            return $this->json(['message' => 'Unknown trusted device'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['message' => 'Trusted device revoked']);
    }

    #[Route('/reset/{code}', name: 'api_auth_2fa_reset', methods: ['POST'])]
    #[OA\Post(path: '/auth/2fa/reset/{code}', summary: 'Super-admin: remove the second factor of an account (lost phone); enrollment required at its next sign-in', tags: ['20. App4 - Authentication'])]
    #[OA\Response(response: 403, description: 'Not a super-admin, or SECOND_FACTOR_REQUIRED_FOR_RESET')]
    public function reset(string $code): JsonResponse
    {
        $me = $this->currentUser();
        if ($me->getNiveau() > self::SUPER_ADMIN_PROFILE) {
            return $this->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        if ($this->secondFactors->activeMethod($me->getCode()) === null) {
            return $this->json([
                'message' => 'Activate your own second factor before resetting another account',
                'code' => 'SECOND_FACTOR_REQUIRED_FOR_RESET',
            ], Response::HTTP_FORBIDDEN);
        }
        try {
            $target = $this->userProvider->loadUserByIdentifier($code);
        } catch (UserNotFoundException) {
            $target = null;
        }
        if (!$target instanceof User) {
            return $this->json(['message' => 'Unknown user'], Response::HTTP_NOT_FOUND);
        }
        $this->secondFactors->reset($target->getCode(), $me->getCode());

        return $this->json(['message' => 'Second factor reset']);
    }

    // ================================================================== interne

    private function totpSetup(string $userCode): JsonResponse
    {
        if (!$this->secondFactors->isTotpAvailable()) {
            return $this->json(['message' => 'TOTP is not configured on this server', 'code' => 'SECOND_FACTOR_NOT_CONFIGURED'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json($this->secondFactors->beginTotp($userCode));
    }

    /** AUTH-2FA-14 : changer de méthode exige un code de la méthode actuelle (ou un code de récupération). */
    private function refuseWithoutCurrentFactor(string $userCode, Request $request): ?JsonResponse
    {
        if ($this->secondFactors->activeMethod($userCode) === null) {
            return null;
        }
        $proved = $this->secondFactors->prove($userCode, (string) ($this->payload($request)['currentCode'] ?? ''));

        return $proved === null ? $this->invalidCode() : null;
    }

    /** Utilisateur porté par un jeton d'enrôlement valide, qui n'a pas encore de second facteur actif. */
    private function enrollingUser(Request $request): User|JsonResponse
    {
        $user = $this->userFromToken((string) ($this->payload($request)['enrollmentToken'] ?? ''), SecondFactorToken::ENROLL);
        if ($user === null) {
            return $this->invalidToken();
        }
        if ($this->secondFactors->activeMethod($user->getCode()) !== null) {
            return $this->json(['message' => 'A second factor is already active', 'code' => 'SECOND_FACTOR_ALREADY_ACTIVE'], Response::HTTP_CONFLICT);
        }

        return $user;
    }

    private function userFromToken(string $token, string $purpose): ?User
    {
        $userCode = $this->tokens->userCode($token);
        if ($userCode === null || !$this->tokens->isValid($token, $purpose, $this->loginCompletion->storedHash($userCode))) {
            return null;
        }
        try {
            $user = $this->userProvider->loadUserByIdentifier($userCode);
        } catch (UserNotFoundException) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    private function sendResult(EmailOtpSendResult $result, string $userCode): JsonResponse
    {
        return match ($result) {
            EmailOtpSendResult::Sent => $this->json(['codeSent' => true, 'maskedEmail' => $this->secondFactors->maskedEmail($userCode)]),
            EmailOtpSendResult::NoEmail => $this->json(['message' => 'No valid e-mail address on this account', 'code' => 'EMAIL_REQUIRED'], Response::HTTP_BAD_REQUEST),
            EmailOtpSendResult::Throttled => $this->json(['message' => 'Please wait before asking for a new code', 'code' => 'TOO_MANY_CODES'], Response::HTTP_TOO_MANY_REQUESTS),
        };
    }

    private function failedMethodOf(string $userCode): string
    {
        return $this->secondFactors->activeMethod($userCode) === SecondFactorService::METHOD_EMAIL
            ? LoginAttemptLogger::METHOD_EMAIL_OTP
            : LoginAttemptLogger::METHOD_TOTP;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    private function invalidToken(): JsonResponse
    {
        return $this->json(['message' => 'Invalid or expired token', 'code' => 'INVALID_TOKEN'], Response::HTTP_UNAUTHORIZED);
    }

    private function invalidCode(): JsonResponse
    {
        return $this->json(['message' => 'Invalid code', 'code' => 'INVALID_CODE'], Response::HTTP_UNAUTHORIZED);
    }
}
