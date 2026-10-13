<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Administration du contenu éditorial (FEATURE_CMS.md § 4–5.2, PAGE_CMS.md) : droits, validation, filtrage du HTML,
 * journal. Comptes des fixtures : TADMIN (profil 2), TEDIT (profil 8 + Rédacteur), TVIEW (profil 8).
 */
final class AdminCmsEndpointsTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testCms03WritesNeedTheEditorCapabilityOrProfile2(): void
    {
        $this->requestAs('TVIEW', 'GET', '/admin/cms/articles', expectedStatus: 403);
        $this->requestAs('TVIEW', 'POST', '/admin/cms/articles', ['title_fr' => 'X'], 403);
        self::assertSame(5, $this->requestAs('TEDIT', 'GET', '/admin/cms/articles')['total']);
        self::assertSame(5, $this->requestAs('TADMIN', 'GET', '/admin/cms/articles')['total']);
    }

    public function testAdmCms01MenuHomeRedirectsAndDeletionAreForProfiles1And2(): void
    {
        foreach (['/admin/cms/menu', '/admin/cms/home-blocks', '/admin/cms/redirects', '/admin/cms/editors'] as $uri) {
            $this->requestAs('TEDIT', 'GET', $uri, expectedStatus: 403);
            $this->requestAs('TADMIN', 'GET', $uri);
        }
        $this->requestAs('TEDIT', 'DELETE', '/admin/cms/articles/9603', expectedStatus: 403);
        $this->requestAs('TADMIN', 'DELETE', '/admin/cms/articles/9603');
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM kp_cms_article WHERE id = 9603'));
    }

    public function testCms03ArticleCreationIsSanitisedAndJournalised(): void
    {
        $article = $this->requestAs('TEDIT', 'POST', '/admin/cms/articles', [
            'title_fr' => 'Sélection des Équipes de France',
            'body_fr' => '<p onclick="x()">Texte</p><script>alert(1)</script>',
            'categories' => [1],
            'links' => [['kind' => 'competition', 'ref' => '2999/RCH']],
            'status' => 'published',
        ], 201);

        self::assertSame('selection-des-equipes-de-france', $article['slug'], 'slug généré depuis le titre');
        self::assertSame('<p>Texte</p>', $article['body_fr'], 'HTML filtré à l\'écriture (CMS-02)');
        self::assertSame('La CNA Kayak-Polo', $article['signature'], 'signature par défaut');
        self::assertNotNull($article['published_at'], 'publication immédiate');
        self::assertSame([['kind' => 'competition', 'ref' => '2999/RCH']], $article['links']);
        self::assertSame('Création article', $this->connection->fetchOne("SELECT Actions FROM kp_journal WHERE Users = 'TEDIT' ORDER BY Id DESC"));
        self::assertSame('Sélection des Équipes de France', $this->getJson('/news/selection-des-equipes-de-france')['title']);
    }

    public function testAdmCms02PublishingNeedsATitleAValidSlugAndACategory(): void
    {
        $errors = $this->requestAs('TEDIT', 'POST', '/admin/cms/articles', ['title_fr' => '', 'slug' => 'Pas Valide', 'status' => 'published'], 400);
        self::assertSame('validation_failed', $errors['error']);
        self::assertSame(['title_fr' => 'required', 'slug' => 'invalid', 'categories' => 'required'], $errors['errors']);

        $taken = $this->requestAs('TEDIT', 'POST', '/admin/cms/articles', ['title_fr' => 'X', 'slug' => 'finale-resultats'], 400);
        self::assertSame(['slug' => 'taken'], $taken['errors']);

        $draft = $this->requestAs('TEDIT', 'POST', '/admin/cms/articles', ['title_fr' => 'Brouillon sans catégorie'], 201);
        self::assertSame('draft', $draft['status'], 'un brouillon peut n\'avoir aucune catégorie');
    }

    public function testCms01ScheduledArticleIsListedAsScheduled(): void
    {
        $statuses = array_column($this->requestAs('TEDIT', 'GET', '/admin/cms/articles')['items'], 'status', 'slug');

        self::assertSame('scheduled', $statuses['programme']);
        self::assertSame('published', $statuses['finale-resultats']);
        self::assertSame(['programme'], array_column($this->requestAs('TEDIT', 'GET', '/admin/cms/articles?status=scheduled')['items'], 'slug'));
    }

    public function testCms06ReservedOrTakenPageSlugsAreRefused(): void
    {
        self::assertSame(['slug' => 'reserved'], $this->requestAs('TEDIT', 'POST', '/admin/cms/pages', ['title_fr' => 'Calendrier', 'slug' => 'calendar'], 400)['errors']);
        self::assertSame(['slug' => 'taken'], $this->requestAs('TEDIT', 'POST', '/admin/cms/pages', ['title_fr' => 'Arbitrage'], 400)['errors']);
        self::assertSame(['parent_id' => 'invalid'], $this->requestAs('TEDIT', 'PUT', '/admin/cms/pages/9701', ['title_fr' => 'Le kayak-polo', 'slug' => 'le-kayak-polo', 'parent_id' => 9702], 400)['errors'], 'cycle refusé');

        $page = $this->requestAs('TEDIT', 'POST', '/admin/cms/pages', ['title_fr' => 'Règlements', 'parent_id' => 9701, 'status' => 'published'], 201);
        self::assertSame('/le-kayak-polo/reglements', $page['path']);
    }

    public function testCms12PreviewTokenIsIssuedToEditors(): void
    {
        $token = $this->requestAs('TEDIT', 'GET', '/admin/cms/articles/9603/preview-token')['token'];

        self::assertTrue($this->getJson('/news/brouillon?preview=' . $token)['preview']);
    }

    public function testCnt04MenuIsReplacedAndValidated(): void
    {
        $errors = $this->requestAs('TADMIN', 'PUT', '/admin/cms/menu', ['items' => [['label_fr' => '', 'target_kind' => 'page', 'target_ref' => '999999']]], 400);
        self::assertSame(['items.0.label_fr' => 'required', 'items.0.target_ref' => 'invalid'], $errors['errors']);

        $menu = $this->requestAs('TADMIN', 'PUT', '/admin/cms/menu', ['items' => [
            ['label_fr' => 'Découvrir', 'label_en' => 'Discover', 'target_kind' => 'page', 'target_ref' => '9701', 'children' => [
                ['label_fr' => 'Arbitrage', 'target_kind' => 'page', 'target_ref' => '9702'],
            ]],
        ]]);
        self::assertSame('Arbitrage', $menu[0]['children'][0]['label_fr']);
        self::assertSame([['label' => 'Découvrir', 'path' => '/le-kayak-polo', 'external' => false, 'children' => [
            ['label' => 'Arbitrage', 'path' => '/le-kayak-polo/arbitrage', 'external' => false, 'children' => []],
        ]]], $this->getJson('/menu'));
    }

    public function testCnt04MenuHasAtMostThreeLevels(): void
    {
        $level3 = ['label_fr' => 'FFCK', 'target_kind' => 'url', 'target_ref' => 'https://www.ffck.org'];
        $tree = ['items' => [
            ['label_fr' => 'Découvrir', 'target_kind' => 'page', 'target_ref' => '9701', 'children' => [
                ['label_fr' => 'Arbitrage', 'target_kind' => 'page', 'target_ref' => '9702', 'children' => [$level3]],
            ]],
        ]];
        $menu = $this->requestAs('TADMIN', 'PUT', '/admin/cms/menu', $tree);
        self::assertSame('FFCK', $menu[0]['children'][0]['children'][0]['label_fr']);
        self::assertSame(
            [['label' => 'FFCK', 'path' => 'https://www.ffck.org', 'external' => true, 'children' => []]],
            $this->getJson('/menu')[0]['children'][0]['children'],
        );

        $tree['items'][0]['children'][0]['children'][0]['children'] = [$level3];
        $errors = $this->requestAs('TADMIN', 'PUT', '/admin/cms/menu', $tree, 400);
        self::assertSame(['items.0.children.0.children.0.children' => 'too_deep'], $errors['errors']);
    }

    public function testCnt06AlertIsShownBetweenItsDates(): void
    {
        $blocks = $this->requestAs('TADMIN', 'GET', '/admin/cms/home-blocks');
        $alert = array_values(array_filter($blocks, static fn (array $block): bool => $block['kind'] === 'alert'))[0];
        $this->requestAs('TADMIN', 'PUT', '/admin/cms/home-blocks', ['blocks' => [
            ['id' => $alert['id'], 'enabled' => true, 'config' => ['level' => 'important', 'text_fr' => 'Bassin fermé', 'from' => '2000-01-01', 'to' => '9999-12-31']],
        ]]);
        self::assertSame(['kind' => 'alert', 'level' => 'important', 'text' => 'Bassin fermé'], $this->getJson('/home')[0]);

        $this->requestAs('TADMIN', 'PUT', '/admin/cms/home-blocks', ['blocks' => [
            ['id' => $alert['id'], 'enabled' => true, 'config' => ['level' => 'info', 'text_fr' => 'Passée', 'from' => '2000-01-01', 'to' => '2000-01-02']],
        ]]);
        self::assertNotContains('alert', array_column($this->getJson('/home'), 'kind'));
    }

    public function testAdmCms05EditorCapabilityIsGrantedByProfiles1And2(): void
    {
        $this->requestAs('TADMIN', 'PUT', '/admin/cms/editors/TVIEW', ['editor' => true]);
        self::assertSame(['TVIEW', 'TEDIT'], array_column($this->requestAs('TADMIN', 'GET', '/admin/cms/editors'), 'code'), 'par nom');
        $this->requestAs('TVIEW', 'GET', '/admin/cms/articles');
        self::assertSame(['editor'], $this->requestAs('TVIEW', 'GET', '/auth/me')['user']['capabilities']);

        $this->requestAs('TADMIN', 'PUT', '/admin/cms/editors/TVIEW', ['editor' => false]);
        $this->requestAs('TVIEW', 'GET', '/admin/cms/articles', expectedStatus: 403);
        $this->requestAs('TEDIT', 'PUT', '/admin/cms/editors/TVIEW', ['editor' => true], 403);
    }

    public function testRedirectsAreManaged(): void
    {
        self::assertSame(['from_path' => 'invalid', 'to_path' => 'invalid'], $this->requestAs('TADMIN', 'POST', '/admin/cms/redirects', ['from_path' => 'x', 'to_path' => ''], 400)['errors']);
        $id = $this->requestAs('TADMIN', 'POST', '/admin/cms/redirects', ['from_path' => '/?p=123', 'to_path' => '/news'], 201)['id'];
        self::assertSame(['to' => '/news', 'code' => 301], $this->getJson('/redirect?path=' . urlencode('/?p=123')));
        $this->requestAs('TADMIN', 'DELETE', '/admin/cms/redirects/' . $id);
    }
}
