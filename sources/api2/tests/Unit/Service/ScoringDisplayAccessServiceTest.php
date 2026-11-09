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
}
