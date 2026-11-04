<?php

namespace App\Controller;

use App\Entity\User;
use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfAccessDenied;
use App\Pdf\LegacyPdfLinks;
use App\Pdf\PdfAccessChecker;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocuments;
use App\Pdf\PdfJournal;
use App\Pdf\PdfLinkSigner;
use App\Pdf\PdfRenderer;
use App\Pdf\PdfRenderingFailed;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\Pdf\PublicPdfThrottle;
use App\Security\MandateApplier;
use App\Security\UserProvider;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * PDF documents rendered by Gotenberg (DOC/specs/PDF_GOTENBERG.md § 6): without any session, the whole context is
 * in the query. Public documents show published data only; admin documents require the role of the document and the
 * perimeter of the active mandate, through the JWT or a short-lived signed link.
 */
#[OA\Tag(name: self::DOC_TAG)]
class PdfController extends AbstractController
{
    private const DOC_TAG = '8. PDF';

    /** Public documents change at most after each game (§ 6.2). */
    private const PUBLIC_MAX_AGE = 300;

    /** Dev-only HTML preview of a page, before Gotenberg (§ 8.6). */
    private const PREVIEW_PARAMETER = 'format';

    public function __construct(
        private readonly PdfDocuments $documents,
        private readonly PdfRenderer $renderer,
        private readonly PdfAccessChecker $access,
        private readonly PdfLinkSigner $signer,
        private readonly LegacyPdfLinks $legacyLinks,
        private readonly PublicPdfThrottle $throttle,
        private readonly UserProvider $users,
        private readonly MandateApplier $mandates,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PdfJournal $journal,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        // Public base URL of api2, /api2 included: Traefik strips that prefix, the request does not know it.
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $publicBaseUrl,
    ) {
    }

    #[Route('/pdf/{document}', name: 'pdf_public', methods: ['GET'], requirements: ['document' => '[a-z-]+'])]
    #[OA\Get(path: '/pdf/{document}', summary: 'Public PDF document (published data only); 30 per minute per IP', tags: [self::DOC_TAG])]
    public function publicDocument(string $document, Request $request): Response
    {
        $retryAfter = $this->throttle->hit($request->getClientIp());
        if ($retryAfter !== null) {
            return $this->error('Too many requests.', Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $retryAfter]);
        }
        if (!PdfCatalog::isPublicDocument($document)) {
            return $this->error('Unknown document.', Response::HTTP_NOT_FOUND);
        }

