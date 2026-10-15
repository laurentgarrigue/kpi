<?php

namespace App\Controller;

use App\Cms\ArticleLinks;
use App\Cms\CmsRules;
use App\Cms\NewsService;
use App\Cms\PageService;
use App\Cms\PreviewToken;
use App\Cms\SiteContentService;
use App\Http\UnicodeJsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contenus éditoriaux publics du site (DOC/specs/public/FEATURE_CMS.md § 5.1) : actualités, pages, menu, accueil,
 * albums, RSS, plan du site, redirections. Lecture seule ; paramètre `lang=fr|en` (repli sur le français).
 * Mêmes conventions que PublicSiteController : inconnu → 404, paramètre invalide → 400.
 */
#[OA\Tag(name: self::DOC_TAG)]
class PublicCmsController extends AbstractController
{
    private const DOC_TAG = '7. Site public';
    private const MAX_AGE = 60;
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    private const MAX_PATH_LENGTH = 255;

    public function __construct(
        private readonly NewsService $news,
        private readonly PageService $pages,
        private readonly SiteContentService $site,
        private readonly PreviewToken $previewToken,
    ) {
    }

    #[Route('/news', name: 'public_news', methods: ['GET'])]
    #[OA\Get(
        path: '/news',
        summary: 'Published news, newest first, 12 per page; filters by category or linked competition / event / club',
        tags: [self::DOC_TAG],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'competition', in: 'query', required: false, description: '{season}/{code}', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'event', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'club', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 12)),
            new OA\Parameter(name: 'lang', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: CmsRules::LANGUAGES)),
        ],
    )]
    public function news(Request $request): JsonResponse
    {
        $page = self::positiveInteger($request->query->getString('page', '1'));
        $limit = self::positiveInteger($request->query->getString('limit', (string) NewsService::PAGE_SIZE));
        $category = $request->query->getString('category') ?: null;
        $link = $this->linkFilter($request);
        if ($page === null || $limit === null || $limit > NewsService::PAGE_SIZE || $link === false) {
            return $this->invalidParameter();
        }
        $list = $this->news->list($page, $category, $link, self::language($request), $limit);

        return $list === null ? $this->invalidParameter() : $this->cached($list);
    }

    #[Route('/news/categories', name: 'public_news_categories', methods: ['GET'], priority: 10)]
    #[OA\Get(path: '/news/categories', summary: 'The news categories, in display order', tags: [self::DOC_TAG])]
    public function categories(Request $request): JsonResponse
    {
        return $this->cached($this->news->categories(self::language($request)));
    }

    #[Route('/news/{slug}', name: 'public_news_article', methods: ['GET'])]
    #[OA\Get(
        path: '/news/{slug}',
        summary: 'A published article (or an unpublished one with a valid preview token)',
        tags: [self::DOC_TAG],
        parameters: [new OA\Parameter(name: 'preview', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
    )]
    public function article(string $slug, Request $request): JsonResponse
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return $this->invalidParameter();
        }
        $token = $request->query->getString('preview') ?: null;
        $article = $this->news->article($slug, self::language($request), $token === null ? null : fn (int $id): bool => $this->previewToken->isValid($token, 'article', $id));
        if ($article === null) {
            return $this->notFound();
        }

        return $article->preview ? $this->private($article) : $this->cached($article);
    }

    #[Route('/pages/{slug}', name: 'public_page', methods: ['GET'])]
    #[OA\Get(path: '/pages/{slug}', summary: 'A published editorial page, with its full path, parents and children', tags: [self::DOC_TAG])]
    public function page(string $slug, Request $request): JsonResponse
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return $this->invalidParameter();
        }
        $token = $request->query->getString('preview') ?: null;
        $page = $this->pages->page($slug, self::language($request), $token === null ? null : fn (int $id): bool => $this->previewToken->isValid($token, 'page', $id));
        if ($page === null) {
            return $this->notFound();
        }

        return $page->preview ? $this->private($page) : $this->cached($page);
    }

    #[Route('/menu', name: 'public_menu', methods: ['GET'])]
    #[OA\Get(path: '/menu', summary: 'Editorial menu « Le kayak-polo » (published targets only)', tags: [self::DOC_TAG])]
    public function menu(Request $request): JsonResponse
    {
        return $this->cached($this->site->menu(self::language($request)));
    }

    #[Route('/home', name: 'public_home', methods: ['GET'])]
    #[OA\Get(path: '/home', summary: 'Enabled, non-empty home blocks in order: alert, featured, latest news, partners', tags: [self::DOC_TAG])]
    public function home(Request $request): JsonResponse
    {
        return $this->cached($this->site->home(self::language($request)));
    }

    #[Route('/albums/{slug}', name: 'public_album', methods: ['GET'])]
    #[OA\Get(path: '/albums/{slug}', summary: 'A gallery album and its media', tags: [self::DOC_TAG])]
    public function album(string $slug, Request $request): JsonResponse
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return $this->invalidParameter();
        }
        $album = $this->site->album($slug, self::language($request));

        return $album === null ? $this->notFound() : $this->cached($album);
    }

    #[Route('/rss.xml', name: 'public_rss', methods: ['GET'])]
    #[OA\Get(path: '/rss.xml', summary: 'RSS 2.0 feed of the 20 latest articles', tags: [self::DOC_TAG])]
    public function rss(Request $request): Response
    {
        $response = new Response($this->site->rss(self::language($request)), 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);

        return $response;
    }

    #[Route('/sitemap/content.xml', name: 'public_sitemap_content', methods: ['GET'])]
    #[OA\Get(path: '/sitemap/content.xml', summary: 'Paths of the published articles and pages (aggregated by the site sitemap)', tags: [self::DOC_TAG])]
    public function sitemap(): JsonResponse
    {
        return $this->cached($this->site->sitemapEntries());
    }

    #[Route('/redirect', name: 'public_redirect', methods: ['GET'])]
    #[OA\Get(
        path: '/redirect',
        summary: 'Known redirection of an old path (WordPress permalinks…)',
        tags: [self::DOC_TAG],
        parameters: [new OA\Parameter(name: 'path', in: 'query', required: true, schema: new OA\Schema(type: 'string'))],
    )]
    public function oldPathRedirect(Request $request): JsonResponse
    {
        $path = $request->query->getString('path');
        if ($path === '' || !str_starts_with($path, '/') || strlen($path) > self::MAX_PATH_LENGTH) {
            return $this->invalidParameter();
        }
        $redirect = $this->site->redirect($path);

        return $redirect === null ? $this->notFound() : $this->cached($redirect);
    }

    /** @return array{kind: string, ref: string}|false|null filtre par lien, aucun, ou invalide (false) */
    private function linkFilter(Request $request): array|false|null
    {
        foreach (['competition', 'event', 'club'] as $kind) {
            $ref = $request->query->getString($kind);
            if ($ref !== '') {
                return ArticleLinks::isValidRef($kind, $ref) ? ['kind' => $kind, 'ref' => $ref] : false;
            }
        }

        return null;
    }

    private static function language(Request $request): string
    {
        return CmsRules::language($request->query->getString('lang') ?: null);
    }

    private static function positiveInteger(string $value): ?int
    {
        return preg_match('/^[1-9]\d{0,5}$/', $value) === 1 ? (int) $value : null;
    }

    private function cached(mixed $data): JsonResponse
    {
        $response = new UnicodeJsonResponse($data);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);

        return $response;
    }

    /** Prévisualisation : jamais mise en cache (CMS-12). */
    private function private(mixed $data): JsonResponse
    {
        $response = new UnicodeJsonResponse($data);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function notFound(): JsonResponse
    {
        return new UnicodeJsonResponse(['error' => 'not_found'], 404);
    }

    private function invalidParameter(): JsonResponse
    {
        return new UnicodeJsonResponse(['error' => 'invalid_parameter'], 400);
    }
}
