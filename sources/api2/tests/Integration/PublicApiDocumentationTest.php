<?php

namespace App\Tests\Integration;

/** API-11 / API3-09 : les endpoints du site public sont documentés dans /doc, sous leur propre tag. */
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

        // Same set, whatever the registration order (routes with a priority come first).
        self::assertEqualsCanonicalizing([
            // Phase 4 (FEATURE_CMS.md § 5.1, FEATURE_FORMS.md § 4)
            '/news/categories',
            '/news',
            '/news/{slug}',
            '/pages/{slug}',
            '/menu',
            '/home',
            '/albums/{slug}',
            '/rss.xml',
            '/sitemap/content.xml',
            '/redirect',
            '/forms',
            '/forms/options/competitions',
            '/forms/{slug}',
            '/forms/{slug}/submissions',
            '/forms/{slug}/entries',
            // Phase 2
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
            // Phase 3 (API_PUBLIC_TRANSVERSE.md, API3-09)
            '/calendar',
            '/calendar/groups',
            '/competition/{season}/{code}/calendar.ics',
            '/gameday/{id}.ics',
            '/event/{id}/calendar.ics',
            '/history',
            '/history/{group}',
            '/teams',
            '/team/{number}',
            '/team/{number}/roster/{season}/{code}',
            '/clubs',
            '/club/{code}',
            '/search',
        ], $documented);
    }
}
