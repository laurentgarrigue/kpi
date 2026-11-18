<?php

namespace App\Security;

/**
 * State of a presented refresh token, as stored in `kp_user_refresh_token`.
 */
final readonly class PresentedRefreshToken
{
    public function __construct(
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $revokedAt,
        /** True when it was revoked by a rotation (`replaced_by` set), not by a logout or a device revocation. */
        public bool $rotated,
        public ?string $deviceFingerprint,
    ) {
    }
}
