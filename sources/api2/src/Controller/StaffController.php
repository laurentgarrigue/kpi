<?php

namespace App\Controller;

use App\Entity\User;
use App\Scrutineering\EquipmentRules;
use App\Service\TeamLastUpdateService;
use App\Trait\AdminLoggableTrait;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Scrutineering (equipment control) of the teams, used by app4 (DOC/specs/PAGE_SCRUTINEERING.md § 5).
 *
 * JWT only (firewall api_staff), for profiles 1–2 and the profile 10 "Contrôle équipement" (ROLE_SCRUTINEER).
 * The control belongs to a team, whether its competition is part of an event or not. A team is reachable within the
 * allowed seasons and competitions and, for an account limited to events, within those events; outside that
 * perimeter it answers 404 (IDOR protection). The team list and the overview take a context: an event, or a season
 * and a competition. The equipment stays editable when the competition is locked.
 */
#[Route('/staff', name: 'staff_')]
#[IsGranted('ROLE_SCRUTINEER')]
#[OA\Tag(name: '28. App4 - Scrutineering')]
class StaffController extends AbstractController
{
    use AdminLoggableTrait;

    private const COMMENT_MAX_LENGTH = 255;

    /** The competition of the team ce has a gameday in one of the events bound to the placeholder. */
    private const IN_EVENTS_SQL = 'EXISTS (SELECT 1 FROM kp_journee j INNER JOIN kp_evenement_journee ej ON j.Id = ej.Id_journee
        WHERE j.Code_competition = ce.Code_compet AND j.Code_saison = ce.Code_saison AND ej.Id_evenement IN (?))';

    public function __construct(
        private readonly Connection $connection,
        private readonly TeamLastUpdateService $teamLastUpdate,
    ) {
    }

    #[Route('/teams', name: 'teams', methods: ['GET'])]
    #[OA\Get(summary: 'Teams of the context (?event= or ?season=&competition=) within the perimeter (team navigation)')]
    public function getTeams(Request $request): JsonResponse
    {
        $scope = $this->contextScope($request);
        if ($scope instanceof JsonResponse) {
            return $scope;
        }

        [$scopeSql, $params, $types] = $scope;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT ce.Id, ce.Libelle, ce.Code_club, ce.logo, ce.Code_compet, ce.Code_saison,
                    comp.Libelle AS comp_libelle, comp.Verrou, cl.Libelle AS club_libelle
             FROM kp_competition_equipe ce
             INNER JOIN kp_competition comp ON (ce.Code_compet = comp.Code AND ce.Code_saison = comp.Code_saison)
             LEFT JOIN kp_club cl ON ce.Code_club = cl.Code
             WHERE $scopeSql
             ORDER BY comp.GroupOrder, ce.Code_compet, COALESCE(cl.Libelle, ce.Code_club), ce.Libelle",
            $params,
            $types,
        );

        return $this->jsonUnicode(array_map(fn (array $row): array => $this->formatTeam($row), $rows));
    }

    #[Route('/team/{teamId}/players', name: 'team_players', methods: ['GET'], requirements: ['teamId' => '\d+'])]
    #[OA\Get(summary: 'Team context and players with their scrutineering data (referees and inactive players excluded)')]
    public function getPlayers(int $teamId): JsonResponse
    {
        $team = $this->findTeam($teamId);
        if (!$team) {
            return $this->teamNotFound();
        }

        $players = $this->connection->fetchAllAssociative(
            "SELECT cej.Matric player_id, cej.Nom last_name, cej.Prenom first_name, cej.Numero num, cej.Capitaine cap,
                    sc.kayak_status, sc.vest_status, sc.helmet_status, sc.paddle_count, sc.comment
             FROM kp_competition_equipe_joueur cej
             LEFT JOIN kp_scrutineering sc ON (cej.Id_equipe = sc.id_equipe AND cej.Matric = sc.matric)
             WHERE cej.Id_equipe = ? AND cej.Capitaine NOT IN ('A', 'X')
             ORDER BY FIELD(IF(cej.Capitaine IN ('C', ''), '-', cej.Capitaine), '-', 'E'), cej.Numero, cej.Nom, cej.Prenom",
            [$teamId],
        );

        $response = $this->jsonUnicode([
            'team' => $team,
            'players' => array_map(static fn (array $row): array => [
                'player_id' => (int) $row['player_id'],
                'last_name' => $row['last_name'],
                'first_name' => $row['first_name'],
                'num' => $row['num'] === null ? null : (int) $row['num'],
                'cap' => $row['cap'] ?: '-',
                'kayak_status' => (int) $row['kayak_status'],
                'vest_status' => (int) $row['vest_status'],
                'helmet_status' => (int) $row['helmet_status'],
                'paddle_count' => (int) $row['paddle_count'],
                // Comments written by app2 were HTML-escaped before being stored
                'comment' => html_entity_decode((string) $row['comment'], ENT_QUOTES, 'UTF-8'),
            ], $players),
            'lastUpdate' => $this->teamLastUpdate->scrutineering($teamId),
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route(
        '/team/{teamId}/player/{playerId}/{parameter}/{value}',
        name: 'update_player',
        methods: ['PUT'],
        requirements: ['teamId' => '\d+', 'playerId' => '\d+', 'parameter' => EquipmentRules::PARAMETER_REQUIREMENT, 'value' => '\d+'],
    )]
    #[OA\Put(summary: 'Set an equipment status (0–4) or the valid paddle count (0–6) of a player (optional ?event= for the journal)')]
    public function updateEquipment(Request $request, int $teamId, int $playerId, string $parameter, int $value): JsonResponse
    {
        if (!EquipmentRules::isValid($parameter, $value)) {
            return $this->json(['error' => 'Invalid value'], Response::HTTP_BAD_REQUEST);
        }
        $target = $this->writeTarget($request, $teamId, $playerId);
        if ($target instanceof JsonResponse) {
            return $target;
        }
        [$team, $eventId] = $target;

        // $parameter is restricted to the EquipmentRules columns by the route requirement
        $this->connection->executeStatement(
            "INSERT INTO kp_scrutineering (id_equipe, matric, $parameter) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE $parameter = VALUES($parameter)",
            [$teamId, $playerId, $value],
        );
        $this->logScrutineering('Scrut équipement', $eventId, $team, "Equipe $teamId - Joueur $playerId - " . EquipmentRules::journalLabel($parameter) . "=$value");

        return $this->jsonUnicode(['value' => $value, 'lastUpdate' => $this->teamLastUpdate->scrutineering($teamId)]);
    }

    #[Route('/team/{teamId}/player/{playerId}/comment', name: 'update_player_comment', methods: ['PUT'], requirements: ['teamId' => '\d+', 'playerId' => '\d+'])]
    #[OA\Put(summary: 'Set the scrutineering comment of a player, 255 characters max (optional ?event= for the journal)')]
    public function updateComment(Request $request, int $teamId, int $playerId): JsonResponse
    {
        $target = $this->writeTarget($request, $teamId, $playerId);
        if ($target instanceof JsonResponse) {
            return $target;
        }
        [$team, $eventId] = $target;

        $input = json_decode($request->getContent(), true);
        $comment = mb_substr(trim((string) ($input['comment'] ?? '')), 0, self::COMMENT_MAX_LENGTH);
        $this->connection->executeStatement(
            'INSERT INTO kp_scrutineering (id_equipe, matric, comment) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE comment = VALUES(comment)',
            [$teamId, $playerId, $comment],
        );
        $this->logScrutineering('Scrut commentaire', $eventId, $team, "Equipe $teamId - Joueur $playerId");

        return $this->jsonUnicode(['comment' => $comment, 'lastUpdate' => $this->teamLastUpdate->scrutineering($teamId)]);
    }

    #[Route('/team/{teamId}/reset', name: 'reset_team', methods: ['DELETE'], requirements: ['teamId' => '\d+'])]
    #[OA\Delete(summary: 'Reset every scrutineering check of a team (optional ?event= for the journal)')]
    public function resetTeam(Request $request, int $teamId): JsonResponse
    {
        $target = $this->writeTarget($request, $teamId);
        if ($target instanceof JsonResponse) {
            return $target;
        }
        [$team, $eventId] = $target;

        $deleted = $this->connection->executeStatement('DELETE FROM kp_scrutineering WHERE id_equipe = ?', [$teamId]);
        $this->logScrutineering('Scrut réinitialisation', $eventId, $team, "Equipe $teamId - réinitialisation complète");

        return $this->json(['reset' => true, 'deleted' => $deleted]);
    }

    #[Route('/overview', name: 'overview', methods: ['GET'])]
    #[OA\Get(summary: 'Scrutineering status (complete / partial / none) of the teams of the context, by club and category')]
    public function getOverview(Request $request): JsonResponse
    {
        $scope = $this->contextScope($request);
        if ($scope instanceof JsonResponse) {
            return $scope;
        }

        // A player is "checked" when at least one equipment is > 0 (a row reset to 0 does not count) and "complete"
        // when kayak, vest and helmet are valid and at least one paddle is. Staff, referees and inactive are ignored.
        [$scopeSql, $params, $types] = $scope;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT ce.Id AS team_id, ce.Libelle AS label, ce.Code_club AS club_code,
                    COALESCE(cl.Libelle, ce.Code_club, '') AS club_label, ce.logo AS logo,
                    COALESCE(comp.Soustitre2, comp.Libelle, '') AS category,
                    COUNT(DISTINCT cej.Matric) AS total_players,
                    COUNT(DISTINCT CASE WHEN COALESCE(sc.kayak_status, 0) > 0 OR COALESCE(sc.vest_status, 0) > 0
                        OR COALESCE(sc.helmet_status, 0) > 0 OR COALESCE(sc.paddle_count, 0) > 0 THEN sc.matric END) AS checked_players,
                    COUNT(DISTINCT CASE WHEN sc.kayak_status = 1 AND sc.vest_status = 1 AND sc.helmet_status = 1
                        AND sc.paddle_count > 0 THEN sc.matric END) AS complete_players
             FROM kp_competition_equipe ce
             INNER JOIN kp_competition comp ON (ce.Code_compet = comp.Code AND ce.Code_saison = comp.Code_saison)
             LEFT JOIN kp_club cl ON ce.Code_club = cl.Code
             LEFT JOIN kp_competition_equipe_joueur cej ON (cej.Id_equipe = ce.Id AND cej.Capitaine NOT IN ('E', 'A', 'X'))
             LEFT JOIN kp_scrutineering sc ON (sc.id_equipe = ce.Id AND sc.matric = cej.Matric)
             WHERE $scopeSql
             GROUP BY ce.Id, ce.Libelle, ce.Code_club, cl.Libelle, ce.logo, category
             ORDER BY category, club_label, label",
            $params,
            $types,
        );

        $teams = [];
        $categories = [];
        $clubs = [];
        foreach ($rows as $row) {
            $category = $row['category'] ?: '—';
            $clubCode = $row['club_code'] ?: '—';
            $categories[$category] = true;
            $clubs[$clubCode] ??= ['code' => $clubCode, 'label' => $row['club_label'] ?: $clubCode];
            $teams[] = [
                'team_id' => (int) $row['team_id'],
                'label' => $row['label'],
                'club' => $clubCode,
                'logo' => $row['logo'],
                'category' => $category,
                'status' => self::overviewStatus((int) $row['total_players'], (int) $row['checked_players'], (int) $row['complete_players']),
            ];
        }
        $clubs = array_values($clubs);
        usort($clubs, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $this->jsonUnicode(['categories' => array_keys($categories), 'clubs' => $clubs, 'teams' => $teams]);
    }

    private static function overviewStatus(int $total, int $checked, int $complete): string
    {
        if ($checked === 0) {
            return 'none';
        }

        return $total > 0 && $complete === $total ? 'complete' : 'partial';
    }

    private function currentUser(): User
    {
        /** @var User $user ROLE_SCRUTINEER guarantees an authenticated User */
        $user = $this->getUser();

        return $user;
    }

    private function denyEventAccess(int $eventId): ?JsonResponse
    {
        $allowedEvents = $this->currentUser()->getAllowedEvents();
        if ($allowedEvents !== null && !in_array($eventId, $allowedEvents, true)) {
            return $this->json(['error' => 'Forbidden - Access denied to this event'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /**
     * WHERE clause (and its parameters) restricting kp_competition_equipe ce to the perimeter of the current user:
     * allowed seasons and competitions and, for an account limited to events, the competitions of those events.
     *
     * @return array{0: string, 1: list<mixed>, 2: list<mixed>}
     */
    private function perimeter(): array
    {
        $user = $this->currentUser();
        $sql = ['1 = 1'];
        $params = [];
        $types = [];
        foreach (['ce.Code_saison' => $user->getAllowedSeasons(), 'ce.Code_compet' => $user->getAllowedCompetitions()] as $column => $allowed) {
            if ($allowed !== null) {
                $sql[] = "$column IN (?)";
                $params[] = $allowed;
                $types[] = ArrayParameterType::STRING;
            }
        }
        $allowedEvents = $user->getAllowedEvents();
        if ($allowedEvents !== null) {
            $sql[] = self::IN_EVENTS_SQL;
            $params[] = $allowedEvents;
            $types[] = ArrayParameterType::INTEGER;
        }

        return [implode(' AND ', $sql), $params, $types];
    }

    /**
     * Perimeter narrowed to the context of the request: ?event={id}, or ?season={s}&competition={c}.
     * 400 without a context or with both, 403 on an event, season or competition outside the perimeter.
     *
     * @return array{0: string, 1: list<mixed>, 2: list<mixed>}|JsonResponse
     */
    private function contextScope(Request $request): array|JsonResponse
    {
        $event = $request->query->get('event');
        $season = (string) $request->query->get('season', '');
        $competition = (string) $request->query->get('competition', '');
        $byEvent = $event !== null;
        $byCompetition = $season !== '' && $competition !== '';
        if ($byEvent === $byCompetition || ($byEvent && !ctype_digit((string) $event))) {
            return $this->json(['error' => 'A context is required: event, or season and competition'], Response::HTTP_BAD_REQUEST);
        }

        [$sql, $params, $types] = $this->perimeter();
        if ($byEvent) {
            if ($denied = $this->denyEventAccess((int) $event)) {
                return $denied;
            }

            return ["$sql AND " . self::IN_EVENTS_SQL, [...$params, [(int) $event]], [...$types, ArrayParameterType::INTEGER]];
        }

        $user = $this->currentUser();
        foreach ([[$season, $user->getAllowedSeasons()], [$competition, $user->getAllowedCompetitions()]] as [$value, $allowed]) {
            if ($allowed !== null && !in_array($value, $allowed, true)) {
                return $this->json(['error' => 'Forbidden - Access denied to this competition'], Response::HTTP_FORBIDDEN);
            }
        }

        return [
            "$sql AND ce.Code_saison = ? AND ce.Code_compet = ?",
            [...$params, $season, $competition],
            [...$types, ParameterType::STRING, ParameterType::STRING],
        ];
    }

    /**
     * Team context when the team belongs to the perimeter of the user, null otherwise.
     *
     * @return array<string, mixed>|null
     */
    private function findTeam(int $teamId): ?array
    {
        [$scopeSql, $params, $types] = $this->perimeter();
        $row = $this->connection->fetchAssociative(
            "SELECT ce.Id, ce.Libelle, ce.Code_club, ce.logo, ce.Code_compet, ce.Code_saison,
                    comp.Libelle AS comp_libelle, comp.Verrou, cl.Libelle AS club_libelle
             FROM kp_competition_equipe ce
             INNER JOIN kp_competition comp ON (ce.Code_compet = comp.Code AND ce.Code_saison = comp.Code_saison)
             LEFT JOIN kp_club cl ON ce.Code_club = cl.Code
             WHERE ce.Id = ? AND $scopeSql",
            [$teamId, ...$params],
            [ParameterType::INTEGER, ...$types],
        );

        return $row ? $this->formatTeam($row) : null;
    }

    /**
     * Team (and optional player) of a write, with the event of the journal (?event=, null outside an event).
     * 404 outside the perimeter or the composition, 403 on an event outside the perimeter, 400 on an event that does
     * not contain the team.
     *
     * @return array{0: array<string, mixed>, 1: int|null}|JsonResponse
     */
    private function writeTarget(Request $request, int $teamId, ?int $playerId = null): array|JsonResponse
    {
        $event = $request->query->get('event');
        $eventId = $event !== null && ctype_digit((string) $event) ? (int) $event : null;
        if ($event !== null && $eventId === null) {
            return $this->json(['error' => 'Invalid event'], Response::HTTP_BAD_REQUEST);
        }
        if ($eventId !== null && ($denied = $this->denyEventAccess($eventId))) {
            return $denied;
        }
        $team = $this->findTeam($teamId);
        if (!$team) {
            return $this->teamNotFound();
        }
        if ($playerId !== null && !$this->isInComposition($teamId, $playerId)) {
            return $this->json(['error' => 'Player not in composition'], Response::HTTP_NOT_FOUND);
        }
        if ($eventId !== null && !$this->isTeamInEvent($teamId, $eventId)) {
            return $this->json(['error' => 'The team is not part of this event'], Response::HTTP_BAD_REQUEST);
        }

        return [$team, $eventId];
    }

    private function isTeamInEvent(int $teamId, int $eventId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM kp_competition_equipe ce WHERE ce.Id = ? AND ' . self::IN_EVENTS_SQL,
            [$teamId, [$eventId]],
            [ParameterType::INTEGER, ArrayParameterType::INTEGER],
        ) !== false;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function formatTeam(array $row): array
    {
        $club = (string) $row['Code_club'];

        return [
            'team_id' => (int) $row['Id'],
            'label' => $row['Libelle'],
            'club' => $club,
            'club_label' => $row['club_libelle'] ?? $club,
            'logo' => $row['logo'] ?: null,
            'competition_code' => $row['Code_compet'],
            'competition_label' => $row['comp_libelle'] ?? '',
            'season' => (string) $row['Code_saison'],
            'locked' => $row['Verrou'] === 'O',
        ];
    }

    private function isInComposition(int $teamId, int $playerId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM kp_competition_equipe_joueur WHERE Id_equipe = ? AND Matric = ?',
            [$teamId, $playerId],
        ) !== false;
    }

    /** @param array<string, mixed> $team */
    private function logScrutineering(string $action, ?int $eventId, array $team, string $details): void
    {
        $this->logActionForEventCompetition($action, $eventId, $team['season'], $team['competition_code'], $details);
    }

    private function teamNotFound(): JsonResponse
    {
        return $this->json(['error' => 'Team not found'], Response::HTTP_NOT_FOUND);
    }

    private function jsonUnicode(mixed $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);

        return $response;
    }
}
