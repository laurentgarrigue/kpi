<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Refresh token service — AUTH_HARDENING.md § 3 (phase 1b).
 *
 * Chaîne de rotation : chaque usage d'un refresh token le révoque et en émet un nouveau,
 * dont l'id est stocké dans `replaced_by`. Un token déjà consommé présenté de nouveau est jugé par
 * {@see RefreshTokenReuse} (AUTH_HARDENING.md § 3ter.4) : doublon du même appareil dans les 60 s → nouveau
 * successeur ; sinon rejeu → les sessions de l'appareil de la chaîne sont révoquées (AUTH-RT-03, AUTH-SC-12).
 *
 * Les tokens eux-mêmes ne sont jamais stockés — seul leur SHA-256 l'est.
 */
final class RefreshTokenService
{
    /** Bound on the walk to the end of a rotation chain: 60 s of grace cannot produce more links. */
    private const MAX_CHAIN_HOPS = 10;

    public function __construct(
        private readonly Connection $connection,
        /** Sliding lifetime of a refresh token, in seconds (AUTH-SC-13, `REFRESH_TOKEN_TTL`). */
        #[Autowire('%refresh_token_ttl%')]
        private readonly int $ttlSeconds,
    ) {
    }

    public function ttlSeconds(): int
    {
        return $this->ttlSeconds;
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
            'expires_at' => $this->expiryFrom(new \DateTimeImmutable()),
        ]);

        return $token;
    }

    /**
     * Tourne un refresh token. Retourne {userCode, token} (le successeur à poser en cookie) ou null si le token
     * est refusé (inconnu, expiré, session close, rejeu). Un rejeu révoque les sessions de l'appareil de la chaîne.
     *
     * @param string|null $requestFingerprint empreinte de l'appareil qui présente le token (KnownDeviceService)
     * @return array{userCode: string, token: string}|null
     */
    public function rotate(string $presentedToken, ?string $requestFingerprint = null): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_code, revoked_at, expires_at, device_fingerprint, replaced_by
             FROM kp_user_refresh_token WHERE token_hash = ?',
            [$this->hash($presentedToken)],
        );
        if (!$row) {
            return null; // inconnu
        }

        $now = new \DateTimeImmutable();
        $presented = new PresentedRefreshToken(
            new \DateTimeImmutable($row['expires_at']),
            $row['revoked_at'] !== null ? new \DateTimeImmutable($row['revoked_at']) : null,
            $row['replaced_by'] !== null,
            $row['device_fingerprint'],
        );
        $chainActive = $presented->rotated && $this->isChainActive((int) $row['replaced_by'], $now);

        switch (RefreshTokenReuse::decide($presented, $requestFingerprint, $chainActive, $now)) {
            case RefreshTokenUse::Rotate:
                $successor = $this->issueSuccessor($row, $now);
                $this->connection->update(
                    'kp_user_refresh_token',
                    ['revoked_at' => $now->format('Y-m-d H:i:s'), 'replaced_by' => $successor['id']],
                    ['id' => $row['id']],
                );
                return ['userCode' => $row['user_code'], 'token' => $successor['token']];

            case RefreshTokenUse::Grace:
                // Doublon légitime (deux onglets, réponse perdue) : un successeur de plus, sans rien révoquer.
                return ['userCode' => $row['user_code'], 'token' => $this->issueSuccessor($row, $now)['token']];

            case RefreshTokenUse::Replay:
                // Fuite présumée : on coupe l'appareil de la chaîne, pas les autres terrains du même compte.
                $row['device_fingerprint'] !== null
                    ? $this->revokeByDeviceFingerprint($row['user_code'], $row['device_fingerprint'])
                    : $this->revokeAllFor($row['user_code']);
                return null;

            default: // Closed, Invalid
                return null;
        }
    }

    /**
     * Repousse l'expiration d'un refresh token actif de `$userCode`, sans le tourner (AUTH-SC-13) : appelé par
     * /auth/refresh, qui renouvelle le JWT sans rotation du cookie. Retourne false si le token n'est pas un token
     * actif de ce compte (absent, expiré, révoqué, autre utilisateur).
     */
    public function extend(string $presentedToken, string $userCode): bool
    {
        $now = new \DateTimeImmutable();

        return $this->connection->executeStatement(
            'UPDATE kp_user_refresh_token SET expires_at = ?
             WHERE token_hash = ? AND user_code = ? AND revoked_at IS NULL AND expires_at > ?',
            [$this->expiryFrom($now), $this->hash($presentedToken), $userCode, $now->format('Y-m-d H:i:s')],
        ) > 0;
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

    /**
     * Révoque les refresh tokens actifs d'un (user_code, device_fingerprint). Utilisé par la
     * révocation ciblée d'un appareil depuis /profile/security (AUTH-DV-04/05) : ne touche
     * qu'aux sessions de cet appareil, les autres restent valides.
     */
    public function revokeByDeviceFingerprint(string $userCode, string $fingerprint): void
    {
        $this->connection->executeStatement(
            'UPDATE kp_user_refresh_token SET revoked_at = NOW()
             WHERE user_code = ? AND device_fingerprint = ? AND revoked_at IS NULL',
            [$userCode, $fingerprint],
        );
    }

    /**
     * @param array{user_code: string, device_fingerprint: ?string} $predecessor
     * @return array{id: int, token: string}
     */
    private function issueSuccessor(array $predecessor, \DateTimeImmutable $now): array
    {
        $token = $this->randomToken();
        $this->connection->insert('kp_user_refresh_token', [
            'user_code' => $predecessor['user_code'],
            'token_hash' => $this->hash($token),
            'device_fingerprint' => $predecessor['device_fingerprint'],
            'created_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiryFrom($now),
        ]);

        return ['id' => (int) $this->connection->lastInsertId(), 'token' => $token];
    }

    /** Follows `replaced_by` to the last link: the session lives on while that link is active (AUTH-SC-11). */
    private function isChainActive(int $successorId, \DateTimeImmutable $now): bool
    {
        for ($hop = 0; $hop < self::MAX_CHAIN_HOPS; $hop++) {
            $link = $this->connection->fetchAssociative(
                'SELECT revoked_at, expires_at, replaced_by FROM kp_user_refresh_token WHERE id = ?',
                [$successorId],
            );
            if (!$link) {
                return false;
            }
            if ($link['revoked_at'] === null) {
                return new \DateTimeImmutable($link['expires_at']) > $now;
            }
            if ($link['replaced_by'] === null) {
                return false; // closed by a logout or a device revocation
            }
            $successorId = (int) $link['replaced_by'];
        }

        return false;
    }

    private function expiryFrom(\DateTimeImmutable $now): string
    {
        return $now->modify('+' . $this->ttlSeconds . ' seconds')->format('Y-m-d H:i:s');
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
