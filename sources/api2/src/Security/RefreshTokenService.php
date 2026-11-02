<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\DBAL\Connection;

/**
 * Refresh token service — AUTH_HARDENING.md § 3 (phase 1b).
 *
 * Chaîne de rotation : chaque usage d'un refresh token le révoque et en émet un nouveau,
 * dont l'id est stocké dans `replaced_by`. Si un refresh token déjà révoqué est présenté
 * (rejeu), on invalide toute la chaîne (AUTH-RT-03) : signe d'une fuite de cookie.
 *
 * Les tokens eux-mêmes ne sont jamais stockés — seul leur SHA-256 l'est.
 */
final class RefreshTokenService
{
    /** Durée de vie glissante (8 h) — alignée AUTH-CK-01. */
    public const TTL_SECONDS = 28800;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Émet un refresh token : chaîne 64 car base64url, inséré hashé.
     *
     * @return string Le token opaque à poser en cookie `kpi_refresh`.
     */
    public function issue(User $user, ?string $deviceFingerprint = null): string
    {
        $token = $this->randomToken();
        $this->connection->insert('kp_user_refresh_token', [
            'user_code' => $user->getCode(),
            'token_hash' => $this->hash($token),
            'device_fingerprint' => $deviceFingerprint,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'expires_at' => (new \DateTimeImmutable('+' . self::TTL_SECONDS . ' seconds'))->format('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * Tourne un refresh token : révoque celui présenté, émet un nouveau. Retourne {userCode, newToken}
     * ou null si le token est invalide (inconnu, expiré, révoqué). En cas de REJEU (token déjà
     * révoqué), toutes les sessions actives du compte sont tuées : la fuite est présumée réelle.
     *
     * @return array{userCode: string, token: string}|null
     */
    public function rotate(string $presentedToken): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_code, revoked_at, expires_at, device_fingerprint
             FROM kp_user_refresh_token WHERE token_hash = ?',
            [$this->hash($presentedToken)],
        );

        if (!$row) {
            return null; // inconnu
        }

        $now = new \DateTimeImmutable();
        if (new \DateTimeImmutable($row['expires_at']) < $now) {
            return null; // expiré
        }

        if ($row['revoked_at'] !== null) {
            // REJEU : un token déjà consommé est présenté → suspicion de vol. Révoque toute la chaîne.
            $this->revokeAllFor($row['user_code']);
            return null;
        }

        // Émet le successeur et marque l'ancien comme révoqué + replaced_by.
        $newToken = $this->randomToken();
        $this->connection->insert('kp_user_refresh_token', [
            'user_code' => $row['user_code'],
            'token_hash' => $this->hash($newToken),
            'device_fingerprint' => $row['device_fingerprint'],
            'created_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $now->modify('+' . self::TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s'),
        ]);
        $newId = (int) $this->connection->lastInsertId();

        $this->connection->update(
            'kp_user_refresh_token',
            [
                'revoked_at' => $now->format('Y-m-d H:i:s'),
                'replaced_by' => $newId,
            ],
            ['id' => $row['id']],
        );

        return ['userCode' => $row['user_code'], 'token' => $newToken];
    }

    public function revoke(string $token): void
    {
        $this->connection->executeStatement(
            'UPDATE kp_user_refresh_token SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL',
            [$this->hash($token)],
        );
    }

    public function revokeAllFor(string $userCode): void
    {
        $this->connection->executeStatement(
            'UPDATE kp_user_refresh_token SET revoked_at = NOW() WHERE user_code = ? AND revoked_at IS NULL',
            [$userCode],
        );
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }
}
