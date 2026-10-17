<?php

namespace App\Controller;

use App\Cms\App3CachePurger;
use App\Cms\ArticleEditor;
use App\Cms\CategoryRepository;
use App\Cms\CmsRules;
use App\Cms\PageEditor;
use App\Cms\PreviewToken;
use App\Cms\ValidationErrors;
use App\Security\EditorialVoter;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Articles et pages du site public (PAGE_CMS.md § 2–3) : Rédacteurs et profils 1–2 ; suppression : profils 1–2. */
#[Route('/admin/cms')]
#[IsGranted(EditorialVoter::EDIT)]
#[OA\Tag(name: '40. App4 - Editorial')]
class AdminCmsContentController extends AdminCmsBaseController
{
    public function __construct(
        Connection $connection,
        App3CachePurger $purger,
        private readonly ArticleEditor $articles,
        private readonly PageEditor $pages,
        private readonly CategoryRepository $categories,
        private readonly PreviewToken $previewToken,
    ) {
        parent::__construct($connection, $purger);
    }

    #[Route('/categories', name: 'admin_cms_categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        return $this->ok(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'label_fr' => $row['label_fr'],
            'label_en' => $row['label_en'],
        ], $this->categories->all()));
    }

    // ---------------------------------------------------------------- articles

    #[Route('/articles', name: 'admin_cms_articles', methods: ['GET'])]
    public function articles(Request $request): JsonResponse
    {
        $status = $request->query->getString('status') ?: null;
        if ($status !== null && !in_array($status, [...CmsRules::STATUSES, 'scheduled'], true)) {
            $status = null;
        }
        $category = filter_var($request->query->get('category'), FILTER_VALIDATE_INT);

        return $this->ok($this->articles->list($status, is_int($category) ? $category : null, $request->query->getString('search') ?: null, self::pageNumber($request)));
    }

    #[Route('/articles/{id}', name: 'admin_cms_article', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function article(int $id): JsonResponse
    {
        $article = $this->articles->get($id);

        return $article === null ? $this->notFound() : $this->ok($article);
    }

    #[Route('/articles', name: 'admin_cms_article_create', methods: ['POST'])]
    public function createArticle(Request $request): JsonResponse
    {
        return $this->saveArticle(null, $request);
    }

    #[Route('/articles/{id}', name: 'admin_cms_article_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateArticle(int $id, Request $request): JsonResponse
    {
        return $this->articles->get($id) === null ? $this->notFound() : $this->saveArticle($id, $request);
    }

    #[Route('/articles/{id}', name: 'admin_cms_article_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(EditorialVoter::ADMIN)]
    public function deleteArticle(int $id): JsonResponse
    {
        $paths = $this->articles->delete($id);
        if ($paths === null) {
            return $this->notFound();
        }
        $this->journal('Suppression article', sprintf('Article %d', $id));
        $this->purge($paths);

        return $this->ok(['deleted' => true]);
    }

    #[Route('/articles/{id}/preview-token', name: 'admin_cms_article_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function articlePreview(int $id): JsonResponse
    {
        return $this->articles->get($id) === null ? $this->notFound() : $this->ok(['token' => $this->previewToken->create('article', $id)]);
    }

    // ---------------------------------------------------------------- pages

    #[Route('/pages', name: 'admin_cms_pages', methods: ['GET'])]
    public function pages(): JsonResponse
    {
        return $this->ok($this->pages->list());
    }

    #[Route('/pages/{id}', name: 'admin_cms_page', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function page(int $id): JsonResponse
    {
        $page = $this->pages->get($id);

        return $page === null ? $this->notFound() : $this->ok($page);
    }

    #[Route('/pages', name: 'admin_cms_page_create', methods: ['POST'])]
    public function createPage(Request $request): JsonResponse
    {
        return $this->savePage(null, $request);
    }

    #[Route('/pages/{id}', name: 'admin_cms_page_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updatePage(int $id, Request $request): JsonResponse
    {
        return $this->pages->get($id) === null ? $this->notFound() : $this->savePage($id, $request);
    }

    #[Route('/pages/{id}', name: 'admin_cms_page_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(EditorialVoter::ADMIN)]
    public function deletePage(int $id): JsonResponse
    {
        $paths = $this->pages->delete($id);
        if ($paths === null) {
            return $this->notFound();
        }
        $this->journal('Suppression page', sprintf('Page %d', $id));
        $this->purge($paths);

        return $this->ok(['deleted' => true]);
    }

    #[Route('/pages/{id}/preview-token', name: 'admin_cms_page_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pagePreview(int $id): JsonResponse
    {
        return $this->pages->get($id) === null ? $this->notFound() : $this->ok(['token' => $this->previewToken->create('page', $id)]);
    }

    // ---------------------------------------------------------------- privé

    private function saveArticle(?int $id, Request $request): JsonResponse
    {
        try {
            $saved = $this->articles->save($id, self::body($request), $this->userCode());
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }
        $this->journal($id === null ? 'Création article' : 'Modification article', sprintf('Article %d', $saved['id']));
        $this->purge($saved['paths']);

        return $this->ok($this->articles->get($saved['id']), $id === null ? 201 : 200);
    }

    private function savePage(?int $id, Request $request): JsonResponse
    {
        try {
            $saved = $this->pages->save($id, self::body($request), $this->userCode());
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }
        $this->journal($id === null ? 'Création page' : 'Modification page', sprintf('Page %d', $saved['id']));
        $this->purge($saved['paths']);

        return $this->ok($this->pages->get($saved['id']), $id === null ? 201 : 200);
    }
}
