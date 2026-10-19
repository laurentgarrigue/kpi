<?php

namespace App\Controller;

use App\Entity\User;
use App\Scrutineering\EquipmentRules;
use App\Scrutineering\TeamNation;
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
 * Scrutineering (equipment control) of the teams of an event, used by app4 (DOC/specs/PAGE_SCRUTINEERING.md § 5).
 *
 * JWT only (firewall api_staff), for profiles 1–2 and the profile 10 "Contrôle équipement" (ROLE_SCRUTINEER).
 * Every query is scoped to the event (getAllowedEvents) and to the allowed seasons and competitions; a team outside
 * that scope answers 404 (IDOR protection). The equipment stays editable when the competition is locked.
 */
#[Route('/staff', name: 'staff_')]
#[IsGranted('ROLE_SCRUTINEER')]
#[OA\Tag(name: '28. App4 - Scrutineering')]
class StaffController extends AbstractController
{
    use AdminLoggableTrait;

    private const COMMENT_MAX_LENGTH = 255;

    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/{eventId}/teams', name: 'teams', methods: ['GET'], requirements: ['eventId' => '\d+'])]
    #[OA\Get(summary: 'Teams of the event within the allowed competitions (team navigation)')]
    public function getTeams(int $eventId): JsonResponse
    {
        if ($denied = $this->denyEventAccess($eventId)) {
            return $denied;
        }

        [$scopeSql, $params, $types] = $this->perimeter($eventId);
        $rows = $this->connection->fetchAllAssociative(
            "SELECT DISTINCT ce.Id, ce.Libelle, ce.Code_club, ce.logo, ce.Code_compet, ce.Code_saison,
                    comp.Libelle AS comp_libelle, comp.Verrou, comp.GroupOrder, cl.Libelle AS club_libelle,
                    COALESCE(cl.Libelle, ce.Code_club) AS club_sort
             FROM kp_competition_equipe ce
             INNER JOIN kp_competition comp ON (ce.Code_compet = comp.Code AND ce.Code_saison = comp.Code_saison)
             INNER JOIN kp_journee j ON (ce.Code_compet = j.Code_competition AND ce.Code_saison = j.Code_saison)
             INNER JOIN kp_evenement_journee ej ON (j.Id = ej.Id_journee)
             LEFT JOIN kp_club cl ON ce.Code_club = cl.Code
             WHERE $scopeSql
             ORDER BY comp.GroupOrder, ce.Code_compet, club_sort, ce.Libelle",
            $params,
            $types,
        );

        return $this->jsonUnicode(array_map(fn (array $row): array => $this->formatTeam($row), $rows));
    }

    #[Route('/{eventId}/team/{teamId}/players', name: 'team_players', methods: ['GET'], requirements: ['eventId' => '\d+', 'teamId' => '\d+'])]
    #[OA\Get(summary: 'Team context and players with their scrutineering data (referees and inactive players excluded)')]
    public function getPlayers(int $eventId, int $teamId): JsonResponse
    {
        if ($denied = $this->denyEventAccess($eventId)) {
            return $denied;
        }
        $team = $this->findTeam($eventId, $teamId);
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
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route(
        '/{eventId}/team/{teamId}/player/{playerId}/{parameter}/{value}',
        name: 'update_player',
        methods: ['PUT'],
        requirements: ['eventId' => '\d+', 'teamId' => '\d+', 'playerId' => '\d+', 'parameter' => EquipmentRules::PARAMETER_REQUIREMENT, 'value' => '\d+'],
    )]
    #[OA\Put(summary: 'Set an equipment status (0–4) or the valid paddle count (0–6) of a player')]
    public function updateEquipment(int $eventId, int $teamId, int $playerId, string $parameter, int $value): JsonResponse
    {
        if ($denied = $this->denyEventAccess($eventId)) {
            return $denied;
        }
        if (!EquipmentRules::isValid($parameter, $value)) {
            return $this->json(['error' => 'Invalid value'], Response::HTTP_BAD_REQUEST);
        }
        $team = $this->findTeam($eventId, $teamId);
        if (!$team) {
            return $this->teamNotFound();
        }
        if (!$this->isInComposition($teamId, $playerId)) {
            return $this->json(['error' => 'Player not in composition'], Response::HTTP_NOT_FOUND);
        }

        // $parameter is restricted to the EquipmentRules columns by the route requirement
        $this->connection->executeStatement(
            "INSERT INTO kp_scrutineering (id_equipe, matric, $parameter) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE $parameter = VALUES($parameter)",
            [$teamId, $playerId, $value],
        );
        $this->logScrutineering('Scrut équipement', $eventId, $team, "Equipe $teamId - Joueur $playerId - " . EquipmentRules::journalLabel($parameter) . "=$value");

        return $this->json(['value' => $value]);
    }

