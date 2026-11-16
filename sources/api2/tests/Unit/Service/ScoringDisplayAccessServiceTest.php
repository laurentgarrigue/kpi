<?php

namespace App\Tests\Unit\Service;

use App\Service\ScoringDisplayAccessService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cross-origin guard of the public overlay read path (PAGE_INCRUSTATION.md).
 * `trustedOrigins` is CORS_ALLOW_ORIGIN, the same regex Nelmio uses (origin_regex: true).
 */
final class ScoringDisplayAccessServiceTest extends TestCase
{
    private const DEV_CORS_REGEX = '^https?://(localhost|127\.0\.0\.1|.*\.localhost)(:[0-9]+)?$';

    private function service(string $trustedOrigins = self::DEV_CORS_REGEX): ScoringDisplayAccessService
    {
        return new ScoringDisplayAccessService($this->createStub(Connection::class), '', $trustedOrigins);
    }

    private function request(array $headers): Request
    {
        $request = Request::create('https://kpi.localhost/scoring/program/243/1');
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    public function testAnOriginMatchingTheCorsRegexIsTrusted(): void
    {
        $request = $this->request(['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://kpi-node4.localhost']);

        self::assertTrue($this->service()->looksSameOrigin($request));
    }

    public function testARefererMatchingTheCorsRegexIsTrusted(): void
    {
        $request = $this->request([
            'Sec-Fetch-Site' => 'cross-site',
            'Referer' => 'https://kpi-node4.localhost/admin2/live/overlay?event=243',
        ]);

        self::assertTrue($this->service()->looksSameOrigin($request));
    }

    public function testAnOriginOutsideTheCorsRegexIsRefused(): void
    {
        $request = $this->request(['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example']);

        self::assertFalse($this->service()->looksSameOrigin($request));
    }

    public function testTheSameHostIsTrustedWithoutAnyCorsSetting(): void
    {
        $request = $this->request(['Origin' => 'https://kpi.localhost']);

        self::assertTrue($this->service('')->looksSameOrigin($request));
    }

    public function testASameSiteFetchIsTrusted(): void
    {
        $request = $this->request(['Sec-Fetch-Site' => 'same-site', 'Origin' => 'https://evil.example']);

        self::assertTrue($this->service('')->looksSameOrigin($request));
    }

    // ------------------------------------------------------------ JWT d'abonné de la console

    /**
     * La console de scoring s'abonne aux topics de SON match : en préprod/prod le hub refuse
     * les abonnés anonymes, il lui faut donc un JWT signé avec le secret du hub, limité à ces topics.
     */
    public function testTopicSubscriberJwtIsSignedWithTheHubSecretAndLimitedToTheGivenTopics(): void
    {
        $secret = 'hub-secret-for-tests';
        $service = new ScoringDisplayAccessService($this->createStub(Connection::class), $secret);

        $jwt = $service->mintTopicSubscriberJwt(['/scoring/event/77/pitch/1/{type}']);

        [$header, $payload, $signature] = explode('.', $jwt);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, $secret, true)), '+/', '-_'), '=');
        self::assertSame($expected, $signature, 'signé HS256 avec le secret du hub');

        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        self::assertSame(['subscribe' => ['/scoring/event/77/pitch/1/{type}']], $claims['mercure']);
        self::assertGreaterThan(time(), $claims['exp']);
    }

    /** Sans secret (dev, hub en mode anonyme), aucun jeton : la console s'abonne sans. */
    public function testNoTopicSubscriberJwtWithoutHubSecret(): void
    {
        self::assertSame('', $this->service()->mintTopicSubscriberJwt(['/scoring/match/1/{type}']));
    }

    /**
     * Le jeton entre dans l'ETag de l'état de la console : il doit rester identique d'une
     * requête à l'autre dans la même heure, sinon plus aucun 304 n'est possible.
     */
    public function testTopicSubscriberJwtIsStableWithinTheHourAndRenewedAfter(): void
    {
        $service = new ScoringDisplayAccessService($this->createStub(Connection::class), 'hub-secret-for-tests');
        $topics = ['/scoring/match/1/{type}'];
        $hour = 1_800_000_000 - (1_800_000_000 % 3600);

        $first = $service->mintTopicSubscriberJwt($topics, now: $hour + 10);

        self::assertSame($first, $service->mintTopicSubscriberJwt($topics, now: $hour + 3500));
        self::assertNotSame($first, $service->mintTopicSubscriberJwt($topics, now: $hour + 3600 + 10));
    }

    /** Arrondi à l'heure, le jeton reste valable au moins la durée demandée. */
    public function testTopicSubscriberJwtLastsAtLeastTheRequestedTtl(): void
    {
        $service = new ScoringDisplayAccessService($this->createStub(Connection::class), 'hub-secret-for-tests');
        $now = 1_800_000_123;

        $payload = explode('.', $service->mintTopicSubscriberJwt(['/t/{type}'], 86400, $now))[1];
        $exp = json_decode(base64_decode(strtr($payload, '-_', '+/')), true)['exp'];

        self::assertGreaterThanOrEqual($now + 86400, $exp);
        self::assertLessThan($now + 86400 + 3600, $exp);
    }
}
