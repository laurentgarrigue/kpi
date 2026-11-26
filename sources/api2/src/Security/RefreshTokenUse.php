<?php

namespace App\Security;

/**
 * What a presented refresh token is worth (AUTH_HARDENING.md § 3ter.4).
 */
enum RefreshTokenUse
{
    /** Active token: revoke it and issue its successor. */
    case Rotate;
    /** Duplicate of a token rotated seconds ago by the same device (AUTH-SC-10): issue another successor. */
    case Grace;
    /** Duplicate within the grace window, but the session was ended meanwhile (AUTH-SC-11): refuse, nothing else. */
    case Closed;
    /** Reuse of a consumed token (AUTH-SC-12): refuse and revoke the sessions of the chain's device. */
    case Replay;
    /** Expired token: refuse. */
    case Invalid;
}
