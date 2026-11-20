<?php

namespace App\Security;

use App\Entity\User;
use App\Pdf\PdfQrCode;
use App\Service\NotificationService;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Second facteur d'un compte : état, décision au login, enrôlement, preuve, désactivation et réinitialisation
 * (AUTH_HARDENING.md § 5). L'état vit dans kp_user_second_factor (pas dans kp_user, partagée avec le legacy).
 */
final class SecondFactorService
{
    public const METHOD_TOTP = 'totp';
    public const METHOD_EMAIL = 'email';

    public const JOURNAL_ACTIVATION = 'Activation 2FA';
    public const JOURNAL_DEACTIVATION = 'Désactivation 2FA';
    public const JOURNAL_RESET = 'Réinitialisation 2FA';

    private const TOTP_ISSUER = 'KPI';
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly SecondFactorSettings $settings,
        private readonly SecondFactorSecretBox $secretBox,
        private readonly EmailOtpService $emailOtp,
        private readonly RecoveryCodes $recoveryCodes,
        private readonly TrustedDevices $trustedDevices,
        private readonly NotificationService $notifications,
    ) {
    }

    // ------------------------------------------------------------------ état et décision

    public function activeMethod(string $userCode): ?string
    {
        $method = $this->connection->fetchOne('SELECT method FROM kp_user_second_factor WHERE user_code = ?', [$userCode]);

        return is_string($method) ? $method : null;
    }

    public function maxProfile(User $user): int
    {
        /** @var list<int|string> $levels */
        $levels = $this->connection->fetchFirstColumn('SELECT niveau FROM kp_user_mandat WHERE user_code = ?', [$user->getCode()]);

        return SecondFactorPolicy::maxProfile($user->getNiveau(), array_map('intval', $levels));
    }

    /** Décision au login ; ouvre la grâce (date mémorisée) à la première connexion concernée (AUTH-2FA-02/03). */
    public function evaluate(User $user, Request $request): SecondFactorOutcome
    {
        $outcome = $this->outcomeFor($user, $this->trustedDevices->isTrusted($user->getCode(), $request));
        $since = $this->enrollmentRequiredSince($user->getCode());
        if ($outcome->decision === SecondFactorDecision::Grace && $since === null) {
            $this->connection->executeStatement(
                'INSERT INTO kp_user_second_factor (user_code, enrollment_required_since) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE enrollment_required_since = VALUES(enrollment_required_since)',
                [$user->getCode(), $outcome->graceStartedAt?->format(self::DATE_FORMAT)],
            );
        }

        return $outcome;
    }

    /**
     * Résumé envoyé à app4 avec la session (bannière de grâce, /profile/security).
     *
     * @return array{method: ?string, required: bool, enrollmentDueAt: ?string}
     */
    public function summary(User $user): array
    {
        return [
            'method' => $this->activeMethod($user->getCode()),
            'required' => SecondFactorPolicy::isRequiredFor($this->maxProfile($user)),
            'enrollmentDueAt' => $this->outcomeFor($user, false)->enrollmentDueAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function enforcedFrom(): ?\DateTimeImmutable
    {
        return $this->settings->enforcedFrom();
    }

    public function isTotpAvailable(): bool
    {
        return $this->secretBox->isConfigured();
    }

    // ------------------------------------------------------------------ preuve du second facteur actif

    /**
     * Vérifie (et consomme) un code du second facteur actif ou un code de récupération.
     *
     * @return ?string méthode journalisée (LoginAttemptLogger::METHOD_*), null si le code est refusé
     */
    public function prove(string $userCode, string $code): ?string
    {
        $method = $this->activeMethod($userCode);
        if ($method === null) {
            return null;
        }
        if (RecoveryCodes::looksLikeRecoveryCode($code)) {
            return $this->recoveryCodes->consume($userCode, $code) ? LoginAttemptLogger::METHOD_RECOVERY_CODE : null;
        }
        if ($method === self::METHOD_EMAIL) {
            return $this->emailOtp->consume($userCode, EmailOtpService::PURPOSE_PROOF, $code) ? LoginAttemptLogger::METHOD_EMAIL_OTP : null;
        }

        return $this->consumeTotp($userCode, 'totp_secret_encrypted', $code) ? LoginAttemptLogger::METHOD_TOTP : null;
    }

    public function sendProofCode(string $userCode): EmailOtpSendResult
    {
        return $this->emailOtp->send($userCode, EmailOtpService::PURPOSE_PROOF);
    }

    public function maskedEmail(string $userCode): ?string
    {
        return $this->emailOtp->maskedEmailOf($userCode);
    }

    // ------------------------------------------------------------------ enrôlement (AUTH-2FA-04/05)

    /** @return array{secret: string, otpauthUri: string, qrSvg: string} */
    public function beginTotp(string $userCode): array
    {
        $secret = Totp::generateSecret();
        $this->connection->executeStatement(
            'INSERT INTO kp_user_second_factor (user_code, pending_totp_secret_encrypted) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE pending_totp_secret_encrypted = VALUES(pending_totp_secret_encrypted)',
            [$userCode, $this->secretBox->encrypt($secret)],
        );
        $uri = Totp::provisioningUri($secret, $userCode, self::TOTP_ISSUER);

        return ['secret' => $secret, 'otpauthUri' => $uri, 'qrSvg' => PdfQrCode::svg($uri)];
    }

    /** @return ?list<string> codes de récupération, null si le code ne correspond pas au secret en attente */
    public function activateTotp(string $userCode, string $code): ?array
    {
        $timestep = $this->matchTotp($userCode, 'pending_totp_secret_encrypted', $code);
        if ($timestep === null) {
            return null;
        }
        $this->connection->executeStatement(
            "UPDATE kp_user_second_factor
             SET method = 'totp', totp_secret_encrypted = pending_totp_secret_encrypted, pending_totp_secret_encrypted = NULL,
                 totp_last_timestep = ?, activated_at = ?
             WHERE user_code = ?",
            [$timestep, $this->now(), $userCode],
        );

        return $this->afterActivation($userCode, 'Application TOTP');
    }

    public function beginEmail(string $userCode): EmailOtpSendResult
    {
        return $this->emailOtp->send($userCode, EmailOtpService::PURPOSE_ENROLL);
    }

    /** @return ?list<string> codes de récupération, null si le code est refusé */
    public function activateEmail(string $userCode, string $code): ?array
    {
        if (!$this->emailOtp->consume($userCode, EmailOtpService::PURPOSE_ENROLL, $code)) {
            return null;
        }
        $this->connection->executeStatement(
            "INSERT INTO kp_user_second_factor (user_code, method, activated_at) VALUES (?, 'email', ?)
             ON DUPLICATE KEY UPDATE method = 'email', totp_secret_encrypted = NULL, pending_totp_secret_encrypted = NULL,
                                     totp_last_timestep = NULL, activated_at = VALUES(activated_at)",
            [$userCode, $this->now()],
        );

        return $this->afterActivation($userCode, 'Code par e-mail');
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(string $userCode): array
    {
        $this->journal($userCode, self::JOURNAL_ACTIVATION, 'Codes de récupération régénérés');

        return $this->recoveryCodes->regenerate($userCode);
    }

    public function remainingRecoveryCodes(string $userCode): int
    {
        return $this->recoveryCodes->remainingFor($userCode);
    }

    // ------------------------------------------------------------------ désactivation, réinitialisation

    public function canDisable(User $user): bool
    {
        return !SecondFactorPolicy::isRequiredFor($this->maxProfile($user));
    }

    public function disable(string $userCode): void
    {
        $this->clear($userCode, null);
        $this->journal($userCode, self::JOURNAL_DEACTIVATION, 'Désactivation depuis /profile/security');
    }

    /** AUTH-2FA-15 : enrôlement exigé dès la connexion suivante, sans nouvelle grâce. */
    public function reset(string $userCode, string $resetBy): void
    {
        $graceAlreadyElapsed = $this->clock->now()->modify(sprintf('-%d days', SecondFactorPolicy::GRACE_DAYS));
        $this->clear($userCode, $graceAlreadyElapsed);
        $this->journal($userCode, self::JOURNAL_RESET, 'Réinitialisation par ' . $resetBy);
        $email = $this->emailOtp->validEmailOf($userCode);
        if ($email !== null) {
            $this->notifications->sendSecondFactorReset($email, $userCode);
        }
    }

    // ------------------------------------------------------------------ appareils de confiance

    public function trustedDevices(): TrustedDevices
    {
        return $this->trustedDevices;
    }

    // ================================================================== interne

    private function outcomeFor(User $user, bool $trustedDevice): SecondFactorOutcome
    {
        return SecondFactorPolicy::decide(
            maxProfile: $this->maxProfile($user),
            enforcedFrom: $this->settings->enforcedFrom(),
            activeMethod: $this->activeMethod($user->getCode()),
            enrollmentRequiredSince: $this->enrollmentRequiredSince($user->getCode()),
            trustedDevice: $trustedDevice,
            now: $this->clock->now(),
        );
    }

    private function enrollmentRequiredSince(string $userCode): ?\DateTimeImmutable
    {
        $since = $this->connection->fetchOne('SELECT enrollment_required_since FROM kp_user_second_factor WHERE user_code = ?', [$userCode]);

        return is_string($since) ? new \DateTimeImmutable($since) : null;
    }

    /** @return list<string> */
    private function afterActivation(string $userCode, string $methodLabel): array
    {
        $this->trustedDevices->revokeAll($userCode);
        $this->emailOtp->invalidate($userCode);
        $this->journal($userCode, self::JOURNAL_ACTIVATION, $methodLabel);

        return $this->recoveryCodes->regenerate($userCode);
    }

    private function consumeTotp(string $userCode, string $column, string $code): bool
    {
        $timestep = $this->matchTotp($userCode, $column, $code);
        if ($timestep === null) {
            return false;
        }
        $this->connection->executeStatement(
            'UPDATE kp_user_second_factor SET totp_last_timestep = ? WHERE user_code = ?',
            [$timestep, $userCode],
        );

        return true;
    }

    private function matchTotp(string $userCode, string $column, string $code): ?int
    {
        $row = $this->connection->fetchAssociative(
            "SELECT {$column} AS sealed, totp_last_timestep FROM kp_user_second_factor WHERE user_code = ?",
            [$userCode],
        );
        $secret = is_array($row) && is_string($row['sealed']) ? $this->secretBox->decrypt($row['sealed']) : null;
        if ($secret === null) {
            return null;
        }
        $lastUsed = $row['totp_last_timestep'] === null ? null : (int) $row['totp_last_timestep'];

        return Totp::verify($secret, $code, $this->clock->now()->getTimestamp(), $lastUsed);
    }

    private function clear(string $userCode, ?\DateTimeImmutable $enrollmentRequiredSince): void
    {
        $this->connection->executeStatement(
            'INSERT INTO kp_user_second_factor (user_code, enrollment_required_since) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE method = NULL, totp_secret_encrypted = NULL, pending_totp_secret_encrypted = NULL,
                                     totp_last_timestep = NULL, activated_at = NULL,
                                     enrollment_required_since = VALUES(enrollment_required_since)',
            [$userCode, $enrollmentRequiredSince?->format(self::DATE_FORMAT)],
        );
        $this->recoveryCodes->deleteFor($userCode);
        $this->trustedDevices->revokeAll($userCode);
        $this->emailOtp->invalidate($userCode);
    }

    private function journal(string $userCode, string $action, string $text): void
    {
        $this->connection->executeStatement(
            'INSERT INTO kp_journal (Dates, Users, Actions, Journal) VALUES (NOW(), ?, ?, ?)',
            [$userCode, $action, $text],
        );
    }

    private function now(): string
    {
        return $this->clock->now()->format(self::DATE_FORMAT);
    }
}
