<?php

namespace App\Controller;

use App\Cms\App3CachePurger;
use App\Cms\EditorialAccess;
use App\Cms\SiteEditor;
use App\Cms\ValidationErrors;
use App\Security\EditorialVoter;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Menu « Le kayak-polo », accueil, redirections et capacité Rédacteur : profils 1–2 seulement (PAGE_CMS.md § 5,
 * ADM-CMS-01, ADM-CMS-05).
 */
#[Route('/admin/cms')]
#[IsGranted(EditorialVoter::ADMIN)]
#[OA\Tag(name: '40. App4 - Editorial')]
class AdminCmsSiteController extends AdminCmsBaseController
{
    public function __construct(
        Connection $connection,
        App3CachePurger $purger,
        private readonly SiteEditor $site,
        private readonly EditorialAccess $access,
    ) {
        parent::__construct($connection, $purger);
    }

    #[Route('/menu', name: 'admin_cms_menu', methods: ['GET'])]
    public function menu(): JsonResponse
    {
        return $this->ok($this->site->menu());
    }

    #[Route('/menu', name: 'admin_cms_menu_save', methods: ['PUT'])]
    public function saveMenu(Request $request): JsonResponse
    {
        try {
            $this->site->saveMenu(self::body($request)['items'] ?? null);
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }
        $this->journal('Modification menu éditorial', 'Menu');
        // The menu is part of every page.
        $this->purge(['/*']);

        return $this->ok($this->site->menu());
    }

    #[Route('/home-blocks', name: 'admin_cms_home', methods: ['GET'])]
    public function homeBlocks(): JsonResponse
    {
        return $this->ok($this->site->homeBlocks());
    }

    #[Route('/home-blocks', name: 'admin_cms_home_save', methods: ['PUT'])]
    public function saveHomeBlocks(Request $request): JsonResponse
    {
        try {
            $this->site->saveHomeBlocks(self::body($request)['blocks'] ?? null);
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }
        $this->journal('Modification accueil', 'Blocs d\'accueil');
        $this->purge(['/']);

        return $this->ok($this->site->homeBlocks());
    }

    #[Route('/redirects', name: 'admin_cms_redirects', methods: ['GET'])]
    public function redirects(): JsonResponse
    {
        return $this->ok($this->site->redirects());
    }

    #[Route('/redirects', name: 'admin_cms_redirect_save', methods: ['POST'])]
    public function saveRedirect(Request $request): JsonResponse
    {
        try {
            $id = $this->site->saveRedirect(self::body($request));
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }
        $this->journal('Ajout redirection', sprintf('Redirection %d', $id));

        return $this->ok(['id' => $id], 201);
    }

    #[Route('/redirects/{id}', name: 'admin_cms_redirect_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteRedirect(int $id): JsonResponse
    {
        if (!$this->site->deleteRedirect($id)) {
            return $this->notFound();
        }
        $this->journal('Suppression redirection', sprintf('Redirection %d', $id));

        return $this->ok(['deleted' => true]);
    }

    #[Route('/editors', name: 'admin_cms_editors', methods: ['GET'])]
    public function editors(): JsonResponse
    {
        return $this->ok($this->access->editors());
    }

    #[Route('/editors/{code}', name: 'admin_cms_editor_set', methods: ['PUT'], requirements: ['code' => '[A-Za-z0-9_-]{1,8}'])]
    public function setEditor(string $code, Request $request): JsonResponse
    {
        if (!$this->access->userExists($code)) {
            return $this->notFound();
        }
        $editor = (bool) (self::body($request)['editor'] ?? false);
        $this->access->setEditor($code, $editor);
        $this->journal($editor ? 'Ajout capacité Rédacteur' : 'Retrait capacité Rédacteur', $code);

        return $this->ok(['code' => $code, 'editor' => $editor]);
    }
}
