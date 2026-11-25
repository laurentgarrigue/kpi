<?php

namespace App\Discipline;

use App\Designation\DesignationRepository;
use App\Designation\EffectiveDesignations;
use App\Designation\GamedayScope;
use App\Report\ReportPeople;
use App\Report\ReportPerimeters;
use App\Report\ReportRepository;
use App\Report\ReportScope;
use App\Report\ReportService;
use App\Report\ReportTypeCatalog;
use App\Report\ReportTypeDefinition;
use App\Service\NotificationService;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Synchronisation des suspensions d'une saison (FEATURE_CARD_SUSPENSIONS.md S9 à S13) : détecte les cumuls dans la
 * timeline, enregistre et notifie chaque nouvelle suspension une seule fois, annule celles qu'une correction de la
 * feuille fait disparaître, ouvre un brouillon de jury d'appel quand le catalogue le prévoit.
 */
final class CardSuspensionService
{
    private const RECIPIENT_RC = 'ROLE:RC';
    private const DELEGATE_ROLE = 'DELEGUE_CNA';

    public function __construct(
        private readonly CardSuspensionRepository $suspensions,
        private readonly DesignationRepository $designations,
        private readonly ReportPerimeters $perimeters,
        private readonly ReportPeople $people,
        private readonly ReportRepository $reports,
        private readonly ReportService $reportService,
        private readonly NotificationService $notifications,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly string $app4Url = '',
    ) {
    }

    /** @return array{detected: int, notified: int, cancelled: int} */
    public function sync(string $season, bool $silent = false): array
    {
        $result = ['detected' => 0, 'notified' => 0, 'cancelled' => 0];
        $catalog = SuspensionCatalog::forSeason($season);
        if ($catalog === null) {
            return $result;
        }
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $active = $this->suspensions->active($season);
        foreach (SuspensionDetector::detect($catalog, $this->suspensions->cards($season)) as $suspension) {
            $existing = $active[$suspension->card->id] ?? null;
            if ($existing !== null && self::unchanged($existing, $suspension)) {
                unset($active[$suspension->card->id]);
                continue;
            }
            $id = $this->suspensions->insert($season, $catalog->version, $suspension, $now);
            ++$result['detected'];
            if (!$silent) {
                $this->notify($id, $season, $suspension, $now);
                ++$result['notified'];
            }
        }
        foreach ($active as $stale) {
            $this->cancel($stale, $catalog, $now);
            ++$result['cancelled'];
        }

        return $result;
    }

    /** @param array<string, mixed> $row */
    private static function unchanged(array $row, DetectedSuspension $suspension): bool
    {
        return $row['regle'] === $suspension->rule->code && (int) $row['matchs'] === $suspension->matches && (bool) $row['jury_appel'] === $suspension->appealJury;
    }

    private function notify(int $id, string $season, DetectedSuspension $suspension, string $now): void
    {
        $card = $suspension->card;
        $context = $this->suspensions->context($card->matchId, $card->matric);
        $reportId = $suspension->appealJury ? $this->openAppealDraft($season, $suspension, $context) : null;
        $message = new SuspensionMessage(
            $card->matric,
            $context,
            $card->color,
            $suspension->rule,
            $card->section,
            $suspension->tally,
            $suspension->matches,
            $suspension->appealJury,
            array_values(array_filter($suspension->rules, static fn (SuspensionRule $rule): bool => $rule !== $suspension->rule)),
            $reportId === null ? null : rtrim($this->app4Url, '/') . '/reports/' . $reportId,
        );
        $recipients = $this->recipients($card->matchId, $season);
        $this->send($recipients, $message->subject(), $message->body());
        $this->suspensions->markNotified($id, $now, $recipients, $reportId);
    }

