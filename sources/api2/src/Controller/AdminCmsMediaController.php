<?php

namespace App\Controller;

use App\Cms\App3CachePurger;
use App\Cms\MediaLibrary;
use App\Cms\ValidationErrors;
use App\Http\UnicodeJsonResponse;
use App\PublicSite\FixedWindowThrottle;
use App\Security\EditorialVoter;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Médiathèque et albums (PAGE_CMS.md § 4, CMS-04) ; envoi limité à 60 fichiers par heure et par compte. */
#[Route('/admin/cms')]
#[IsGranted(EditorialVoter::EDIT)]
#[OA\Tag(name: '40. App4 - Editorial')]
class AdminCmsMediaController extends AdminCmsBaseController
{
    private const UPLOADS_PER_HOUR = 60;
    private const HOUR = 3600;

    public function __construct(
        Connection $connection,
        App3CachePurger $purger,
        private readonly MediaLibrary $library,
        private readonly FixedWindowThrottle $throttle,
    ) {
        parent::__construct($connection, $purger);
    }

    #[Route('/media', name: 'admin_cms_media', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $type = in_array($request->query->getString('type'), ['image', 'document'], true) ? $request->query->getString('type') : null;
        $album = filter_var($request->query->get('album'), FILTER_VALIDATE_INT);

        return $this->ok($this->library->list($type, is_int($album) ? $album : null, self::pageNumber($request)));
    }

    #[Route('/media', name: 'admin_cms_media_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->validationFailed((new ValidationErrors())->add('file', 'required'));
        }
        $retryAfter = $this->throttle->hit('cms_upload.' . $this->userCode(), self::UPLOADS_PER_HOUR, self::HOUR);
        if ($retryAfter !== null) {
            return new UnicodeJsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $retryAfter]);
        }
        try {
            $media = $this->library->upload($file, $request->request->all(), $this->userCode());
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }
        $this->journal('Ajout média', $media['name']);

        return $this->ok($media, 201);
    }

    #[Route('/media/{id}', name: 'admin_cms_media_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $media = $this->library->updateMetadata($id, self::body($request));
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }

        return $media === null ? $this->notFound() : $this->ok($media);
    }

    /** POST (pas PUT) : PHP ne lit les fichiers multipart que sur POST. */
    #[Route('/media/{id}/file', name: 'admin_cms_media_replace', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function replace(int $id, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->validationFailed((new ValidationErrors())->add('file', 'required'));
        }
        try {
            $media = $this->library->replace($id, $file);
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }
        if ($media === null) {
            return $this->notFound();
        }
        $this->journal('Remplacement média', $media['name']);
        $this->purge(['/', '/news']);

        return $this->ok($media);
    }

    #[Route('/media/{id}', name: 'admin_cms_media_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(EditorialVoter::ADMIN)]
    public function delete(int $id): JsonResponse
    {
        try {
            $deleted = $this->library->delete($id);
        } catch (ValidationErrors $errors) {
            return new UnicodeJsonResponse(['error' => 'in_use', 'errors' => $errors->all()], Response::HTTP_CONFLICT);
        }
        if (!$deleted) {
            return $this->notFound();
        }
        $this->journal('Suppression média', sprintf('Média %d', $id));

        return $this->ok(['deleted' => true]);
    }

    #[Route('/albums', name: 'admin_cms_albums', methods: ['GET'])]
    public function albums(): JsonResponse
    {
        return $this->ok($this->library->albums());
    }

    #[Route('/albums', name: 'admin_cms_album_create', methods: ['POST'])]
    public function createAlbum(Request $request): JsonResponse
    {
        return $this->saveAlbum(null, $request);
    }

    #[Route('/albums/{id}', name: 'admin_cms_album_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateAlbum(int $id, Request $request): JsonResponse
    {
        return $this->saveAlbum($id, $request);
    }

    #[Route('/albums/{id}', name: 'admin_cms_album_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(EditorialVoter::ADMIN)]
    public function deleteAlbum(int $id): JsonResponse
    {
        return $this->library->deleteAlbum($id) ? $this->ok(['deleted' => true]) : $this->notFound();
    }

    private function saveAlbum(?int $id, Request $request): JsonResponse
    {
        try {
            $albumId = $this->library->saveAlbum($id, self::body($request));
        } catch (ValidationErrors $errors) {
            return $this->validationFailed($errors);
        }

        return $this->ok(['id' => $albumId], $id === null ? 201 : 200);
    }
}
