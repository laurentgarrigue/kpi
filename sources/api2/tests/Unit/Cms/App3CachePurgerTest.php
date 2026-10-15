<?php

namespace App\Tests\Unit\Cms;

use App\Cms\App3CachePurger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** CMS-07 : la publication purge le cache d'app3 ; une purge en échec n'empêche rien. */
final class App3CachePurgerTest extends TestCase
{
    public function testCms07PostsThePathsWithTheSharedSecret(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse('{"purged":3}');
        });

        $purged = (new App3CachePurger($client, new NullLogger(), 'http://kpi_app3:3000/', 'secret'))->purge(['/', '/news', '/news']);

        self::assertTrue($purged);
        self::assertSame('POST', $requests[0][0]);
        self::assertSame('http://kpi_app3:3000/_internal/purge', $requests[0][1]);
        self::assertContains('Authorization: Bearer secret', $requests[0][2]['headers']);
        self::assertSame(['paths' => ['/', '/news']], json_decode($requests[0][2]['body'], true), 'chemins dédoublonnés');
    }

    public function testCms07FailureOrMissingConfigurationNeverThrows(): void
    {
        $failing = new MockHttpClient(static fn (): never => throw new TransportException('app3 down'));

        self::assertFalse((new App3CachePurger($failing, new NullLogger(), 'http://kpi_app3:3000', 'secret'))->purge(['/']));
        self::assertFalse((new App3CachePurger(new MockHttpClient(), new NullLogger(), '', ''))->purge(['/']));
    }
}
