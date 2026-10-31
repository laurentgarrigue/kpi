<?php

namespace App\Tests\Integration;

use App\Cms\PreviewToken;

/**
 * Contenus éditoriaux publics (FEATURE_CMS.md § 5.1, PAGE_NEWS.md, PAGE_CONTENT.md) sur les fixtures
 * SQL/fixtures/data.sql (articles 9601–9605, pages 9701–9703, menu 9801–9804).
 */
final class PublicCmsEndpointsTest extends ApiTestCase
{
    public function testCms01OnlyPublishedPastArticlesAreListedNewestFirst(): void
    {
        $news = $this->getJson('/news');

        self::assertSame(['finale-resultats', 'nouveau-reglement'], self::column($news['items'], 'slug'), 'brouillon, programmé et archivé exclus');
        self::assertSame(['page' => 1, 'pages' => 1, 'total' => 2], ['page' => $news['page'], 'pages' => $news['pages'], 'total' => $news['total']]);
        self::assertSame(['slug', 'title', 'lead', 'cover', 'published_at', 'categories'], array_keys($news['items'][0]));
        self::assertStringContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testCms01UnpublishedArticlesAreNotFound(): void
    {
        foreach (['brouillon', 'programme', 'archive', 'inconnu'] as $slug) {
            self::assertSame(['error' => 'not_found'], $this->getJson('/news/' . $slug, 404), $slug);
        }
    }

    public function testNews03ArticleExposesItsContentAndNoAccount(): void
    {
        $article = $this->getJson('/news/finale-resultats');

        self::assertSame(['slug', 'title', 'lead', 'body', 'cover', 'published_at', 'updated_at', 'signature', 'categories', 'links', 'seo', 'previous', 'next', 'preview'], array_keys($article));
        self::assertSame('Finale des résultats', $article['title']);
        self::assertSame('<p>Corps <strong>gras</strong>.</p>', $article['body']);
        self::assertSame('La CNA Kayak-Polo', $article['signature']);
        self::assertStringNotContainsString('TEDIT', (string) json_encode($article), 'le compte auteur n\'est jamais exposé (CMS-10)');
        self::assertSame('2020-06-01T12:00:00+02:00', $article['published_at']);
        self::assertSame(['equipes-de-france', 'competitions-nationales'], self::column($article['categories'], 'slug'));
        self::assertSame(['slug' => 'nouveau-reglement', 'title' => 'Nouveau règlement'], $article['previous']);
        self::assertNull($article['next']);
        self::assertStringStartsWith('/media/content/2999/01/finale.jpg?v=', $article['cover']['url']);
        self::assertSame([320, 768, 1280], self::column($article['cover']['variants'], 'width'), 'variantes plus petites que l\'original (1600 px)');
    }

    public function testNews04LinksResolveToPublishedResultsPages(): void
    {
        self::assertSame([
            ['kind' => 'competition', 'label' => 'Championnat Résultats 2999', 'path' => '/competitions/2999/RCH'],
            ['kind' => 'event', 'label' => 'Tournoi Résultats', 'path' => '/events/77'],
            ['kind' => 'club', 'label' => 'Club Alpha Lacville', 'path' => '/clubs?club=C001'],
        ], $this->getJson('/news/finale-resultats')['links']);
    }

    public function testCms05EnglishFallsBackToFrenchWhenEmpty(): void
    {
        self::assertSame('Results final', $this->getJson('/news/finale-resultats?lang=en')['title']);
        $fallback = $this->getJson('/news/nouveau-reglement?lang=en');
        self::assertSame('Nouveau règlement', $fallback['title']);
        self::assertSame('<p>Texte.</p>', $fallback['body']);
        self::assertSame('Rules and federation life', $fallback['categories'][0]['label']);
    }

    public function testCms13CategoriesFilterTheNews(): void
    {
        self::assertSame(['equipes-de-france', 'competitions-nationales', 'competitions-regionales-open-internationales', 'reglements-vie-federale'], self::column($this->getJson('/news/categories'), 'slug'));
        self::assertSame('Règlements et vie fédérale', $this->getJson('/news/categories')[3]['label'], 'libellés accentués intacts (migration en utf8mb4)');
        self::assertSame(['nouveau-reglement'], self::column($this->getJson('/news?category=reglements-vie-federale')['items'], 'slug'));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/news?category=inconnue', 400));
    }

    public function testNews09NewsCanBeFilteredByLinkedResultsPage(): void
    {
        self::assertSame(['finale-resultats'], self::column($this->getJson('/news?competition=2999/RCH&limit=3')['items'], 'slug'), 'le brouillon lié est exclu');
        self::assertSame(['finale-resultats'], self::column($this->getJson('/news?event=77')['items'], 'slug'));
        self::assertSame([], $this->getJson('/news?club=C002')['items']);
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/news?competition=RCH', 400));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/news?page=0', 400));
    }

    public function testCms12PreviewTokenShowsAnUnpublishedArticleWithoutCache(): void
    {
        $token = static::getContainer()->get(PreviewToken::class)->create('article', 9603);

        $preview = $this->getJson('/news/brouillon?preview=' . $token);
        self::assertTrue($preview['preview']);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(['error' => 'not_found'], $this->getJson('/news/programme?preview=' . $token, 404), 'jeton d\'un autre article');
        self::assertSame(['error' => 'not_found'], $this->getJson('/news/brouillon?preview=' . $token . 'x', 404), 'jeton falsifié');
    }

    public function testCnt01PagesHaveTheirFullPathParentsAndChildren(): void
    {
        $parent = $this->getJson('/pages/le-kayak-polo');
        self::assertSame('/le-kayak-polo', $parent['path']);
        self::assertSame([['title' => 'Arbitrage', 'path' => '/le-kayak-polo/arbitrage']], $parent['children']);

        $child = $this->getJson('/pages/arbitrage');
        self::assertSame('/le-kayak-polo/arbitrage', $child['path']);
        self::assertSame([['title' => 'Le kayak-polo', 'path' => '/le-kayak-polo']], $child['parents']);

        self::assertSame(['error' => 'not_found'], $this->getJson('/pages/page-brouillon', 404));
        self::assertSame(['error' => 'not_found'], $this->getJson('/pages/inconnue', 404));
    }

    public function testCnt04MenuListsPublishedTargetsOnly(): void
    {
        self::assertSame([
            ['label' => 'Le kayak-polo', 'path' => '/le-kayak-polo', 'external' => false, 'children' => []],
            ['label' => 'Arbitrage', 'path' => '/le-kayak-polo/arbitrage', 'external' => false, 'children' => []],
            ['label' => 'FFCK', 'path' => 'https://www.ffck.org', 'external' => true, 'children' => []],
        ], $this->getJson('/menu'));
    }

    public function testCnt05HomeListsEnabledNonEmptyBlocksInOrder(): void
    {
        $home = $this->getJson('/home');

        self::assertSame(['featured', 'latest_news'], self::column($home, 'kind'), 'alerte vide et partenaires désactivés masqués');
        self::assertSame(['finale-resultats'], self::column($home[0]['articles'], 'slug'));
        self::assertSame(['finale-resultats', 'nouveau-reglement'], self::column($home[1]['articles'], 'slug'));
    }

    public function testCms08RssIsAValidRss2Feed(): void
    {
        $this->client->request('GET', '/rss.xml');
        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/rss+xml', (string) $response->headers->get('Content-Type'));
        $rss = new \SimpleXMLElement((string) $response->getContent());
        self::assertSame('2.0', (string) $rss['version']);
        self::assertCount(2, $rss->channel->item);
        self::assertSame('Finale des résultats', (string) $rss->channel->item[0]->title);
        self::assertStringEndsWith('/news/finale-resultats', (string) $rss->channel->item[0]->link);
    }

    public function testCnt07SitemapListsPublishedArticlesAndPages(): void
    {
        self::assertSame(
            ['/news/finale-resultats', '/news/nouveau-reglement', '/le-kayak-polo/arbitrage', '/le-kayak-polo'],
            self::column($this->getJson('/sitemap/content.xml'), 'path'),
        );
    }

    public function testOldPathsRedirect(): void
    {
        self::assertSame(['to' => '/news/finale-resultats', 'code' => 301], $this->getJson('/redirect?path=/2020/06/finale/'));
        self::assertSame(['error' => 'not_found'], $this->getJson('/redirect?path=/inconnu', 404));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/redirect?path=relative', 400));
    }

    public function testCms11SearchFindsPublishedNewsAndPages(): void
    {
        $results = $this->getJson('/search?q=finale');
        self::assertSame(['finale-resultats'], self::column($results['news'], 'slug'));

        $pages = $this->getJson('/search?q=arbitrage')['pages'];
        self::assertSame([['title' => 'Arbitrage', 'path' => '/le-kayak-polo/arbitrage']], $pages);
        self::assertSame([], $this->getJson('/search?q=brouillon')['news'], 'brouillon jamais trouvé');
    }
}
