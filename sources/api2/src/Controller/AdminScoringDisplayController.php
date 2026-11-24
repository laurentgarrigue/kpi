<?php

namespace App\Controller;

use App\Service\ScoringProgramService;
use App\Trait\AdminLoggableTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration of the video overlays: **display tokens** and **chaining settings**
 * (PAGE_INCRUSTATION.md §7/§11bis, plan lot 4).
 *
 * Without this, a token could only be created and revoked in raw SQL — and the plan
 * (§4.4) requires revocation "from app4". Revocation in particular must be one click:
 * a token lives in an OBS configuration that can end up anywhere.
 *
 * Behind the /admin firewall (JWT), restricted to ROLE_ADMIN like the rest of the event
 * tooling. Every mutation is journalized.
 */
#[Route('/admin/scoring/displays', name: 'admin_scoring_displays_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: '6. Scoring (admin incrustations)')]
class AdminScoringDisplayController extends AbstractController
{
    use AdminLoggableTrait;

    /** DBAL connection — also required by AdminLoggableTrait. */
    private Connection $connection;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly ScoringProgramService $program,
    ) {
        $this->connection = $entityManager->getConnection();
    }

    #[Route('/{event}', name: 'get', methods: ['GET'], requirements: ['event' => '\d+'])]
    #[OA\Get(
        path: '/admin/scoring/displays/{event}',
        summary: 'Display tokens and chaining settings of an event',
        tags: ['6. Scoring (admin incrustations)'],
        responses: [new OA\Response(response: 200, description: 'Tokens + settings (event and per-pitch)')]
    )]
    public function get(int $event): JsonResponse
    {
        $tokens = $this->connection->fetchAllAssociative(
            'SELECT id, token, pitch, label, expires_at, revoked_at, last_used_at, created_at
             FROM scoring_display_token
             WHERE id_event = ?
             ORDER BY revoked_at IS NOT NULL, created_at DESC',
            [$event]
        );

        $settings = $this->connection->fetchAllAssociative(
            'SELECT * FROM scoring_display_settings WHERE id_event = ? ORDER BY pitch IS NULL DESC, pitch',
            [$event]
        );

        return new JsonResponse([
            'event' => $event,
            'tokens' => $tokens,
            'settings' => $settings,
            // Server defaults, so the UI can show what an empty field will fall back to.
            'defaults' => ScoringProgramService::DEFAULTS,
        ]);
    }

    #[Route('/{event}/tokens', name: 'token_create', methods: ['POST'], requirements: ['event' => '\d+'])]
    #[OA\Post(
        path: '/admin/scoring/displays/{event}/tokens',
        summary: 'Mint a display token for an event (optionally restricted to one pitch)',
        tags: ['6. Scoring (admin incrustations)'],
        responses: [new OA\Response(response: 201, description: 'Token created')]
    )]
    public function createToken(int $event, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent());

        $pitch = isset($data->pitch) && $data->pitch !== '' ? (string) $data->pitch : null;
        $label = isset($data->label) ? substr((string) $data->label, 0, 100) : null;
        // Default lifetime: a week — long enough for an event, short enough that a
        // forgotten token does not stay valid forever.
        $days = isset($data->days) ? max(1, min(365, (int) $data->days)) : 7;

        $token = bin2hex(random_bytes(24));

        $this->connection->executeStatement(
            'INSERT INTO scoring_display_token (token, id_event, pitch, label, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))',
            [$token, $event, $pitch, $label, $days]
        );

        $this->logDisplayAction($event, 'Incrustation jeton créé', "pitch=" . ($pitch ?? '*') . " label={$label} jours={$days}");

        return new JsonResponse([
            'id' => (int) $this->connection->lastInsertId(),
            'token' => $token,
            'pitch' => $pitch,
            'label' => $label,
        ], Response::HTTP_CREATED);
    }

    #[Route('/tokens/{id}', name: 'token_revoke', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/admin/scoring/displays/tokens/{id}',
        summary: 'Revoke a display token immediately',
        tags: ['6. Scoring (admin incrustations)'],
        responses: [new OA\Response(response: 200, description: 'Token revoked')]
    )]
    public function revokeToken(int $id): JsonResponse
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id_event, pitch, label FROM scoring_display_token WHERE id = ?',
            [$id]
        );
        if ($row === false) {
            return new JsonResponse(['error' => 'Token not found'], Response::HTTP_NOT_FOUND);
        }

        // Revoked, never deleted: the row keeps the audit trail (who displayed what, until when).
        $this->connection->executeStatement(
            'UPDATE scoring_display_token SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL',
            [$id]
        );

        $this->logDisplayAction(
            (int) $row['id_event'],
            'Incrustation jeton révoqué',
            'pitch=' . ($row['pitch'] ?? '*') . ' label=' . ($row['label'] ?? '')
        );

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{event}/settings', name: 'settings_put', methods: ['PUT'], requirements: ['event' => '\d+'])]
    #[OA\Put(
        path: '/admin/scoring/displays/{event}/settings',
        summary: 'Set the chaining settings of an event (pitch = null) or of one pitch',
        tags: ['6. Scoring (admin incrustations)'],
        responses: [new OA\Response(response: 200, description: 'Settings saved')]
    )]
    public function putSettings(int $event, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $pitch = isset($data['pitch']) && $data['pitch'] !== '' ? (string) $data['pitch'] : null;

        // Whitelist payload key → column. An empty value stores NULL = "inherit"
        // (defaults, then the event row) — never a silent zero.
        $columns = [
            'halftimeScoreDelay' => 'halftime_score_delay',
            'finalScoreDelay' => 'final_score_delay',
            'finalScoreDuration' => 'final_score_duration',
            'nextGameDelay' => 'next_game_delay',
            'nextGameDuration' => 'next_game_duration',
            'background' => 'background',
            'styleId' => 'style_id',
        ];

        $values = ['id_event' => $event, 'pitch' => $pitch];
        foreach ($columns as $key => $column) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $raw = $data[$key];
            $values[$column] = ($raw === '' || $raw === null) ? null : $raw;
        }

        $fields = array_keys($values);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $updates = implode(',', array_map(
            static fn (string $f): string => "$f = VALUES($f)",
            array_diff($fields, ['id_event', 'pitch'])
        ));

        $sql = 'INSERT INTO scoring_display_settings (' . implode(',', $fields) . ") VALUES ($placeholders)";
        if ($updates !== '') {
            $sql .= " ON DUPLICATE KEY UPDATE $updates";
        }

        // MySQL treats NULL as distinct in a UNIQUE key: the event-level row (pitch NULL)
        // would be duplicated by ON DUPLICATE KEY. Handle it explicitly.
        if ($pitch === null) {
            $existing = $this->connection->fetchOne(
                'SELECT id FROM scoring_display_settings WHERE id_event = ? AND pitch IS NULL',
                [$event]
            );
            if ($existing !== false) {
                $set = [];
                $params = [];
                foreach ($values as $column => $value) {
                    if (in_array($column, ['id_event', 'pitch'], true)) {
                        continue;
                    }
                    $set[] = "$column = ?";
                    $params[] = $value;
                }
                if ($set !== []) {
                    $params[] = $existing;
                    $this->connection->executeStatement(
                        'UPDATE scoring_display_settings SET ' . implode(',', $set) . ' WHERE id = ?',
                        $params
                    );
                }
                $this->logDisplayAction($event, 'Incrustation réglages', 'niveau=événement');

                return new JsonResponse(['success' => true]);
            }
        }

        $this->connection->executeStatement($sql, array_values($values));
        $this->logDisplayAction($event, 'Incrustation réglages', 'niveau=' . ($pitch === null ? 'événement' : "terrain $pitch"));

        return new JsonResponse(['success' => true]);
    }

    /**
     * Journal an overlay administration action. The event is not a match, so the trait's
     * match-oriented helper does not apply: log against the event's first gameday, which
     * is what ties it to a season/competition.
     */
    private function logDisplayAction(int $idEvent, string $action, string $details): void
    {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT j.Id, j.Code_saison, j.Code_competition
                 FROM kp_evenement_journee ej
                 INNER JOIN kp_journee j ON j.Id = ej.Id_journee
                 WHERE ej.Id_evenement = ?
                 ORDER BY j.Id LIMIT 1',
                [$idEvent]
            );
            if ($row === false) {
                return;
            }
            $this->logActionForMatch(
                $action,
                $row['Code_saison'],
                $row['Code_competition'],
                (int) $row['Id'],
                0,
                "event={$idEvent} {$details}"
            );
        } catch (\Throwable) {
            // Journalling must never break an administration action.
        }
    }
}