    /** @param array<string, mixed> $row */
    private function cancel(array $row, SuspensionCatalog $catalog, string $now): void
    {
        $this->suspensions->cancel((int) $row['id'], $now);
        if ($row['notifiee_le'] === null) {
            return;
        }
        $rule = self::rule($catalog, (string) $row['regle']);
        $card = $this->suspensions->cardColor((string) $row['carton_id']);
        if ($rule === null) {
            return;
        }
        $message = new SuspensionMessage(
            (int) $row['matric'],
            $this->suspensions->context((int) $row['match_id'], (int) $row['matric']),
            $card ?? $rule->cards[0],
            $rule,
            (int) $row['section'],
            (int) $row['decompte'],
            (int) $row['matchs'],
            (bool) $row['jury_appel'],
        );
        $recipients = array_map('intval', json_decode((string) ($row['destinataires'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR) ?? []);
        $this->send($recipients, $message->cancellationSubject(), $message->cancellationBody());
    }

    private static function rule(SuspensionCatalog $catalog, string $code): ?SuspensionRule
    {
        foreach ($catalog->rules as $rule) {
            if ($rule->code === $code) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Bureau CNA de la saison et RC de la compétition du match (S9), résolus maintenant depuis les Désignations.
     *
     * @return list<int>
     */
    private function recipients(int $matchId, string $season): array
    {
        $perimeter = $this->perimeters->resolve(ReportScope::Match, (string) $matchId, $season);

        return $perimeter === null ? [] : $this->people->recipients([ReportTypeDefinition::RECIPIENT_BUREAU, self::RECIPIENT_RC], $perimeter, null);
    }

    /**
     * Un e-mail par destinataire joignable ; à défaut, l'adresse d'administration (S9).
     *
     * @param list<int> $recipients
     */
    private function send(array $recipients, string $subject, string $body): void
    {
        $emails = $this->reports->emails($recipients);
        if ($emails === []) {
            $this->logger->warning('Card suspension: no designated recipient with an email, sent to the admin address', ['subject' => $subject]);
            $this->notifications->sendAdminNotification($subject, $body);

            return;
        }
        foreach ($emails as $email) {
            $this->notifications->sendAdminNotification($subject, $body, $email);
        }
    }

    /**
     * Brouillon `JURY_APPEL` sur l'événement de la journée, ou sur la journée hors événement ; auteur : le délégué CNA
     * effectif de la journée, président du jury (S12).
     *
     * @param array<string, mixed> $context
     */
    private function openAppealDraft(string $season, DetectedSuspension $suspension, array $context): ?int
    {
        $gamedayId = (int) ($context['Id_journee'] ?? 0);
        $events = $context['events'] ?? [];
        [$scope, $scopeId] = $events === [] ? [ReportScope::Journee, (string) $gamedayId] : [ReportScope::Evenement, (string) $events[0]];
        $delegate = EffectiveDesignations::of(
            new GamedayScope($gamedayId, $season, (string) ($context['Code_competition'] ?? ''), $events),
            $this->designations->ofSeason($season),
        )->holders(self::DELEGATE_ROLE)[0] ?? null;
        $teams = sprintf('%s - %s', $context['team_a'] ?? '?', $context['team_b'] ?? '?');
        try {
            return $this->reportService->openSystemDraft(ReportTypeCatalog::JURY_APPEL, $scope, $scopeId, $season, $delegate?->matric, [
                'reason' => sprintf('Suspension de %d matchs + jury d\'appel : %s, licence %d (%s).', $suspension->matches, $context['player'] ?? '', $suspension->card->matric, $suspension->rule->labelFr),
                'matches' => [['number' => (string) ($context['Numero_ordre'] ?? ''), 'teams' => $teams]],
                'facts' => sprintf('Carton %s pris par %s (licence %d) au match n° %s du %s, %s. Règles atteintes : %s.',
                    $suspension->card->color->labelFr(), $context['player'] ?? '', $suspension->card->matric, $context['Numero_ordre'] ?? '', $context['Date_match'] ?? '', $teams,
                    implode(' ; ', array_map(static fn (SuspensionRule $rule): string => $rule->labelFr, $suspension->rules))),
            ])->id;
        } catch (\Throwable $e) {
            $this->logger->error('Card suspension: appeal jury draft not opened', ['card' => $suspension->card->id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
