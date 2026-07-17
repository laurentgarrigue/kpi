<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Admin Mercure Controller
 *
 * Banc de test du hub Mercure natif de FrankenPHP (Super Admin uniquement).
 * Sert à valider la chaîne publish/subscribe avant la refonte du scoring live
 * (cf. DOC/developer/audits/FRANKENPHP_MIGRATION_ANALYSIS.md §7.7bis).
 *
 * Ne porte aucune logique métier : à supprimer ou remplacer quand le scoring
 * publiera ses propres updates.
 */
#[Route('/admin/mercure')]
#[IsGranted('ROLE_SUPER_ADMIN')]
#[OA\Tag(name: '25. App4 - Mercure (test)')]
class AdminMercureController extends AbstractController
{
    /**
     * Préfixe de topic imposé au banc de test.
     *
     * Empêche la page de test de publier sur les topics réels (scoring, etc.)
     * en cas de faute de frappe.
     */
    private const TOPIC_PREFIX = 'test/';

    public function __construct(
        private readonly HubInterface $hub,
        private readonly string $mercurePublicUrl
    ) {
    }

    /**
     * Configuration du hub, pour que la page de test sache où s'abonner.
     *
     * Ne renvoie jamais le secret JWT : uniquement l'URL publique et l'état.
     */
    #[Route('/config', name: 'admin_mercure_config', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'URL publique du hub et état de la configuration')]
    public function config(): JsonResponse
    {
        return $this->json([
            'publicUrl' => $this->mercurePublicUrl,
            'topicPrefix' => self::TOPIC_PREFIX,
            'configured' => $this->mercurePublicUrl !== '',
        ]);
    }

    /**
     * Publie un update de test sur le hub.
     */
    #[Route('/publish', name: 'admin_mercure_publish', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'topic (préfixé par "test/"), data (objet libre), private (bool)',
        required: false
    )]
    #[OA\Response(response: 200, description: 'Update publié, renvoie l\'id Mercure')]
    #[OA\Response(response: 400, description: 'Topic hors du préfixe de test')]
    public function publish(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $topic = $payload['topic'] ?? self::TOPIC_PREFIX . 'ping';
        if (!str_starts_with($topic, self::TOPIC_PREFIX)) {
            return $this->json([
                'error' => sprintf('Le topic doit commencer par "%s".', self::TOPIC_PREFIX),
            ], 400);
        }

        // Un update privé n'est délivré qu'aux abonnés dont le JWT porte le topic
        // dans sa claim "subscribe" — sert à vérifier que l'anonyme ne reçoit rien.
        $private = (bool) ($payload['private'] ?? false);

        $data = json_encode([
            'message' => $payload['data']['message'] ?? 'ping',
            'publishedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'private' => $private,
        ], JSON_THROW_ON_ERROR);

        $id = $this->hub->publish(new Update($topic, $data, $private));

        return $this->json([
            'success' => true,
            'id' => $id,
            'topic' => $topic,
            'private' => $private,
        ]);
    }
}
