<?php

namespace App\Discipline;

use Doctrine\DBAL\Connection;

/** Base de données des cumuls de cartons (FEATURE_CARD_SUSPENSIONS.md § 4, S5, S7) : cartons lus, suspensions tenues. */
final class CardSuspensionRepository
{
    /** Section d'une compétition sans groupe : « Divers », jamais couverte (S4). */
    private const NO_SECTION = 100;
    private const VALIDATED = 'O';

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
            "SELECT s.*, l.Nom, l.Prenom, l.Numero_club, m.Numero_ordre, m.Date_match, c.Libelle AS competition
             FROM kp_card_suspension s
             LEFT JOIN kp_licence l ON l.Matric = s.matric
             LEFT JOIN kp_match m ON m.Id = s.match_id
             LEFT JOIN kp_competition c ON c.Code = s.code_competition AND c.Code_saison = s.saison
             WHERE s.saison = ? ORDER BY s.detectee_le DESC, s.id DESC",
            [$season],
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'state' => $row['actif'] === null ? 'CANCELLED' : 'ACTIVE',
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
            'appealJury' => (bool) $row['jury_appel'],
            'detectedAt' => $row['detectee_le'],
            'notifiedAt' => $row['notifiee_le'],
            'cancelledAt' => $row['annulee_le'],
            'reportId' => $row['rapport_id'] === null ? null : (int) $row['rapport_id'],
        ], $rows);
    }

    /** Couleur actuelle d'un carton, ou null s'il a été supprimé de la timeline. */
    public function cardColor(string $cardId): ?CardColor
    {
        $code = $this->connection->fetchOne('SELECT Id_evt_match FROM kp_match_detail WHERE Id = ?', [$cardId]);

        return $code === false ? null : CardColor::tryFrom((string) $code);
    }
}