    #[Route('/{eventId}/team/{teamId}/player/{playerId}/comment', name: 'update_player_comment', methods: ['PUT'], requirements: ['eventId' => '\d+', 'teamId' => '\d+', 'playerId' => '\d+'])]
    #[OA\Put(summary: 'Set the scrutineering comment of a player (255 characters max)')]
    public function updateComment(Request $request, int $eventId, int $teamId, int $playerId): JsonResponse
    {
        if ($denied = $this->denyEventAccess($eventId)) {
            return $denied;
        }
        $team = $this->findTeam($eventId, $teamId);
        if (!$team) {
            return $this->teamNotFound();
        }
        if (!$this->isInComposition($teamId, $playerId)) {
            return $this->json(['error' => 'Player not in composition'], Response::HTTP_NOT_FOUND);
        }

        $input = json_decode($request->getContent(), true);
        $comment = mb_substr(trim((string) ($input['comment'] ?? '')), 0, self::COMMENT_MAX_LENGTH);
        $this->connection->executeStatement(
            'INSERT INTO kp_scrutineering (id_equipe, matric, comment) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE comment = VALUES(comment)',
            [$teamId, $playerId, $comment],
        );
        $this->logScrutineering('Scrut commentaire', $eventId, $team, "Equipe $teamId - Joueur $playerId");

        return $this->jsonUnicode(['comment' => $comment]);
    }

    #[Route('/{eventId}/team/{teamId}/reset', name: 'reset_team', methods: ['DELETE'], requirements: ['eventId' => '\d+', 'teamId' => '\d+'])]
    #[OA\Delete(summary: 'Reset every scrutineering check of a team')]
    public function resetTeam(int $eventId, int $teamId): JsonResponse
    {
        if ($denied = $this->denyEventAccess($eventId)) {
            return $denied;
        }
        $team = $this->findTeam($eventId, $teamId);
        if (!$team) {
            return $this->teamNotFound();
        }

        $deleted = $this->connection->executeStatement('DELETE FROM kp_scrutineering WHERE id_equipe = ?', [$teamId]);
        $this->logScrutineering('Scrut réinitialisation', $eventId, $team, "Equipe $teamId - réinitialisation complète");

        return $this->json(['reset' => true, 'deleted' => $deleted]);
    }

    #[Route('/{eventId}/overview', name: 'overview', methods: ['GET'], requirements: ['eventId' => '\d+'])]
    #[OA\Get(summary: 'Scrutineering status (complete / partial / none) of the teams of the event, by club and category')]
    public function getOverview(int $eventId): JsonResponse
    {
        if ($denied = $this->denyEventAccess($eventId)) {
            return $denied;
        }

        // A player is "checked" when at least one equipment is > 0 (a row reset to 0 does not count) and "complete"
        // when kayak, vest and helmet are valid and at least one paddle is. Staff, referees and inactive are ignored.
        [$scopeSql, $params, $types] = $this->perimeter($eventId);
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
             INNER JOIN kp_journee j ON (ce.Code_compet = j.Code_competition AND ce.Code_saison = j.Code_saison)
             INNER JOIN kp_evenement_journee ej ON (j.Id = ej.Id_journee)
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
     * WHERE clause (and its parameters) restricting kp_competition_equipe ce to the event and to the allowed seasons
     * and competitions of the current user.
     *
     * @return array{0: string, 1: list<mixed>, 2: list<mixed>}
     */
    private function perimeter(int $eventId): array
    {
        $user = $this->currentUser();
        $sql = ['ej.Id_evenement = ?'];
        $params = [$eventId];
        $types = [ParameterType::INTEGER];
        foreach (['ce.Code_saison' => $user->getAllowedSeasons(), 'ce.Code_compet' => $user->getAllowedCompetitions()] as $column => $allowed) {
            if ($allowed !== null) {
                $sql[] = "$column IN (?)";
                $params[] = $allowed;
                $types[] = ArrayParameterType::STRING;
            }
        }

        return [implode(' AND ', $sql), $params, $types];
    }

    /**
     * Team context when the team belongs to the event and to the user's perimeter, null otherwise.
     *
     * @return array<string, mixed>|null
     */
    private function findTeam(int $eventId, int $teamId): ?array
    {
        [$scopeSql, $params, $types] = $this->perimeter($eventId);
        $row = $this->connection->fetchAssociative(
            "SELECT ce.Id, ce.Libelle, ce.Code_club, ce.logo, ce.Code_compet, ce.Code_saison,
                    comp.Libelle AS comp_libelle, comp.Verrou, cl.Libelle AS club_libelle
             FROM kp_competition_equipe ce
             INNER JOIN kp_competition comp ON (ce.Code_compet = comp.Code AND ce.Code_saison = comp.Code_saison)
             INNER JOIN kp_journee j ON (ce.Code_compet = j.Code_competition AND ce.Code_saison = j.Code_saison)
             INNER JOIN kp_evenement_journee ej ON (j.Id = ej.Id_journee)
             LEFT JOIN kp_club cl ON ce.Code_club = cl.Code
             WHERE ce.Id = ? AND $scopeSql
             LIMIT 1",
            [$teamId, ...$params],
            [ParameterType::INTEGER, ...$types],
        );

        return $row ? $this->formatTeam($row) : null;
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
            'nation' => TeamNation::of($club),
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
    private function logScrutineering(string $action, int $eventId, array $team, string $details): void
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
