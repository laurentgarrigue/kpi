<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * CMS-09 / Q-P4-8 : reprise WordPress sur les tables « wptest_* » des fixtures (articles 101–103, pages 301–313,
 * formulaire Ninja Forms 19), médias lus dans un répertoire uploads local.
 */
final class ImportWordPressTest extends ApiTestCase
{
    private Connection $connection;
    private string $root;
    private string $uploads;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kpi-import-' . bin2hex(random_bytes(4));
        $this->uploads = $this->root . '/uploads';
        mkdir($this->uploads . '/2024/06', 0777, true);
        $image = imagecreatetruecolor(1000, 500);
        imagejpeg($image, $this->uploads . '/2024/06/photo.jpg');
        file_put_contents($this->uploads . '/2024/06/reglement.pdf', "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n");
        $_SERVER['CMS_MEDIA_ROOT'] = $_ENV['CMS_MEDIA_ROOT'] = $this->root . '/content';
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['CMS_MEDIA_ROOT'], $_ENV['CMS_MEDIA_ROOT']);
        exec('rm -rf ' . escapeshellarg($this->root));
        parent::tearDown();
    }

    public function testCms09DryRunReportsWithoutWriting(): void
    {
        $output = $this->import(['--dry-run' => true]);

        self::assertStringContainsString('Simulation', $output);
        self::assertStringContainsString('shortcode [ninja_form] non converti', $output);
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM kp_cms_article WHERE legacy_wp_id IS NOT NULL'));
        self::assertFalse($this->connection->fetchOne("SELECT 1 FROM kp_cms_redirect WHERE from_path = '/?p=101'"));
    }

    public function testCms09ImportsArticlesWithTheirMediaLinksAndRedirections(): void
    {
        $this->import();

        $article = $this->connection->fetchAssociative('SELECT * FROM kp_cms_article WHERE legacy_wp_id = 101');
        self::assertSame(['finale-n1', 'Finale N1', 'N1 final', 'published', '2024-06-02 10:00:00'], [$article['slug'], $article['title_fr'], $article['title_en'], $article['status'], $article['published_at']]);
        self::assertSame('Le résumé', $article['lead_fr']);
        self::assertStringStartsWith('<p>Premier paragraphe</p>', $article['body_fr']);
        self::assertMatchesRegularExpression('~<img src="/media/content/\d{4}/\d{2}/photo\.jpg" alt="Podium">~', $article['body_fr'], 'original rapatrié à la place de la vignette 300x200');
        self::assertStringContainsString('<a href="/news">ancien</a>', $article['body_fr'], 'article non repris → /news');
        self::assertNotNull($article['cover_media_id'], 'image mise en avant');
        self::assertSame('Description SEO', $article['seo_description_fr']);
        self::assertSame('', $article['seo_title_fr'], 'variables Rank Math ignorées');
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_cms_media WHERE legacy_url LIKE '%photo.jpg'"), 'un seul média pour la vignette et l\'image mise en avant');
        self::assertStringNotContainsString('wp-content', $article['body_fr'], 'WordPress installé sous /wordpress : médias rapatriés quand même');

        $redirects = $this->connection->fetchAllKeyValue('SELECT from_path, to_path FROM kp_cms_redirect');
        self::assertSame('/news/finale-n1', $redirects['/2024/06/finale-n1/']);
        self::assertSame('/news/finale-n1', $redirects['/?p=101']);
        self::assertSame('/news', $redirects['/2024/05/ancien/'], 'article publié non repris → /news (301)');
        self::assertArrayNotHasKey('/2024/07/brouillon-wp/', $redirects);
    }

    public function testCms09ArticlesKeepTheirCategories(): void
    {
        $output = $this->import();

        $categories = $this->connection->fetchFirstColumn(
            'SELECT c.slug FROM kp_cms_article_category ac JOIN kp_cms_category c ON (c.id = ac.category_id)
            JOIN kp_cms_article a ON (a.id = ac.article_id) WHERE a.legacy_wp_id = 101 ORDER BY c.position',
        );
        self::assertSame(['equipes-de-france', 'competitions-nationales'], $categories, 'par nom ; « Non classé » et les étiquettes ignorées');
        self::assertStringContainsString('catégorie WordPress « Divers » sans équivalent', $output);

        $this->import(['--category-map' => 'divers=reglements-vie-federale']);
        self::assertContains('reglements-vie-federale', $this->connection->fetchFirstColumn(
            'SELECT c.slug FROM kp_cms_article_category ac JOIN kp_cms_category c ON (c.id = ac.category_id)
            JOIN kp_cms_article a ON (a.id = ac.article_id) WHERE a.legacy_wp_id = 101',
        ));
    }

    public function testCms14ImportedMediaGoToTheArticlesAndPagesAlbums(): void
    {
        $this->import();

        $albums = $this->connection->fetchAllKeyValue(
            "SELECT SUBSTRING_INDEX(m.legacy_url, '/', -1), a.slug FROM kp_cms_media m JOIN kp_cms_album a ON (a.id = m.album_id) WHERE m.legacy_url IS NOT NULL",
        );
        self::assertSame(['photo.jpg' => 'articles', 'reglement.pdf' => 'pages'], $albums);
        self::assertStringContainsString('https://www.kayak-polo.info/wordpress/wp-content/uploads/2024/06/photo.jpg', (string) $this->connection->fetchOne("SELECT legacy_url FROM kp_cms_media WHERE legacy_url LIKE '%photo.jpg'"));
        self::assertMatchesRegularExpression('~<a href="/media/content/\d{4}/\d{2}/reglement\.pdf">PDF</a>~', (string) $this->connection->fetchOne('SELECT body_fr FROM kp_cms_page WHERE legacy_wp_id = 301'));
    }

    public function testQP44ImportsChosenPagesWithParentsAndDraftsTheOnesToRework(): void
    {
        $this->import();

        $pages = $this->connection->fetchAllAssociativeIndexed('SELECT legacy_wp_id, slug, status, parent_id, id FROM kp_cms_page WHERE legacy_wp_id IS NOT NULL');
        self::assertSame(['reglement', 'published'], [$pages[301]['slug'], $pages[301]['status']]);
        self::assertSame($pages[301]['id'], $pages[302]['parent_id']);
        self::assertSame('draft', $pages[313]['status'], 'page 313 à rafraîchir');
        self::assertSame('/reglement/arbitres', $this->connection->fetchOne("SELECT to_path FROM kp_cms_redirect WHERE from_path = '/arbitres/'"));
        self::assertSame('/reglement/arbitres', $this->getJson('/pages/arbitres')['path']);
    }

    public function testQP48ImportsTheNinjaFormAsAnUnpublishedExample(): void
    {
        $this->import();

        $form = $this->connection->fetchAssociative('SELECT * FROM kp_form WHERE legacy_nf_id = 19');
        self::assertSame(['inscription-tournoi', 'draft'], [$form['slug'], $form['status']]);
        $fields = $this->connection->fetchAllAssociative('SELECT field_key, type, required, options FROM kp_form_field WHERE form_id = ? ORDER BY position', [$form['id']]);
        self::assertSame(['nom_equipe', 'email', 'categorie'], array_column($fields, 'field_key'), 'bouton d\'envoi ignoré');
        self::assertSame(['text', 'email', 'select'], array_column($fields, 'type'));
        self::assertSame([1, 1, 0], array_map('intval', array_column($fields, 'required')));
        self::assertSame(['u18', 'sen'], array_column(json_decode($fields[2]['options'], true), 'value'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_form_entry WHERE form_id = ?', [$form['id']]), 'sans les inscriptions');
    }

    public function testCms09ImportIsIdempotent(): void
    {
        $this->import();
        $this->import();

        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_cms_article WHERE legacy_wp_id IS NOT NULL'), 'le dernier article publié seulement');
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_cms_page WHERE legacy_wp_id IS NOT NULL'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_form WHERE legacy_nf_id = 19'));
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_cms_media WHERE legacy_url LIKE '%photo.jpg'"));
    }

    /** @param array<string, mixed> $options */
    private function import(array $options = []): string
    {
        $tester = new CommandTester((new Application(static::$kernel))->find('app:import-wordpress'));
        $tester->execute($options + [
            '--same-database' => true,
            '--prefix' => 'wptest_',
            '--articles' => '1',
            '--pages' => '301,302,313',
            '--draft-pages' => '313',
            '--uploads-dir' => $this->uploads,
        ]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        return $tester->getDisplay();
    }
}