        return $this->respond($document, PdfTheme::Public, $request, $request->query->all());
    }

    #[Route('/admin/pdf/{document}', name: 'pdf_admin', methods: ['GET'], requirements: ['document' => '[a-z-]+'])]
    #[OA\Get(path: '/admin/pdf/{document}', summary: 'Admin PDF document (role and perimeter of the active mandate)', tags: [self::DOC_TAG])]
    public function adminDocument(string $document, Request $request): Response
    {
        return $this->respond($document, PdfTheme::Admin, $request, $request->query->all());
    }

    #[Route('/admin/pdf/{document}/link', name: 'pdf_admin_link', methods: ['POST'], requirements: ['document' => '[a-z-]+'])]
    #[OA\Post(path: '/admin/pdf/{document}/link', summary: 'Short-lived signed link to an admin PDF (5 minutes)', tags: [self::DOC_TAG])]
    public function adminLink(string $document, Request $request): JsonResponse
    {
        $query = $this->bodyQuery($request);
        $provider = $this->documents->find($document, PdfTheme::Admin);
        if ($provider === null || !PdfCatalog::isAdminDocument($document)) {
            return $this->error('Unknown document.', Response::HTTP_NOT_FOUND);
        }
        try {
            $pdfRequest = PdfRequest::fromQuery($query, $provider->options());
        } catch (InvalidPdfRequest $e) {
            return $this->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        if (!$this->access->canGet($document, $pdfRequest)) {
            return $this->error('Access denied.', Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $parameters = $this->signer->sign(self::signedPath($document), [
            ...$pdfRequest->toQuery(),
            'user' => $user->getCode(),
            'mandate' => $user->getActiveMandateId() ?? 0,
        ]);

        return new JsonResponse([
            'url' => rtrim($this->publicBaseUrl, '/') . self::signedPath($document) . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986),
            'expires_in' => PdfLinkSigner::TTL_SECONDS,
        ]);
    }

    #[Route('/admin/pdf/legacy-link', name: 'pdf_admin_legacy_link', methods: ['POST'], priority: 1)]
    #[OA\Post(path: '/admin/pdf/legacy-link', summary: 'Short-lived signed link to a legacy admin PDF page (phase 0)', tags: [self::DOC_TAG])]
    public function legacyLink(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $page = is_array($body) ? ($body['page'] ?? null) : null;
        $parameters = is_array($body) ? ($body['query'] ?? []) : [];
        if (!is_string($page) || !LegacyPdfLinks::isLegacyPage($page) || !is_array($parameters)) {
            return $this->error('Unknown legacy PDF page.', Response::HTTP_NOT_FOUND);
        }
        try {
            $link = $this->legacyLinks->sign($page, $parameters);
        } catch (InvalidPdfRequest $e) {
            return $this->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        if ($link === null) {
            return $this->error('Access denied.', Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse([...$link, 'expires_in' => PdfLinkSigner::TTL_SECONDS]);
    }

    #[Route('/pdf-signed/{document}', name: 'pdf_signed', methods: ['GET'], requirements: ['document' => '[a-z-]+'])]
    #[OA\Get(path: '/pdf-signed/{document}', summary: 'Admin PDF through a signed link; rights checked again at download', tags: [self::DOC_TAG])]
    public function signedDocument(string $document, Request $request): Response
    {
        $query = $request->query->all();
        if (!$this->signer->isValid(self::signedPath($document), $query)) {
            return $this->error('Invalid or expired link.', Response::HTTP_FORBIDDEN);
        }
        try {
            $user = $this->users->loadUserByIdentifier((string) ($query['user'] ?? ''));
        } catch (UserNotFoundException) {
            return $this->error('Invalid or expired link.', Response::HTTP_FORBIDDEN);
        }
        if (!$user instanceof User) {
            return $this->error('Invalid or expired link.', Response::HTTP_FORBIDDEN);
        }
        $mandate = (int) ($query['mandate'] ?? 0);
        if ($mandate > 0 && !$this->mandates->apply($user, $mandate)) {
            return $this->error('Access denied.', Response::HTTP_FORBIDDEN);
        }
        // The rights are those of the user now, not when the link was issued (§ 6.3, PDF-AUTH-07).
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        unset($query[PdfLinkSigner::EXPIRES], $query[PdfLinkSigner::SIGNATURE], $query['user'], $query['mandate']);

        return $this->respond($document, PdfTheme::Admin, $request, $query);
    }

    /** @param array<string, mixed> $query */
    private function respond(string $document, PdfTheme $theme, Request $request, array $query): Response
    {
        $provider = $this->documents->find($document, $theme);
        if ($provider === null) {
            return $this->error('Unknown document.', Response::HTTP_NOT_FOUND);
        }

        $preview = $this->environment === 'dev' && ($query[self::PREVIEW_PARAMETER] ?? null) === 'html';
        unset($query[self::PREVIEW_PARAMETER]);

        try {
            $pdfRequest = PdfRequest::fromQuery($query, $provider->options());
        } catch (InvalidPdfRequest $e) {
            return $this->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        if ($theme === PdfTheme::Admin && !$this->access->canGet($document, $pdfRequest)) {
            return $this->error('Access denied.', Response::HTTP_FORBIDDEN);
        }

        try {
            $content = $provider->content($pdfRequest, $theme, $theme === PdfTheme::Admin ? $this->access->allowedClubs() : null);
        } catch (InvalidPdfRequest $e) {
            return $this->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (PdfAccessDenied) {
            return $this->error('Access denied.', Response::HTTP_FORBIDDEN);
        }
        if ($content === null) {
            return $this->error('Nothing to print.', Response::HTTP_NOT_FOUND);
        }

        if ($preview) {
            return new Response($this->renderer->html($content, $theme, $pdfRequest), Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $etag = $content->fingerprint();
        if ($theme === PdfTheme::Public && in_array('"' . $etag . '"', $request->getETags(), true)) {
            return $this->publicCache(new Response(null, Response::HTTP_NOT_MODIFIED), $etag);
        }

        try {
            $pdf = $this->renderer->render($content, $theme, $pdfRequest);
        } catch (PdfRenderingFailed $e) {
            return $this->error($e->getMessage(), $e->getCode(), $e->getCode() === Response::HTTP_SERVICE_UNAVAILABLE
                ? ['Retry-After' => (string) PdfRenderingFailed::RETRY_AFTER_SECONDS]
                : []);
        }

        $response = new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => self::inline($content),
        ]);
        if ($theme === PdfTheme::Public) {
            return $this->publicCache($response, $etag);
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        // Who printed which admin document, in the journal of the admin actions (§ 6.4).
        $user = $this->getUser();
        if ($user instanceof User) {
            $this->journal->record($user->getCode(), $document, $pdfRequest);
        }

        return $response;
    }

    private function publicCache(Response $response, string $etag): Response
    {
        $response->setEtag($etag);
        $response->setPublic();
        $response->setMaxAge(self::PUBLIC_MAX_AGE);

        return $response;
    }

    private static function inline(PdfContent $content): string
    {
        return sprintf('inline; filename="%s"', preg_replace('/[^A-Za-z0-9._-]/', '_', $content->filename));
    }

    private static function signedPath(string $document): string
    {
        return '/pdf-signed/' . $document;
    }

    /** @return array<string, mixed> parameters of a link request, sent as a JSON object */
    private function bodyQuery(Request $request): array
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return [];
        }

        return array_map(static fn ($value) => is_int($value) ? (string) $value : $value, $body);
    }

    /** @param array<string, string> $headers */
    private function error(string $message, int $status, array $headers = []): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status, $headers);
    }
}
