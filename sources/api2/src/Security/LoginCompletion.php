<?php

namespace App\Security;

use App\Cms\EditorialAccess;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fin d'une connexion par mot de passe, commune à /auth/login, au second facteur et au renouvellement
 * (AUTH_HARDENING.md § 2bis et § 5) :
 *
 *   mot de passe bon → second facteur (défi ou enrôlement exigé) → renouvellement exigé → session.
 *
 * Le second facteur passe avant le renouvellement : sinon /auth/renew-password ouvrirait une session sans lui.
 */
final class LoginCompletion
{
    public const SECOND_FACTOR_REQUIRED = 'SECOND_FACTOR_REQUIRED';
    public const SECOND_FACTOR_ENROLLMENT_REQUIRED = 'SECOND_FACTOR_ENROLLMENT_REQUIRED';
    public const PASSWORD_RENEWAL_REQUIRED = 'PASSWORD_RENEWAL_REQUIRED';

    public function __construct(
        private readonly Connection $connection,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EditorialAccess $editorialAccess,
        private readonly LoginAttemptLogger $loginLogger,
        private readonly LoginRateLimiter $loginRateLimiter,
        private readonly RefreshTokenService $refreshTokens,
        private readonly KnownDeviceService $knownDevices,
        private readonly PasswordRenewalService $passwordRenewals,
        private readonly PasswordRenewalToken $renewalTokens,
        private readonly SecondFactorService $secondFactors,
        private readonly SecondFactorToken $secondFactorTokens,
        private readonly UserMandates $mandates,
    ) {
    }

    /** Le mot de passe vient d'être vérifié. */
    public function afterPassword(User $user, Request $request, string $username, bool $usedLegacyHash): JsonResponse
    {
        $outcome = $this->secondFactors->evaluate($user, $request);
        if ($outcome->decision === SecondFactorDecision::Challenge) {
            return new JsonResponse($this->challenge($user->getCode()), Response::HTTP_FORBIDDEN);
        }
        if ($outcome->decision === SecondFactorDecision::EnrollmentRequired) {
            return new JsonResponse($this->enrollmentRequired($user->getCode()), Response::HTTP_FORBIDDEN);
        }

        $this->loginLogger->logSuccess($user->getCode(), LoginAttemptLogger::METHOD_PASSWORD, $usedLegacyHash, $request);
        $this->loginRateLimiter->resetUser($username);

        return $this->beforeSession($user, $request);
    }

    /**
     * Le second facteur vient d'être prouvé (ou enrôlé avec un jeton d'enrôlement).
     *
     * @param array<string, mixed> $extra ajouté à la réponse (codes de récupération après un enrôlement)
     */
    public function afterSecondFactor(User $user, Request $request, string $method, array $extra = []): JsonResponse
    {
        $this->loginLogger->logSuccess($user->getCode(), $method, false, $request);
        $this->loginRateLimiter->resetUser($user->getCode());

        return $this->beforeSession($user, $request, $extra);
    }

    /**
     * Corps du 403 « code attendu » ; envoie le code si la méthode est l'e-mail.
     *
     * @return array<string, mixed>
     */
    public function challenge(string $userCode): array
    {
        $method = (string) $this->secondFactors->activeMethod($userCode);
        $body = [
            'message' => 'Second factor required',
            'code' => self::SECOND_FACTOR_REQUIRED,
            'method' => $method,
            'challengeToken' => $this->secondFactorTokens->create(SecondFactorToken::CHALLENGE, $userCode, $this->storedHash($userCode)),
        ];
        if ($method === SecondFactorService::METHOD_EMAIL) {
            $body['codeSent'] = $this->secondFactors->sendProofCode($userCode) === EmailOtpSendResult::Sent;
            $body['maskedEmail'] = $this->secondFactors->maskedEmail($userCode);
        }

        return $body;
    }

    /** @return array<string, mixed> corps du 403 « enrôlement exigé » */
    public function enrollmentRequired(string $userCode): array
    {
        return [
            'message' => 'Second factor enrollment required',
            'code' => self::SECOND_FACTOR_ENROLLMENT_REQUIRED,
            'enrollmentToken' => $this->secondFactorTokens->create(SecondFactorToken::ENROLL, $userCode, $this->storedHash($userCode)),
            'totpAvailable' => $this->secondFactors->isTotpAvailable(),
            'maskedEmail' => $this->secondFactors->maskedEmail($userCode),
        ];
    }

    /** @param array<string, mixed> $extra */
    public function openSession(User $user, Request $request, array $extra = []): JsonResponse
    {
        $mandates = $this->mandates->forUser($user->getCode());
        $this->journalConnection($user);

        $token = $this->jwtManager->create($user);
        $refreshToken = $this->refreshTokens->issue($user, $this->knownDevices->currentFingerprint($request));
        $this->knownDevices->recognizeAndNotify($user, $this->emailOf($user->getCode()), $request);

        $userData = $user->toArray();
        $userData['capabilities'] = $this->editorialAccess->capabilities($user->getCode());
        $userData['mandates'] = $mandates;
        $userData['activeMandate'] = null;
        $userData['effectiveProfile'] = $user->getNiveau();
        $userData['effectiveFilters'] = $userData['filters'];
        $userData['secondFactor'] = $this->secondFactors->summary($user);

        $response = new JsonResponse([
            'token' => $token, // conservé en body pendant la transition d'app4 vers le cookie (AUTH-CK-02)
            'user' => $userData,
            'hasMandates' => count($mandates) > 0,
            ...$extra,
        ]);
        $response->headers->setCookie(SessionCookies::access($token));
        $response->headers->setCookie(SessionCookies::refresh($refreshToken));

        return $response;
    }

    public function storedHash(string $userCode): string
    {
        $hash = $this->connection->fetchOne('SELECT Pwd FROM kp_user WHERE Code = ?', [$userCode]);

        return is_string($hash) ? $hash : '';
    }

    /** @param array<string, mixed> $extra */
    private function beforeSession(User $user, Request $request, array $extra = []): JsonResponse
    {
        if ($this->passwordRenewals->isRequired($user->getCode())) {
            return new JsonResponse([
                'message' => 'Password renewal required',
                'code' => self::PASSWORD_RENEWAL_REQUIRED,
                'renewalToken' => $this->renewalTokens->create($user->getCode(), $this->storedHash($user->getCode())),
                ...$extra,
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->openSession($user, $request, $extra);
    }

    private function journalConnection(User $user): void
    {
        try {
            $identite = trim($user->getPrenom() . ' ' . $user->getNom());
            $this->connection->executeStatement(
                "INSERT INTO kp_journal (Dates, Users, Actions, Journal) VALUES (NOW(), ?, 'Connexion Admin', ?)",
                [$user->getUserIdentifier(), $identite ?: $user->getUserIdentifier()],
            );
        } catch (\Exception) {
        }
    }

    private function emailOf(string $userCode): string
    {
        $mail = $this->connection->fetchOne('SELECT Mail FROM kp_user WHERE Code = ?', [$userCode]);

        return is_string($mail) ? trim($mail) : '';
    }
}
