<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * « Faire confiance à cet appareil 30 jours » (AUTH_HARDENING.md § 5, AUTH-2FA-11) : cookie `kpi_2fa_trust`
 * `{id}.{jeton}` dont seul le SHA-256 est stocké. Révocable depuis /profile/security ; tous les appareils
 * d'un compte sont révoqués au changement de mot de passe, au changement de méthode et à la réinitialisation.
 */
final class TrustedDevices
{
    public const COOKIE = 'kpi_2fa_trust';
    public const DAYS = 30;
    private const TOKEN_BYTES = 32;
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly KnownDeviceService $knownDevices,
    ) {
    }

    public function trust(string $userCode, Request $request): Cookie
    {
        $now = $this->clock->now();
        $expiresAt = $now->modify(sprintf('+%d days', self::DAYS));
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $this->connection->insert('kp_user_trusted_device', [
            'user_code' => $userCode,
            'device_token_hash' => hash('sha256', $token),
            'label' => $this->knownDevices->label($request),
            'created_at' => $now->format(self::DATE_FORMAT),
            'expires_at' => $expiresAt->format(self::DATE_FORMAT),
        ]);

        return Cookie::create(self::COOKIE)
            ->withValue($this->connection->lastInsertId() . '.' . $token)
            ->withExpires($expiresAt)
            ->withPath('/')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite('lax');
    }

    public function isTrusted(string $userCode, Request $request): bool
    {
        $parts = explode('.', (string) $request->cookies->get(self::COOKIE, ''), 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return false;
        }
        $hash = $this->connection->fetchOne(
            'SELECT device_token_hash FROM kp_user_trusted_device
             WHERE id = ? AND user_code = ? AND revoked_at IS NULL AND expires_at > ?',
            [(int) $parts[0], $userCode, $this->clock->now()->format(self::DATE_FORMAT)],
        );

        return is_string($hash) && hash_equals($hash, hash('sha256', $parts[1]));
    }

    /** @return list<array{id: int, label: string, createdAt: string, expiresAt: string}> */
    public function listActive(string $userCode): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, label, created_at, expires_at FROM kp_user_trusted_device
             WHERE user_code = ? AND revoked_at IS NULL AND expires_at > ? ORDER BY created_at DESC',
            [$userCode, $this->clock->now()->format(self::DATE_FORMAT)],
        );

        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'label' => (string) $row['label'],
            'createdAt' => (string) $row['created_at'],
            'expiresAt' => (string) $row['expires_at'],
        ], $rows);
    }

    public function revoke(string $userCode, int $deviceId): bool
    {
        return $this->connection->executeStatement(
            'UPDATE kp_user_trusted_device SET revoked_at = ? WHERE id = ? AND user_code = ? AND revoked_at IS NULL',
            [$this->clock->now()->format(self::DATE_FORMAT), $deviceId, $userCode],
        ) > 0;
    }

    public function revokeAll(string $userCode): void
    {
        $this->connection->executeStatement(
            'UPDATE kp_user_trusted_device SET revoked_at = ? WHERE user_code = ? AND revoked_at IS NULL',
            [$this->clock->now()->format(self::DATE_FORMAT), $userCode],
        );
    }
}
