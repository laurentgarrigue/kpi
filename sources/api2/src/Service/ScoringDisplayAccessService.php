<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Access control of the public live read path (PAGE_INCRUSTATION.md §11bis).
 *
 * The honest framing first, because it drives the design:
 *
 *  - **A same-origin check is NOT a security boundary.** `Origin`, `Referer` and
 *    `Sec-Fetch-Site` are set by browsers and trivially forged by any script or curl.
 *    They stop *other websites* from consuming our data in a visitor's browser, and they
 *    stop casual hotlinking — nothing more. Useful as defence in depth, never as the lock.
 *  - **CORS is the browser-side lock**: without a permissive `Access-Control-Allow-Origin`,
 *    a third-party page's JavaScript cannot read the response. That is real, and free.
 *  - **The actual lock is the display token**: scoped to an event (optionally a pitch),
 *    expiring, revocable, carried by the URL configured once in OBS.
 *
 * The token is also a functional necessity, not only a security one: in preprod/prod the
 * Mercure hub runs with `MERCURE_ANONYMOUS=0`, so the overlay cannot subscribe without a
 * subscriber JWT. This service mints that JWT, restricted to the token's own topics —
 * an overlay can therefore never listen to another event's pitches.
 */
class ScoringDisplayAccessService
{
    /** Scope of a validated token. */
    public const SCOPE_EVENT = 'event';
    public const SCOPE_PITCH = 'pitch';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $mercureJwtSecret = '',
        /** Comma-separated list of origins allowed to read without a token (own apps). */
        private readonly string $trustedOrigins = '',
    ) {
    }

    /**
     * Validate a display token for an event (and optionally a pitch).
     *
     * @return array{id:int,event:int,pitch:?string}|null null when missing/invalid/expired/revoked
     */
    public function validate(Request $request, int $idEvent, ?string $pitch = null): ?array
    {
        $token = $request->query->get('token') ?? $request->headers->get('X-Display-Token');
        if (!is_string($token) || $token === '') {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT id, id_event, pitch, expires_at, revoked_at
             FROM scoring_display_token
             WHERE token = ?',
            [$token]
        );

        if ($row === false || $row['revoked_at'] !== null) {
            return null;
        }
        if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
            return null;
        }
        if ((int) $row['id_event'] !== $idEvent) {
            return null;
        }
        // A pitch-scoped token only opens its own pitch.
        if ($row['pitch'] !== null && $pitch !== null && $row['pitch'] !== $pitch) {
            return null;
        }

        // Best-effort usage stamp (helps spot a token still live after an event).
        try {
            $this->connection->executeStatement(
                'UPDATE scoring_display_token SET last_used_at = NOW() WHERE id = ?',
                [$row['id']]
            );
        } catch (\Throwable) {
            // Never fail a display because of a bookkeeping write.
        }

        return [
            'id' => (int) $row['id'],
            'event' => (int) $row['id_event'],
            'pitch' => $row['pitch'],
        ];
    }

    /**
     * Defence in depth, explicitly NOT a security boundary (see the class docblock):
     * true when the request looks like it comes from one of our own pages (or from a
     * non-browser context such as the OBS browser source, which sends no Origin on a
     * same-origin GET). Used to answer 403 to an obvious cross-site fetch.
     */
    public function looksSameOrigin(Request $request): bool
    {
        // Browsers set Sec-Fetch-Site on every fetch; 'none' = typed URL / OBS source.
        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        if ($fetchSite !== null && in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) {
            return true;
        }

        $origin = $request->headers->get('Origin') ?? $request->headers->get('Referer');
        if ($origin === null) {
            // No Origin at all: same-origin GET or a non-browser client. The token is the
            // real control, so this is not the place to reject.
            return true;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return false;
        }
        if ($host === $request->getHost()) {
            return true;
        }

        foreach (array_filter(array_map('trim', explode(',', $this->trustedOrigins))) as $trusted) {
            $trustedHost = parse_url($trusted, PHP_URL_HOST) ?: $trusted;
            if ($host === $trustedHost) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mint a Mercure **subscriber** JWT restricted to the token's topics — required in
     * preprod/prod where the hub refuses anonymous subscriptions (MERCURE_ANONYMOUS=0).
     *
     * The claim is deliberately narrow: an event-scoped token gets every pitch of THAT
     * event, a pitch-scoped one gets a single pitch. Signed HS256 with the hub secret;
     * short-lived (the overlay refetches the program regularly and gets a fresh one).
     *
     * @param array{event:int,pitch:?string} $scope
     */
    public function mintSubscriberJwt(array $scope, int $ttlSeconds = 86400): string
    {
        if ($this->mercureJwtSecret === '') {
            return '';
        }

        $topic = $scope['pitch'] !== null
            ? "/scoring/event/{$scope['event']}/pitch/{$scope['pitch']}/{type}"
            : "/scoring/event/{$scope['event']}/pitch/{pitch}/{type}";

        $payload = [
            'mercure' => ['subscribe' => [$topic]],
            'exp' => time() + $ttlSeconds,
        ];

        return $this->encodeHs256($payload);
    }

    /** Minimal HS256 JWT encoder — no dependency needed for a two-claim token. */
    private function encodeHs256(array $payload): string
    {
        $segments = [
            $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            $this->base64UrlEncode(json_encode($payload)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $this->mercureJwtSecret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
