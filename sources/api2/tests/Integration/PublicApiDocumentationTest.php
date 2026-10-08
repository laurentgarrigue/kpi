<?php

namespace App\Tests\Integration;

/** API-11 : les endpoints du site public sont documentés dans /doc, sous leur propre tag. */
final class PublicApiDocumentationTest extends ApiTestCase
{
    private const TAG = '7. Site public';

    public function testPublicSiteEndpointsAreDocumentedUnderTheirTag(): void
    {
        $paths = $this->getJson('/doc.json')['paths'];

        $documented = [];
        foreach ($paths as $path => $operations) {
            foreach ($operations as $operation) {
                if (in_array(self::TAG, $operation['tags'] ?? [], true)) {
                    $documented[] = $path;
                }
            }
        }

        self::assertSame([
            '/seasons',
            '/group/{season}/{code}/competitions',
            '/competition/{season}/{code}',
            '/competition/{season}/{code}/games',
            '/competition/{season}/{code}/charts',
            '/competition/{season}/{code}/ranking',
            '/competition/{season}/{code}/info',
            '/competition/{season}/{code}/stats',
            '/competition/{season}/{code}/stats/{kind}',
            '/event/{id}/competitions',
        ], $documented);
    }
}
