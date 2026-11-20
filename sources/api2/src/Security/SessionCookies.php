<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/** Cookies de session `kpi_access` (JWT) et `kpi_refresh` (AUTH_HARDENING.md § 3, AUTH-CK-01). */
final class SessionCookies
{
    public const ACCESS = 'kpi_access';
    public const REFRESH = 'kpi_refresh';
    private const DEFAULT_JWT_TTL_SECONDS = 900;

    public static function access(string $jwt): Cookie
    {
        $ttl = (int) ($_ENV['JWT_TTL'] ?? getenv('JWT_TTL') ?: self::DEFAULT_JWT_TTL_SECONDS);

        return self::httpOnly(self::ACCESS, $jwt, time() + $ttl);
    }

    public static function refresh(string $token): Cookie
    {
        return self::httpOnly(self::REFRESH, $token, time() + RefreshTokenService::TTL_SECONDS);
    }

    public static function clear(Response $response): void
    {
        $response->headers->clearCookie(self::REFRESH, '/', null, true, true, 'lax');
        $response->headers->clearCookie(self::ACCESS, '/', null, true, true, 'lax');
    }

    private static function httpOnly(string $name, string $value, int $expiresAt): Cookie
    {
        return Cookie::create($name)
            ->withValue($value)
            ->withExpires($expiresAt)
            ->withPath('/')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite('lax');
    }
}
