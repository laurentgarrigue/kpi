<?php

namespace App\Cms\Import;

use App\Cms\ArticleEditor;
use App\Cms\ArticleRepository;
use App\Cms\CategoryRepository;
use App\Cms\CmsRules;
use App\Cms\ContentAlbums;
use App\Cms\HtmlSanitizer;
use App\Cms\MediaLibrary;
use App\Cms\MediaRepository;
use App\Cms\MediaUrls;
use App\Cms\PageRepository;
use App\Cms\SiteRepository;
use App\Cms\ValidationErrors;
use App\Forms\FormRepository;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reprise du contenu WordPress (FEATURE_CMS.md § 8, CMS-09) : derniers articles publiés avec leurs catégories, pages
 * choisies, médias des contenus repris (albums « Articles » et « Pages », CMS-14), redirections des permaliens,
 * formulaire Ninja Forms d'exemple. Idempotente : relancée, elle met à jour ce qui a déjà été repris
 * (`legacy_wp_id`, `legacy_url`, `legacy_nf_id`).
 */
final class WordPressImporter
{
    private const IMPORT_USER = 'wpimport';
    private const RESIZED_SUFFIX = '~-\d+x\d+(\.[a-z0-9]+)$~i';
    private const IMPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    private ImportOptions $options;
    private ImportReport $report;
    private Connection $wordpress;
    /** Adresse de wp-content/uploads, lue dans les réglages WordPress (installé sous /wordpress sur kayak-polo.info). */
    private string $uploadsUrl;
    private string $uploadsPattern;
    /** @var list<array{slug: string, label_fr: string}> */
    private array $siteCategories = [];

