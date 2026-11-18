<?php

namespace App\Security;

/**
 * Decides what a presented refresh token is worth (AUTH_HARDENING.md § 3ter.4, AUTH-SC-10 to AUTH-SC-12).
 *
 * A token presented twice is not always a theft: two tabs refreshing together, or a response lost on a venue wifi
 * followed by a retry, send the same token again seconds later from the same device. Those get a grace successor;
 * anything else is a replay.
 */
final class RefreshTokenReuse
{
    /** How long after its rotation a token may be presented again by the same device. */
    public const GRACE_SECONDS = 60;

    public static function decide(
        PresentedRefreshToken $token,
        ?string $requestFingerprint,
        bool $chainActive,
        \DateTimeImmutable $now,
    ): RefreshTokenUse {
        if ($token->expiresAt < $now) {
            return RefreshTokenUse::Invalid;
        }
        if ($token->revokedAt === null) {
            return RefreshTokenUse::Rotate;
        }
        if (!self::isDuplicateFromSameDevice($token, $requestFingerprint, $now)) {
            return RefreshTokenUse::Replay;
        }

        return $chainActive ? RefreshTokenUse::Grace : RefreshTokenUse::Closed;
    }

    private static function isDuplicateFromSameDevice(PresentedRefreshToken $token, ?string $requestFingerprint, \DateTimeImmutable $now): bool
    {
        return $token->rotated
            && $token->revokedAt !== null
            && $token->deviceFingerprint !== null
            && $token->deviceFingerprint === $requestFingerprint
            && $now->getTimestamp() - $token->revokedAt->getTimestamp() <= self::GRACE_SECONDS;
    }
}
