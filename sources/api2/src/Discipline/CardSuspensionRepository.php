<?php

namespace App\Discipline;

use Doctrine\DBAL\Connection;

/** Base de données des cumuls de cartons (FEATURE_CARD_SUSPENSIONS.md § 4, S5, S7) : cartons lus, suspensions tenues. */
final class CardSuspensionRepository
{
    /** Section d'une compétition sans groupe : « Divers », jamais couverte (S4). */
    private const NO_SECTION = 100;
    private const VALIDATED = 'O';
    public const ACTIVE = 'ACTIVE';
    public const LIFTED = 'LIFTED';
    public const CANCELLED = 'CANCELLED';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Cartons portés sur une licence dans les matchs validés de la saison, dans l'ordre chronologique (S5, S7).
     *
     * @return list<Card>
     */
    public function cards(string $season): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT d.Id, d.Competiteur, d.Id_evt_match, m.Id AS match_id, j.Id AS gameday_id, j.Code_competition,
                    c.Code_typeclt, j.Date_debut, j.Date_fin, j.Lieu, COALESCE(g.section, ?) AS section
             FROM kp_match_detail d
             JOIN kp_match m ON m.Id = d.Id_match
             JOIN kp_journee j ON j.Id = m.Id_journee
             JOIN kp_competition c ON c.Code = j.Code_competition AND c.Code_saison = j.Code_saison
             LEFT JOIN kp_groupe g ON g.Groupe = c.Code_ref
             WHERE j.Code_saison = ? AND m.Validation = ? AND d.Id_evt_match IN ('V', 'J', 'R', 'D')
               AND d.Competiteur > 0 AND d.Equipe_A_B IN ('A', 'B')
             ORDER BY m.Date_match, m.Heure_match, m.Numero_ordre, m.Id, d.date_insert, d.Id",
            [self::NO_SECTION, $season, self::VALIDATED],
        );

        return array_map(static fn (array $row): Card => new Card(
            (string) $row['Id'],
            (int) $row['Competiteur'],
            CardColor::from((string) $row['Id_evt_match']),
            (int) $row['match_id'],
            Card::gamedayKey((int) $row['gameday_id'], (string) $row['Code_competition'], $row['Code_typeclt'], $row['Date_debut'], $row['Date_fin'], $row['Lieu']),
            (int) $row['section'],
        ), $rows);
    }

    /** @return array<string, array<string, mixed>> suspensions en vigueur de la saison, par carton déclencheur */
    public function active(string $season): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM kp_card_suspension WHERE saison = ? AND actif = 1', [$season]);

        return array_column($rows, null, 'carton_id');
    }

    public function insert(string $season, int $catalogVersion, DetectedSuspension $suspension, string $now): int
    {
        $gameday = $this->connection->fetchAssociative(
            'SELECT j.Id, j.Code_competition FROM kp_match m JOIN kp_journee j ON j.Id = m.Id_journee WHERE m.Id = ?',
            [$suspension->card->matchId],
        ) ?: ['Id' => 0, 'Code_competition' => ''];
        $this->connection->insert('kp_card_suspension', [
            'saison' => $season,
            'section' => $suspension->card->section,
            'matric' => $suspension->card->matric,
            'carton_id' => $suspension->card->id,
            'match_id' => $suspension->card->matchId,
            'journee_id' => (int) $gameday['Id'],
            'code_competition' => (string) $gameday['Code_competition'],
            'regle' => $suspension->rule->code,
            'regles' => json_encode($suspension->ruleCodes(), JSON_THROW_ON_ERROR),
            'version_regles' => $catalogVersion,
            'decompte' => $suspension->tally,
            'matchs' => $suspension->matches,
            'matchs_journee' => $suspension->gamedayTotal,
            'jury_appel' => (int) $suspension->appealJury,
            'detectee_le' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param list<int> $recipients */
    public function markNotified(int $id, string $now, array $recipients, ?int $reportId): void
    {
        $this->connection->update('kp_card_suspension', [
            'notifiee_le' => $now,
            'destinataires' => json_encode($recipients, JSON_THROW_ON_ERROR),
            'rapport_id' => $reportId,
        ], ['id' => $id]);
    }

    public function cancel(int $id, string $now): void
    {
        $this->connection->update('kp_card_suspension', ['actif' => null, 'annulee_le' => $now], ['id' => $id]);
    }

    /**
     * Ce qu'une notification dit du match déclencheur (§ 5) : compétition, journée, match, joueur.
     *
     * @return array<string, mixed>
     */
    public function context(int $matchId, int $matric): array
    {
        $match = $this->connection->fetchAssociative(
            "SELECT m.Id, m.Numero_ordre, m.Date_match, m.Heure_match, m.Id_journee, j.Code_competition, j.Code_saison,
                    COALESCE(NULLIF(j.Phase, ''), j.Nom) AS gameday, j.Lieu, c.Libelle AS competition,
                    ea.Libelle AS team_a, eb.Libelle AS team_b
             FROM kp_match m JOIN kp_journee j ON j.Id = m.Id_journee
             LEFT JOIN kp_competition c ON c.Code = j.Code_competition AND c.Code_saison = j.Code_saison
             LEFT JOIN kp_competition_equipe ea ON ea.Id = m.Id_equipeA
             LEFT JOIN kp_competition_equipe eb ON eb.Id = m.Id_equipeB
             WHERE m.Id = ?",
            [$matchId],
        ) ?: [];
        $player = $this->connection->fetchAssociative('SELECT Nom, Prenom, Numero_club FROM kp_licence WHERE Matric = ?', [$matric]) ?: [];

        return $match + [
            'player' => trim(sprintf('%s %s', $player['Nom'] ?? '', $player['Prenom'] ?? '')) ?: (string) $matric,
            'club' => $player['Numero_club'] ?? null,
            'events' => array_map('intval', $this->connection->fetchFirstColumn(
                'SELECT Id_evenement FROM kp_evenement_journee WHERE Id_journee = ? ORDER BY Id_evenement',
                [(int) ($match['Id_journee'] ?? 0)],
            )),
        ];
    }

    /**
     * Suspensions de la saison, en vigueur et annulées, pour la consultation (S14).
     *
     * @return list<array<string, mixed>>
     */
    public function list(string $season): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT s.*, l.Nom, l.Prenom, l.Numero_club, m.Numero_ordre, m.Date_match, c.Libelle AS competition, ce.Libelle AS team
             FROM kp_card_suspension s
             LEFT JOIN kp_competition_equipe ce ON ce.Id = s.equipe_id
             LEFT JOIN kp_licence l ON l.Matric = s.matric
             LEFT JOIN kp_match m ON m.Id = s.match_id
             LEFT JOIN kp_competition c ON c.Code = s.code_competition AND c.Code_saison = s.saison
             WHERE s.saison = ? ORDER BY s.detectee_le DESC, s.id DESC",
            [$season],
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'state' => self::state($row),
            'matric' => (int) $row['matric'],
            'name' => trim(sprintf('%s %s', $row['Nom'] ?? '', $row['Prenom'] ?? '')),
            'club' => $row['Numero_club'],
            'section' => (int) $row['section'],
            'competition' => ['code' => $row['code_competition'], 'label' => $row['competition']],
            'gamedayId' => (int) $row['journee_id'],
            'match' => ['id' => (int) $row['match_id'], 'number' => $row['Numero_ordre'], 'date' => $row['Date_match']],
            'rule' => $row['regle'],
            'rules' => json_decode((string) $row['regles'], true, 512, JSON_THROW_ON_ERROR),
            'catalogVersion' => (int) $row['version_regles'],
            'tally' => (int) $row['decompte'],
            'matches' => (int) $row['matchs'],
            'gamedayTotal' => $row['matchs_journee'] === null ? null : (int) $row['matchs_journee'],
            'appealJury' => (bool) $row['jury_appel'],
            'detectedAt' => $row['detectee_le'],
            'notifiedAt' => $row['notifiee_le'],
            'cancelledAt' => $row['annulee_le'],
            'reportId' => $row['rapport_id'] === null ? null : (int) $row['rapport_id'],
            'team' => $row['equipe_id'] === null ? null : ['id' => (int) $row['equipe_id'], 'label' => $row['team']],
            'liftedAt' => $row['levee_le'],
            'liftedBy' => $row['levee_par'],
        ], $rows);
    }

    /** @param array<string, mixed> $row */
    public static function state(array $row): string
    {
        return match (true) {
            $row['actif'] === null => self::CANCELLED,
            $row['levee_le'] !== null => self::LIFTED,
            default => self::ACTIVE,
        };
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->connection->fetchAssociative('SELECT * FROM kp_card_suspension WHERE id = ?', [$id]) ?: null;
    }

    /**
     * Équipe avec laquelle le joueur a pris le carton (S16) : celle de sa feuille de match, sinon celle des deux
     * équipes du match dont la composition le contient.
     */
    public function teamOfCard(int $matchId, int $matric): ?int
    {
        $team = $this->connection->fetchOne(
            "SELECT IF(mj.Equipe = 'A', m.Id_equipeA, m.Id_equipeB) FROM kp_match_joueur mj JOIN kp_match m ON m.Id = mj.Id_match
             WHERE mj.Id_match = ? AND mj.Matric = ? AND mj.Equipe IN ('A', 'B')",
            [$matchId, $matric],
        ) ?: $this->connection->fetchOne(
            'SELECT cej.Id_equipe FROM kp_competition_equipe_joueur cej JOIN kp_match m ON cej.Id_equipe IN (m.Id_equipeA, m.Id_equipeB)
             WHERE m.Id = ? AND cej.Matric = ? ORDER BY cej.Id_equipe LIMIT 1',
            [$matchId, $matric],
        );

        return $team ? (int) $team : null;
    }

    /**
     * Pose le statut « Suspendu » sur la composition (S16) et retient celui d'avant ; un joueur déjà suspendu garde
     * le statut d'avant de sa première suspension en vigueur.
     */
    public function suspend(int $id, int $teamId, int $matric): void
    {
        $current = $this->connection->fetchOne('SELECT Capitaine FROM kp_competition_equipe_joueur WHERE Id_equipe = ? AND Matric = ?', [$teamId, $matric]);
        if ($current === false) {
            return;
        }
        $before = $current === SuspendedStatus::CODE
            ? ($this->connection->fetchOne(
                'SELECT statut_avant FROM kp_card_suspension WHERE equipe_id = ? AND matric = ? AND actif = 1 AND levee_le IS NULL AND id <> ? AND statut_avant IS NOT NULL ORDER BY id LIMIT 1',
                [$teamId, $matric, $id],
            ) ?: SuspendedStatus::DEFAULT_STATUS)
            : ((string) $current === '' ? SuspendedStatus::DEFAULT_STATUS : (string) $current);
        $this->connection->update('kp_competition_equipe_joueur', ['Capitaine' => SuspendedStatus::CODE], ['Id_equipe' => $teamId, 'Matric' => $matric]);
        $this->connection->update('kp_card_suspension', ['equipe_id' => $teamId, 'statut_avant' => $before], ['id' => $id]);
    }

    /**
     * Rend au joueur son statut d'avant (S19), si aucune autre suspension en vigueur et non levée ne le retient sur
     * la même équipe et s'il est toujours suspendu.
     *
     * @param array<string, mixed> $row
     */
    public function release(array $row): void
    {
        if ($row['equipe_id'] === null) {
            return;
        }
        $holding = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM kp_card_suspension WHERE equipe_id = ? AND matric = ? AND actif = 1 AND levee_le IS NULL AND id <> ?',
            [$row['equipe_id'], $row['matric'], $row['id']],
        );
        if ($holding > 0) {
            return;
        }
        $this->connection->executeStatement(
            'UPDATE kp_competition_equipe_joueur SET Capitaine = ? WHERE Id_equipe = ? AND Matric = ? AND Capitaine = ?',
            [$row['statut_avant'] ?? SuspendedStatus::DEFAULT_STATUS, $row['equipe_id'], $row['matric'], SuspendedStatus::CODE],
        );
    }

    public function lift(int $id, string $userCode, string $now): void
    {
        $this->connection->update('kp_card_suspension', ['levee_le' => $now, 'levee_par' => $userCode], ['id' => $id]);
    }

    /** Couleur actuelle d'un carton, ou null s'il a été supprimé de la timeline. */
    public function cardColor(string $cardId): ?CardColor
    {
        $code = $this->connection->fetchOne('SELECT Id_evt_match FROM kp_match_detail WHERE Id = ?', [$cardId]);

        return $code === false ? null : CardColor::tryFrom((string) $code);
    }
}
