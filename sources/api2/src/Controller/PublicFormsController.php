<?php

namespace App\Controller;

use App\Cms\ArticleLinks;
use App\Cms\CmsRules;
use App\Cms\ValidationErrors;
use App\Forms\FormRejected;
use App\Forms\PublicFormService;
use App\Http\UnicodeJsonResponse;
use App\PublicSite\FixedWindowThrottle;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Formulaires d'inscription publics (DOC/specs/public/FEATURE_FORMS.md § 3–5) : définition, envoi, liste publique.
 * Envoi limité à 5 par 10 minutes, par adresse IP et par formulaire (IP du visiteur relayée par app3).
 */
#[OA\Tag(name: self::DOC_TAG)]
class PublicFormsController extends AbstractController
{
    private const DOC_TAG = '7. Site public';
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    private const SUBMISSIONS_LIMIT = 5;
    private const SUBMISSIONS_WINDOW = 600;
    private const LIST_MAX_AGE = 300;

    public function __construct(
        private readonly PublicFormService $forms,
        private readonly FixedWindowThrottle $throttle,
    ) {
    }

    #[Route('/forms', name: 'public_forms', methods: ['GET'])]
    #[OA\Get(
        path: '/forms',
        summary: 'Published forms attached to an event or a competition',
        tags: [self::DOC_TAG],
        parameters: [
            new OA\Parameter(name: 'event', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'competition', in: 'query', required: false, description: '{season}/{code}', schema: new OA\Schema(type: 'string')),
        ],
    )]
    public function attached(Request $request): JsonResponse
    {
        $event = $request->query->getString('event');
        $competition = $request->query->getString('competition');
        if (($event === '' && $competition === '')
            || ($event !== '' && !ArticleLinks::isValidRef('event', $event))
            || ($competition !== '' && !ArticleLinks::isValidRef('competition', $competition))) {
            return $this->error('invalid_parameter', 400);
        }

        return $this->cached($this->forms->attachedTo($event === '' ? null : (int) $event, $competition ?: null, self::language($request)), self::LIST_MAX_AGE);
    }

    #[Route('/forms/options/competitions', name: 'public_form_competitions', methods: ['GET'], priority: 10)]
    #[OA\Get(path: '/forms/options/competitions', summary: 'Competitions a form can propose: published, not finished, active and next season', tags: [self::DOC_TAG])]
    public function competitions(): JsonResponse
    {
        return $this->cached($this->forms->competitionOptions(), self::LIST_MAX_AGE);
    }

    #[Route('/forms/{slug}', name: 'public_form', methods: ['GET'])]
    #[OA\Get(path: '/forms/{slug}', summary: 'Definition of a published form, its state and an anti-bot token (never cached)', tags: [self::DOC_TAG])]
    public function definition(string $slug, Request $request): JsonResponse
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return $this->error('invalid_parameter', 400);
        }
        $form = $this->forms->definition($slug, self::language($request));
        if ($form === null) {
            return $this->error('not_found', 404);
        }
        // The anti-bot token is issued at display time: never cached.
        $response = new UnicodeJsonResponse($form);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/forms/{slug}/submissions', name: 'public_form_submit', methods: ['POST'])]
    #[OA\Post(path: '/forms/{slug}/submissions', summary: 'Submit a registration: 201 confirmed / waiting, 422 field errors, 409 closed or full, 429 too many', tags: [self::DOC_TAG])]
    public function submit(string $slug, Request $request): JsonResponse
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return $this->error('invalid_parameter', 400);
        }
        $ip = $request->getClientIp();
        if ($ip !== null && !IpUtils::isPrivateIp($ip)) {
            $retryAfter = $this->throttle->hit(sprintf('form_submit.%s.%s', $slug, $ip), self::SUBMISSIONS_LIMIT, self::SUBMISSIONS_WINDOW);
            if ($retryAfter !== null) {
                return new UnicodeJsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $retryAfter]);
            }
        }
        $input = json_decode($request->getContent(), true);
        if (!is_array($input)) {
            return $this->error('invalid_parameter', 400);
        }

        try {
            return new UnicodeJsonResponse($this->forms->submit($slug, $input, self::language($request)), 201);
        } catch (ValidationErrors $errors) {
            return new UnicodeJsonResponse(['error' => 'validation_failed', 'errors' => $errors->all()], 422);
        } catch (FormRejected $rejected) {
            return $this->error($rejected->reason, match ($rejected->reason) {
                'not_found' => 404,
                'closed', 'full' => 409,
                default => 400,
            });
        }
    }

    #[Route('/forms/{slug}/entries', name: 'public_form_entries', methods: ['GET'])]
    #[OA\Get(path: '/forms/{slug}/entries', summary: 'Public list of a form: confirmed then waiting entries, published columns only (no personal data)', tags: [self::DOC_TAG])]
    public function entries(string $slug, Request $request): JsonResponse
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return $this->error('invalid_parameter', 400);
        }
        $entries = $this->forms->publicEntries($slug, self::language($request));

        return $entries === null ? $this->error('not_found', 404) : $this->cached($entries, self::LIST_MAX_AGE);
    }

    private static function language(Request $request): string
    {
        return CmsRules::language($request->query->getString('lang') ?: null);
    }

    private function cached(mixed $data, int $maxAge): JsonResponse
    {
        $response = new UnicodeJsonResponse($data);
        $response->setPublic();
        $response->setMaxAge($maxAge);

        return $response;
    }

    private function error(string $code, int $status): JsonResponse
    {
        return new UnicodeJsonResponse(['error' => $code], $status);
    }
}