    /** @var array<int, string> chemin du site public de chaque contenu WordPress repris */
    private array $newPaths = [];

    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly CategoryRepository $categories,
        private readonly ContentAlbums $albums,
        private readonly PageRepository $pages,
        private readonly MediaLibrary $media,
        private readonly MediaRepository $mediaRepository,
        private readonly SiteRepository $site,
        private readonly FormRepository $forms,
        private readonly HtmlSanitizer $sanitizer,
        private readonly HttpClientInterface $httpClient,
        private readonly ClockInterface $clock,
    ) {
    }

    public function run(Connection $wordpress, ImportOptions $options, ImportReport $report): void
    {
        $this->wordpress = $wordpress;
        $this->options = $options;
        $this->report = $report;
        $this->newPaths = [];
        $this->uploadsUrl = $this->wordPressUploadsUrl();
        $this->uploadsPattern = WordPressContent::uploadsPattern($options->siteUrl);
        $this->siteCategories = array_map(
            static fn (array $category): array => ['slug' => (string) $category['slug'], 'label_fr' => (string) $category['label_fr']],
            $this->categories->all(),
        );

        $posts = $this->wordpress->fetchAllAssociative(
            "SELECT * FROM {$this->table('posts')} WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC, ID DESC LIMIT " . $options->articleCount,
        );
        $pages = $options->pageIds === [] ? [] : $this->wordpress->fetchAllAssociative(
            "SELECT * FROM {$this->table('posts')} WHERE post_type = 'page' AND ID IN (?) ORDER BY post_parent, ID",
            [$options->pageIds],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER],
        );
        foreach (array_diff($options->pageIds, array_map(static fn (array $page): int => (int) $page['ID'], $pages)) as $missing) {
            $report->warning("Page WordPress $missing introuvable");
        }

        // Paths are known before the contents are converted, so that links between imported contents are rewritten.
        foreach ($posts as $post) {
            $this->newPaths[(int) $post['ID']] = '/news/' . $this->slugOf($post);
        }
        foreach ($pages as $page) {
            $this->newPaths[(int) $page['ID']] = '/' . $this->slugOf($page);
        }

        foreach ($posts as $post) {
            $this->importArticle($post);
        }
        $pageIds = [];
        foreach ($pages as $page) {
            $pageIds[(int) $page['ID']] = $this->importPage($page);
        }
        $this->linkPageParents($pages, $pageIds);
        $this->createRedirects($posts, $pages, $pageIds);
        if ($options->ninjaFormId !== null) {
            $this->importNinjaForm($options->ninjaFormId);
        }
    }

    // ---------------------------------------------------------------- articles et pages

    /** @param array<string, mixed> $post */
    private function importArticle(array $post): void
    {
        $wordPressId = (int) $post['ID'];
        $title = WordPressContent::splitLanguages((string) $post['post_title']);
        $content = WordPressContent::splitLanguages((string) $post['post_content']);
        $excerpt = WordPressContent::splitLanguages((string) $post['post_excerpt']);
        $seo = $this->seo($wordPressId);
        $label = sprintf('Article %d « %s »', $wordPressId, $title['fr']);
        [$coverId, $bodyFr, $bodyEn] = $this->coverAndBodies($wordPressId, $content, $label);
        $values = [
            'slug' => $this->uniqueSlug($this->slugOf($post), fn (string $slug): bool => $this->articles->slugExists($slug, $this->articles->findIdByLegacyId($wordPressId))),
            'title_fr' => mb_substr($title['fr'], 0, 255),
            'title_en' => mb_substr($title['en'], 0, 255),
            'lead_fr' => strip_tags($excerpt['fr']),
            'lead_en' => strip_tags($excerpt['en']),
            'body_fr' => $bodyFr,
            'body_en' => $bodyEn,
            'cover_media_id' => $coverId,
            'status' => CmsRules::STATUS_PUBLISHED,
            'published_at' => (string) $post['post_date'],
            'signature' => ArticleEditor::DEFAULT_SIGNATURE,
            'legacy_wp_id' => $wordPressId,
            'updated_at' => (string) $post['post_modified'],
            'updated_by' => self::IMPORT_USER,
        ] + $seo;
        $this->newPaths[$wordPressId] = '/news/' . $values['slug'];
        $categoryIds = $this->articleCategories($wordPressId, $label);
        $this->report->count('articles');
        if ($this->options->dryRun) {
            return;
        }
        $id = $this->articles->findIdByLegacyId($wordPressId);
        if ($id === null) {
            $id = $this->articles->insert($values + ['created_at' => (string) $post['post_date'], 'author_code' => self::IMPORT_USER]);
        } else {
            $this->articles->update($id, $values);
        }
        $this->articles->replaceCategories($id, $categoryIds);
        $this->albums->collect(ContentAlbums::ARTICLES, [$values['body_fr'], $values['body_en']], $values['cover_media_id']);
    }

    /**
     * Image de une : la première image du corps, retirée des deux langues ; à défaut (aucune image, ou image non
     * rapatriée), l'image mise en avant de WordPress, corps intacts (CMS-15).
     *
     * @param array{fr: string, en: string} $content
     *
     * @return array{0: ?int, 1: string, 2: string} média de une, corps FR, corps EN
     */
    private function coverAndBodies(int $wordPressId, array $content, string $label): array
    {
        $bodyFr = $this->convertBody($content['fr'], $label);
        $bodyEn = $this->convertBody($content['en'], $label);
        [$src, $bodyFrWithoutImage] = WordPressContent::extractImage($bodyFr);
        $coverId = $src !== null && str_starts_with($src, MediaUrls::PUBLIC_PREFIX) ? $this->mediaIdOf($src) : null;
        if ($coverId === null) {
            return [$this->featuredImage($wordPressId, $label), $bodyFr, $bodyEn];
        }

        return [$coverId, $bodyFrWithoutImage, WordPressContent::extractImage($bodyEn, $src)[1]];
    }

    /**
     * Catégories du site des catégories WordPress de l'article (« Non classé » et étiquettes ignorées) ; une catégorie
     * sans équivalent est signalée (à classer, ou à faire correspondre avec `--category-map`).
     *
     * @return list<int>
     */
    private function articleCategories(int $wordPressId, string $label): array
    {
        $rows = $this->wordpress->fetchAllAssociative(
            "SELECT t.name, t.slug FROM {$this->table('terms')} t
            INNER JOIN {$this->table('term_taxonomy')} tt ON (tt.term_id = t.term_id AND tt.taxonomy = 'category')
            INNER JOIN {$this->table('term_relationships')} tr ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
            WHERE tr.object_id = ?",
            [$wordPressId],
        );
        $ids = [];
        foreach ($rows as $row) {
            $category = ['name' => (string) $row['name'], 'slug' => (string) $row['slug']];
            if (WordPressContent::isDefaultCategory($category)) {
                continue;
            }
            $slug = WordPressContent::categoryFor($category, $this->siteCategories, $this->options->categoryMap);
            $id = $slug === null ? null : $this->categories->idOfSlug($slug);
            if ($id === null) {
                $this->report->warning(sprintf('%s : catégorie WordPress « %s » sans équivalent (--category-map=%s=<catégorie>)', $label, WordPressContent::splitLanguages($category['name'])['fr'], $category['slug']));
                continue;
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string, mixed> $page */
    private function importPage(array $page): ?int
    {
        $wordPressId = (int) $page['ID'];
        $title = WordPressContent::splitLanguages((string) $page['post_title']);
        $content = WordPressContent::splitLanguages((string) $page['post_content']);
        $label = sprintf('Page %d « %s »', $wordPressId, $title['fr']);
        $draft = in_array($wordPressId, $this->options->draftPageIds, true);
        if ($draft) {
            $this->report->info("$label reprise en brouillon : à retravailler avant publication (Q-P4-4)");
        }
        $existing = $this->pages->findIdByLegacyId($wordPressId);
        $slug = $this->slugOf($page);
        if (CmsRules::isReservedSlug($slug)) {
            $slug .= '-page';
        }
        $values = [
            'slug' => $this->uniqueSlug($slug, fn (string $candidate): bool => $this->pages->slugExists($candidate, $existing)),
            'title_fr' => mb_substr($title['fr'], 0, 255),
            'title_en' => mb_substr($title['en'], 0, 255),
            'body_fr' => $this->convertBody($content['fr'], $label),
            'body_en' => $this->convertBody($content['en'], $label),
            'status' => $draft ? CmsRules::STATUS_DRAFT : CmsRules::STATUS_PUBLISHED,
            'legacy_wp_id' => $wordPressId,
            'updated_at' => (string) $page['post_modified'],
            'updated_by' => self::IMPORT_USER,
        ] + $this->seo($wordPressId);
        $this->newPaths[$wordPressId] = '/' . $values['slug'];
        $this->report->count('pages');
        if ($this->options->dryRun) {
            return null;
        }
        if ($existing === null) {
            $existing = $this->pages->insert($values + ['created_at' => (string) $page['post_date']]);
        } else {
            $this->pages->update($existing, $values);
        }
        $this->albums->collect(ContentAlbums::PAGES, [$values['body_fr'], $values['body_en']]);

        return $existing;
    }

    /**
     * Rattache les pages reprises à leur parent s'il est repris aussi.
     *
     * @param list<array<string, mixed>> $pages
     * @param array<int, int|null>       $pageIds
     */
    private function linkPageParents(array $pages, array $pageIds): void
    {
        foreach ($pages as $page) {
            $id = $pageIds[(int) $page['ID']] ?? null;
            $parentId = $pageIds[(int) $page['post_parent']] ?? null;
            if ($id !== null) {
                $this->pages->update($id, ['parent_id' => $parentId]);
            }
            if ($parentId !== null) {
                $this->newPaths[(int) $page['ID']] = $this->newPaths[(int) $page['post_parent']] . $this->newPaths[(int) $page['ID']];
            }
        }
    }

    // ---------------------------------------------------------------- contenu

    /** HTML WordPress → HTML du site : galeries, médias rapatriés, liens réécrits, paragraphes, filtre. */
    private function convertBody(string $html, string $label): string
    {
        if (trim($html) === '') {
            return '';
        }
        $html = (string) preg_replace_callback('/\[gallery[^\]]*\]/i', fn (array $match): string => $this->gallery($match[0], $label), $html);
        foreach (WordPressContent::shortcodes($html) as $shortcode) {
            if (!in_array($shortcode, ['caption', 'embed'], true)) {
                $this->report->warning("$label : shortcode [$shortcode] non converti");
            }
        }
        $html = (string) preg_replace('~\[embed\](.*?)\[/embed\]~i', '<iframe src="$1"></iframe>', $html);
        $html = (string) preg_replace('~\[caption[^\]]*\](.*?)\[/caption\]~is', '<figure>$1</figure>', $html);
        $html = (string) preg_replace_callback($this->uploadsPattern, fn (array $match): string => $this->mediaUrl($match[1], $label), $html);
        $html = $this->rewriteInternalLinks($html, $label);

        return $this->sanitizer->sanitize(WordPressContent::autop($html));
    }

    private function gallery(string $shortcode, string $label): string
    {
        $figures = '';
        foreach (WordPressContent::galleryIds($shortcode) as $attachmentId) {
            $file = $this->attachmentFile($attachmentId);
            if ($file === null) {
                $this->report->warning("$label : image $attachmentId de la galerie introuvable");
                continue;
            }
            $url = $this->mediaUrl($file, $label);
            $figures .= sprintf('<figure><img src="%s" alt=""></figure>', htmlspecialchars($url, ENT_QUOTES));
        }

        return $figures;
    }

    /** Rapatrie un fichier de wp-content/uploads et renvoie son adresse sur le site (l'ancienne si échec). */
    private function mediaUrl(string $relative, string $label): string
    {
        $relative = rawurldecode($relative);
        $legacyUrl = $this->uploadsUrl . $relative;
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!in_array($extension, self::IMPORTED_EXTENSIONS, true)) {
            $this->report->warning("$label : fichier $relative non repris (type $extension)");

            return $legacyUrl;
        }
        if ($this->options->dryRun) {
            $this->report->count('media');

            return $legacyUrl;
        }
        // The original rather than a WordPress thumbnail (« photo-300x200.jpg »).
        $candidates = array_unique([(string) preg_replace(self::RESIZED_SUFFIX, '$1', $relative), $relative]);
        foreach ($candidates as $candidate) {
            $local = $this->fetch($candidate);
            if ($local === null) {
                continue;
            }
            try {
                $media = $this->media->importFile($local, basename($candidate), $this->uploadsUrl . $candidate, [], self::IMPORT_USER);
                $this->report->count('media');

                return strtok((string) $media['url'], '?') ?: (string) $media['url'];
            } catch (ValidationErrors $errors) {
                $this->report->warning(sprintf('%s : média %s refusé (%s)', $label, $candidate, implode(', ', $errors->all())));
            } finally {
                if ($this->options->uploadsDir === null) {
                    @unlink($local);
                }
            }
        }
        $this->report->warning("$label : média $relative introuvable");

        return $legacyUrl;
    }

    /** Chemin local du fichier : répertoire uploads monté, sinon téléchargé depuis le site WordPress. */
    private function fetch(string $relative): ?string
    {
        if ($this->options->uploadsDir !== null) {
            $path = rtrim($this->options->uploadsDir, '/') . '/' . $relative;

            return is_file($path) ? $path : null;
        }
        try {
            $response = $this->httpClient->request('GET', $this->uploadsUrl . str_replace('%2F', '/', rawurlencode($relative)), ['timeout' => 30]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $path = (string) tempnam(sys_get_temp_dir(), 'wpimport-');
            file_put_contents($path, $response->getContent());

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    private function rewriteInternalLinks(string $html, string $label): string
    {
        $site = preg_quote(preg_replace('~^https?://(www\.)?~', '', rtrim($this->options->siteUrl, '/')) ?? '', '~');

        return (string) preg_replace_callback(
            '~href=(["\'])(?:https?://(?:www\.)?' . $site . ')?(/\?(?:p|page_id)=(\d+)[^"\']*)\1~i',
            function (array $match) use ($label): string {
                $path = $this->newPaths[(int) $match[3]] ?? null;
                if ($path === null) {
                    $this->report->warning("$label : lien vers le contenu WordPress {$match[3]} non repris → /news");
                    $path = '/news';
                }

                return 'href=' . $match[1] . $path . $match[1];
            },
            $html,
        );
    }

    private function featuredImage(int $wordPressId, string $label): ?int
    {
        $thumbnail = $this->wordpress->fetchOne("SELECT meta_value FROM {$this->table('postmeta')} WHERE post_id = ? AND meta_key = '_thumbnail_id'", [$wordPressId]);
        if ($thumbnail === false) {
            return null;
        }
        $file = $this->attachmentFile((int) $thumbnail);
        if ($file === null || $this->options->dryRun) {
            return null;
        }
        $url = $this->mediaUrl($file, $label);

        return str_starts_with($url, MediaUrls::PUBLIC_PREFIX) ? $this->mediaIdOf($url) : null;
    }

    /** wp-content/uploads d'après les réglages WordPress (`upload_url_path`, sinon `siteurl`), sinon d'après --site-url. */
    private function wordPressUploadsUrl(): string
    {
        $settings = $this->wordpress->fetchAllKeyValue(
            "SELECT option_name, option_value FROM {$this->table('options')} WHERE option_name IN ('siteurl', 'upload_url_path')",
        );
        if (trim((string) ($settings['upload_url_path'] ?? '')) !== '') {
            return rtrim((string) $settings['upload_url_path'], '/') . '/';
        }
        $siteUrl = trim((string) ($settings['siteurl'] ?? '')) ?: $this->options->siteUrl;

        return rtrim($siteUrl, '/') . '/wp-content/uploads/';
    }

    private function attachmentFile(int $attachmentId): ?string
    {
        $file = $this->wordpress->fetchOne("SELECT meta_value FROM {$this->table('postmeta')} WHERE post_id = ? AND meta_key = '_wp_attached_file'", [$attachmentId]);

        return $file === false ? null : (string) $file;
    }

    private function mediaIdOf(string $url): ?int
    {
        $media = $this->mediaRepository->findByPath(substr($url, strlen(MediaUrls::PUBLIC_PREFIX)));

        return $media === null ? null : (int) $media['id'];
    }

    /** @return array<string, string> champs SEO depuis Rank Math, sans ses variables (%title%…) */
    private function seo(int $wordPressId): array
    {
        $meta = $this->wordpress->fetchAllKeyValue(
            "SELECT meta_key, meta_value FROM {$this->table('postmeta')} WHERE post_id = ? AND meta_key IN ('rank_math_title', 'rank_math_description')",
            [$wordPressId],
        );
        $values = [];
        foreach (['title' => 'rank_math_title', 'description' => 'rank_math_description'] as $field => $key) {
            $texts = WordPressContent::splitLanguages((string) ($meta[$key] ?? ''));
            foreach (CmsRules::LANGUAGES as $language) {
                $text = str_contains($texts[$language], '%') ? '' : $texts[$language];
                $values["seo_{$field}_$language"] = mb_substr($text, 0, $field === 'title' ? 255 : 300);
            }
        }

        return $values;
    }

    // ---------------------------------------------------------------- redirections

    /**
     * Permaliens repris → nouvelles adresses ; autres articles publiés → /news (301).
     *
     * @param list<array<string, mixed>> $posts
     * @param list<array<string, mixed>> $pages
     * @param array<int, int|null>       $pageIds
     */
    private function createRedirects(array $posts, array $pages, array $pageIds): void
    {
        $structure = (string) ($this->wordpress->fetchOne("SELECT option_value FROM {$this->table('options')} WHERE option_name = 'permalink_structure'") ?: '');
        $redirects = [];
        foreach ($posts as $post) {
            $target = $this->newPaths[(int) $post['ID']];
            $redirects['/?p=' . $post['ID']] = $target;
            $redirects[WordPressContent::permalink($structure, $post)] = $target;
        }
        foreach ($pages as $page) {
            $target = $this->newPaths[(int) $page['ID']];
            $redirects['/?page_id=' . $page['ID']] = $target;
            $redirects['/' . $page['post_name'] . '/'] = $target;
        }
        $imported = array_map(static fn (array $post): int => (int) $post['ID'], $posts);
        foreach ($this->wordpress->fetchAllAssociative("SELECT ID, post_name, post_date FROM {$this->table('posts')} WHERE post_type = 'post' AND post_status = 'publish'") as $post) {
            if (!in_array((int) $post['ID'], $imported, true)) {
                $redirects['/?p=' . $post['ID']] = '/news';
                $redirects[WordPressContent::permalink($structure, $post)] = '/news';
            }
        }
        foreach ($redirects as $from => $to) {
            if ($from !== $to && strlen($from) <= 255) {
                if (!$this->options->dryRun) {
                    $this->site->saveRedirect($from, $to);
                }
                $this->report->count('redirects');
            }
        }
    }

    // ---------------------------------------------------------------- Ninja Forms

    private function importNinjaForm(int $ninjaFormId): void
    {
        $form = $this->wordpress->fetchAssociative("SELECT * FROM {$this->table('nf3_forms')} WHERE id = ?", [$ninjaFormId]);
        if ($form === false) {
            $this->report->warning("Formulaire Ninja Forms $ninjaFormId introuvable");

            return;
        }
        $fields = [];
        $keys = [];
        foreach ($this->wordpress->fetchAllAssociative("SELECT * FROM {$this->table('nf3_fields')} WHERE parent_id = ? ORDER BY `order`, id", [$ninjaFormId]) as $field) {
            $type = WordPressContent::ninjaFieldType((string) $field['type']);
            if ($type === null) {
                continue;
            }
            $meta = $this->wordpress->fetchAllKeyValue("SELECT `key`, value FROM {$this->table('nf3_field_meta')} WHERE parent_id = ?", [$field['id']]);
            $key = $this->uniqueKey(str_replace('-', '_', CmsRules::slugify((string) ($field['key'] ?: $field['label']))) ?: 'champ', $keys);
            $keys[] = $key;
            $options = [];
            $serialized = @unserialize((string) ($meta['options'] ?? ''), ['allowed_classes' => false]);
            foreach (is_array($serialized) ? $serialized : [] as $option) {
                $value = trim((string) ($option['value'] ?? $option['label'] ?? ''));
                if ($value !== '' && !in_array($value, array_column($options, 'value'), true)) {
                    $options[] = ['value' => $value, 'label_fr' => trim((string) ($option['label'] ?? $value)), 'label_en' => ''];
                }
            }
            $fields[] = [
                'field_key' => $key,
                'type' => $type,
                'label_fr' => mb_substr(WordPressContent::splitLanguages((string) $field['label'])['fr'], 0, 255),
                'label_en' => '',
                'required' => (int) ($field['required'] ?? $meta['required'] ?? 0) === 1 ? 1 : 0,
                'help_fr' => mb_substr(strip_tags((string) ($meta['desc_text'] ?? '')), 0, 500),
                'help_en' => '',
                'options' => json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'min_value' => null,
                'max_value' => null,
            ];
        }
        $title = WordPressContent::splitLanguages((string) $form['title']);
        $existing = $this->forms->findIdByLegacyNinjaId($ninjaFormId);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s');
        $values = [
            'slug' => $this->uniqueSlug(CmsRules::slugify($title['fr']) ?: 'formulaire', fn (string $slug): bool => $this->forms->slugExists($slug, $existing)),
            'title_fr' => mb_substr($title['fr'], 0, 255),
            'title_en' => mb_substr($title['en'], 0, 255),
            'intro_fr' => '',
            'intro_en' => '',
            'status' => 'draft', // exemple, jamais publié (Q-P4-8)
            'confirmation_text_fr' => '',
            'confirmation_text_en' => '',
            'purpose_fr' => '',
            'purpose_en' => '',
            'legacy_nf_id' => $ninjaFormId,
            'updated_at' => $now,
            'updated_by' => self::IMPORT_USER,
        ];
        $this->report->info(sprintf('Formulaire Ninja Forms %d « %s » repris en brouillon avec %d champ(s), sans les inscriptions', $ninjaFormId, $title['fr'], count($fields)));
        $this->report->count('forms');
        if ($this->options->dryRun) {
            return;
        }
        $id = $existing ?? $this->forms->insert($values + ['created_at' => $now]);
        if ($existing !== null) {
            $this->forms->update($existing, $values);
        }
        $this->forms->replaceFields($id, $fields);
    }

    // ---------------------------------------------------------------- outils

    private function table(string $name): string
    {
        return '`' . str_replace('`', '', $this->options->prefix . $name) . '`';
    }

    /** @param array<string, mixed> $post */
    private function slugOf(array $post): string
    {
        $slug = CmsRules::slugify(rawurldecode((string) $post['post_name']));

        return $slug !== '' ? $slug : (CmsRules::slugify(WordPressContent::splitLanguages((string) $post['post_title'])['fr']) ?: 'contenu-' . $post['ID']);
    }

    /** @param callable(string): bool $taken */
    private function uniqueSlug(string $slug, callable $taken): string
    {
        $candidate = $slug;
        for ($suffix = 2; $taken($candidate); ++$suffix) {
            $candidate = sprintf('%s-%d', substr($slug, 0, CmsRules::SLUG_MAX_LENGTH - 4), $suffix);
        }

        return $candidate;
    }

    /** @param list<string> $keys */
    private function uniqueKey(string $key, array $keys): string
    {
        $key = substr($key, 0, 36);
        $candidate = $key;
        for ($suffix = 2; in_array($candidate, $keys, true); ++$suffix) {
            $candidate = $key . '_' . $suffix;
        }

        return $candidate;
    }
}
