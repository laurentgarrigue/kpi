<?php

namespace App\Security;

use App\Service\NotificationService;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * Codes à 6 chiffres envoyés par e-mail (AUTH_HARDENING.md § 5, AUTH-2FA-05 et 08) : valables 10 minutes, à
 * usage unique, stockés hachés ; un nouvel envoi invalide le précédent ; 1 envoi par minute et 5 par heure.
 */
final class EmailOtpService
{
    /** Code qui prouve le second facteur actif (connexion, changement de méthode, désactivation…). */
    public const PURPOSE_PROOF = 'proof';
    /** Code qui confirme l'adresse lors de l'enrôlement par e-mail. */
    public const PURPOSE_ENROLL = 'enroll';

    public const VALIDITY_MINUTES = 10;
    private const MIN_SECONDS_BETWEEN_SENDS = 60;
    private const MAX_SENDS_PER_HOUR = 5;
    private const CODE_DIGITS = 6;
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly NotificationService $notifications,
    ) {
    }

    public function send(string $userCode, string $purpose): EmailOtpSendResult
    {
        $email = $this->validEmailOf($userCode);
        if ($email === null) {
            return EmailOtpSendResult::NoEmail;
        }
        if ($this->isThrottled($userCode)) {
            return EmailOtpSendResult::Throttled;
        }

        $now = $this->clock->now();
        $code = str_pad((string) random_int(0, 10 ** self::CODE_DIGITS - 1), self::CODE_DIGITS, '0', STR_PAD_LEFT);
        $this->invalidate($userCode, $purpose);
        $this->connection->insert('kp_user_email_otp', [
            'user_code' => $userCode,
            'purpose' => $purpose,
            'code_hash' => password_hash($code, PASSWORD_BCRYPT),
            'created_at' => $now->format(self::DATE_FORMAT),
            'expires_at' => $now->modify(sprintf('+%d minutes', self::VALIDITY_MINUTES))->format(self::DATE_FORMAT),
        ]);
        $this->notifications->sendSecondFactorCode($email, $code, self::VALIDITY_MINUTES);

        return EmailOtpSendResult::Sent;
    }

    /** Consomme le dernier code valide du compte pour ce but s'il correspond. */
    public function consume(string $userCode, string $purpose, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        $row = $this->connection->fetchAssociative(
            'SELECT id, code_hash FROM kp_user_email_otp
             WHERE user_code = ? AND purpose = ? AND used_at IS NULL AND expires_at > ?
             ORDER BY id DESC LIMIT 1',
            [$userCode, $purpose, $this->clock->now()->format(self::DATE_FORMAT)],
        );
        if ($row === false || !password_verify($code, (string) $row['code_hash'])) {
            return false;
        }

        $this->connection->executeStatement(
            'UPDATE kp_user_email_otp SET used_at = ? WHERE id = ?',
            [$this->clock->now()->format(self::DATE_FORMAT), $row['id']],
        );

        return true;
    }

    public function invalidate(string $userCode, ?string $purpose = null): void
    {
        $sql = 'UPDATE kp_user_email_otp SET used_at = ? WHERE user_code = ? AND used_at IS NULL';
        $params = [$this->clock->now()->format(self::DATE_FORMAT), $userCode];
        if ($purpose !== null) {
            $sql .= ' AND purpose = ?';
            $params[] = $purpose;
        }
        $this->connection->executeStatement($sql, $params);
    }

    public function validEmailOf(string $userCode): ?string
    {
        $mail = trim((string) $this->connection->fetchOne('SELECT Mail FROM kp_user WHERE Code = ?', [$userCode]));

        return filter_var($mail, FILTER_VALIDATE_EMAIL) === false ? null : $mail;
    }

    /** « a***@domaine » : rappelle à l'utilisateur où chercher, sans dévoiler l'adresse. */
    public function maskedEmailOf(string $userCode): ?string
    {
        $mail = $this->validEmailOf($userCode);
        if ($mail === null) {
            return null;
        }
        [$local, $domain] = explode('@', $mail, 2);

        return mb_substr($local, 0, 1) . '***@' . $domain;
    }

    private function isThrottled(string $userCode): bool
    {
        $now = $this->clock->now();
        $count = fn (string $since): int => (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM kp_user_email_otp WHERE user_code = ? AND created_at > ?',
            [$userCode, $since],
        );

        return $count($now->modify(sprintf('-%d seconds', self::MIN_SECONDS_BETWEEN_SENDS))->format(self::DATE_FORMAT)) > 0
            || $count($now->modify('-1 hour')->format(self::DATE_FORMAT)) >= self::MAX_SENDS_PER_HOUR;
    }
}
