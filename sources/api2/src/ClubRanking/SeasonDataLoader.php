<?php

namespace App\ClubRanking;

use App\ClubRanking\Data\Club;
use App\ClubRanking\Data\Competition;
use App\ClubRanking\Data\CompetitionManager;
use App\ClubRanking\Data\Gameday;
use App\ClubRanking\Data\Licensee;
use App\ClubRanking\Data\ManualEntry;
use App\ClubRanking\Data\Referee;
use App\ClubRanking\Data\SeasonData;
use App\ClubRanking\Data\TeamEntry;
use App\ClubRanking\Data\TeamPlayer;
use App\ClubRanking\Model\Scale;
use Doctrine\DBAL\Connection;

/**
 * Reads, for one season, everything the calculation needs from the legacy tables (§ 6), as it is today: the club
 * of a licensee and his referee qualification are the current ones (§ 5.1, the reference date).
 */
final class SeasonDataLoader
{
    private const VALIDATED = 'O';
    private const PUBLISHED = 'O';
    private const LOCKED = 'O';
    private const COACH = 'E';
    /** Roster statuses that are not players: coach, referee of the pool, inactive. */
    private const NOT_PLAYERS = ['E', 'A', 'X'];
    private const FOREIGN_REGION = '98';
    private const NO_DATE = '0000-00-00';

    public function __construct(
        private readonly Connection $connection,
        private readonly ClubRankingRepository $repository,
    ) {
    }

    public function load(Scale $scale): SeasonData
    {
        $season = $scale->season;
        $clubs = $this->clubs();
        $gamedays = $this->gamedays($season, $clubs);
        $teamPlayers = $this->teamPlayers($season);
        $managers = $this->managers($season);
        $referees = $this->referees($season, $scale);
        $entries = $this->manualEntries($season);
        $matrics = [
            ...array_map(static fn (TeamPlayer $player): int => $player->matric, $teamPlayers),
            ...array_map(static fn (CompetitionManager $manager): int => $manager->matric, $managers),
            ...array_map(static fn (Referee $referee): int => $referee->matric, $referees),
            ...array_filter(array_map(static fn (ManualEntry $entry): ?int => $entry->matric, $entries)),
            ...array_values($this->repository->aliases()),
            ...array_filter(array_map(NameNormalizer::matric(...), $this->officialNames($gamedays))),
        ];

        return new SeasonData(
            $season,
            $clubs,
            [...$this->licensees($matrics), ...$this->licenseesNamed(array_map(NameNormalizer::withoutMatric(...), $this->officialNames($gamedays)))],
            $this->competitions($season),
            $this->teamEntries($season),
            $teamPlayers,
            $gamedays,
            $managers,
            $referees,
            $this->repository->ententes($season),
            $this->repository->aliases(),
            $entries,
        );
    }

    /** @return list<Club> */
    private function clubs(): array
    {
        $regions = array_flip($this->connection->fetchFirstColumn('SELECT Code FROM kp_cr'));
        $departments = array_flip($this->connection->fetchFirstColumn('SELECT Code FROM kp_cd'));
        $clubs = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT c.Code, c.Libelle, cd.Code AS cd_code, cd.Libelle AS cd_label, cd.Code_comite_reg, cr.Libelle AS cr_label
             FROM kp_club c
             LEFT JOIN kp_cd cd ON cd.Code = c.Code_comite_dep
             LEFT JOIN kp_cr cr ON cr.Code = cd.Code_comite_reg',
        ) as $row) {
            $code = (string) $row['Code'];
            $selection = isset($regions[$code]) || isset($departments[$code]) || preg_match('/^CR\d|CD$/', $code) === 1;
            $foreign = $row['cd_code'] === null || (string) $row['Code_comite_reg'] === self::FOREIGN_REGION;
            $clubs[] = new Club($code, (string) $row['Libelle'], !$selection && !$foreign, (string) ($row['cd_label'] ?? ''), (string) ($row['cr_label'] ?? ''));
        }

        return $clubs;
    }

    /** @return list<Competition> */
    private function competitions(string $season): array
    {
        return array_map(
            static fn (array $row): Competition => new Competition(
                (string) $row['Code'], (string) $row['Libelle'], (string) ($row['Code_typeclt'] ?? Competition::CHAMPIONSHIP), (int) ($row['section'] ?? Competition::SECTION_NATIONAL),
                $row['Publication'] === self::PUBLISHED && (string) $row['Date_publication_calcul'] > '1970', $row['Verrou'] === self::LOCKED,
            ),
            $this->connection->fetchAllAssociative('SELECT c.Code, c.Libelle, c.Code_typeclt, g.section, c.Publication, c.Verrou, c.Date_publication_calcul
             FROM kp_competition c LEFT JOIN kp_groupe g ON g.Groupe = c.Code_ref WHERE c.Code_saison = ?', [$season]),
        );
    }

    /** @return list<TeamEntry> */
    private function teamEntries(string $season): array
    {
        $games = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT team_id, COUNT(*) AS games FROM (
                SELECT m.Id_equipeA AS team_id FROM kp_match m JOIN kp_journee j ON j.Id = m.Id_journee WHERE j.Code_saison = ? AND m.Validation = ?
                UNION ALL
                SELECT m.Id_equipeB FROM kp_match m JOIN kp_journee j ON j.Id = m.Id_journee WHERE j.Code_saison = ? AND m.Validation = ?
             ) t WHERE team_id IS NOT NULL GROUP BY team_id',
            [$season, self::VALIDATED, $season, self::VALIDATED],
        ) as $row) {
            $games[(int) $row['team_id']] = (int) $row['games'];
        }

        return array_map(
            static fn (array $row): TeamEntry => new TeamEntry((int) $row['Id'], (string) $row['Code_compet'], (int) $row['Numero'], (string) $row['Code_club'],
                (string) $row['Libelle'], (int) $row['Clt_publi'], (int) $row['CltNiveau_publi'], $games[(int) $row['Id']] ?? 0),
            $this->connection->fetchAllAssociative('SELECT Id, Code_compet, Numero, Code_club, Libelle, Clt_publi, CltNiveau_publi FROM kp_competition_equipe WHERE Code_saison = ? ORDER BY Id', [$season]),
        );
    }

    /** @return list<TeamPlayer> roster and players of the validated game sheets, by team entry (§ 5.5) */
    private function teamPlayers(string $season): array
    {
        $players = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT cej.Id_equipe, cej.Matric, cej.Capitaine FROM kp_competition_equipe_joueur cej
             JOIN kp_competition_equipe ce ON ce.Id = cej.Id_equipe WHERE ce.Code_saison = ?',
            [$season],
        ) as $row) {
            $players[(int) $row['Id_equipe']][(int) $row['Matric']] = ['roster' => !in_array($row['Capitaine'], self::NOT_PLAYERS, true), 'coach' => $row['Capitaine'] === self::COACH, 'played' => false];
        }
        foreach ($this->connection->fetchAllAssociative(
            'SELECT DISTINCT IF(mj.Equipe = \'A\', m.Id_equipeA, m.Id_equipeB) AS team_id, mj.Matric
             FROM kp_match_joueur mj
             JOIN kp_match m ON m.Id = mj.Id_match
             JOIN kp_journee j ON j.Id = m.Id_journee
             WHERE j.Code_saison = ? AND m.Validation = ? AND mj.Capitaine <> ?',
            [$season, self::VALIDATED, self::COACH],
        ) as $row) {
            if ($row['team_id'] === null) {
                continue;
            }
            $current = $players[(int) $row['team_id']][(int) $row['Matric']] ?? ['roster' => false, 'coach' => false];
            $players[(int) $row['team_id']][(int) $row['Matric']] = ['roster' => $current['roster'], 'coach' => false, 'played' => true];
        }
        $list = [];
        foreach ($players as $entryId => $byMatric) {
            foreach ($byMatric as $matric => $player) {
                $list[] = new TeamPlayer($entryId, $matric, $player['played'], $player['coach'] && !$player['played'], $player['roster']);
            }
        }

        return $list;
    }

    /**
     * @param list<Club> $clubs
     *
     * @return list<Gameday>
     */
    private function gamedays(string $season, array $clubs): array
    {
        $date = static fn (mixed $value): ?string => is_string($value) && $value !== '' && $value !== self::NO_DATE ? substr($value, 0, 10) : null;
        $codes = self::clubCodesByLabel($clubs);

        return array_map(
            static function (array $row) use ($date, $codes): Gameday {
                $label = (string) ($row['Organisateur'] ?? '');
                $code = trim((string) ($row['Code_organisateur'] ?? ''));

                return new Gameday((int) $row['Id'], (string) $row['Code_competition'], $date($row['Date_debut']), $date($row['Date_fin']),
                    (string) ($row['Lieu'] ?? ''), $code !== '' ? $code : ($codes[NameNormalizer::normalizeLabel($label)] ?? ''), $label,
                    (string) ($row['Delegue'] ?? ''), (string) ($row['ChefArbitre'] ?? ''));
            },
            $this->connection->fetchAllAssociative('SELECT Id, Code_competition, Date_debut, Date_fin, Lieu, Code_organisateur, Organisateur, Delegue, ChefArbitre FROM kp_journee WHERE Code_saison = ? ORDER BY Id', [$season]),
        );
    }

    /**
     * The organiser of a gameday is often typed as a label, with no club code: the label of a club identifies it
     * (HTML entities and accents ignored), unless two clubs share it.
     *
     * @param list<Club> $clubs
     *
     * @return array<string, string> normalised label → club code
     */
    private static function clubCodesByLabel(array $clubs): array
    {
        $codes = [];
        foreach ($clubs as $club) {
            $key = NameNormalizer::normalizeLabel($club->label);
            $codes[$key] = isset($codes[$key]) ? '' : $club->code;
        }
        unset($codes['']);

        return array_filter($codes, static fn (string $code): bool => $code !== '');
    }

    /** @return list<CompetitionManager> */
    private function managers(string $season): array
    {
        return array_map(
            static fn (array $row): CompetitionManager => new CompetitionManager((int) $row['Matric'], $row['Code_competition'] === null || $row['Code_competition'] === '' ? null : (string) $row['Code_competition']),
            $this->connection->fetchAllAssociative('SELECT Matric, Code_competition FROM kp_rc WHERE Code_saison = ?', [$season]),
        );
    }

    /** @return list<Referee> qualifications, and the validated games refereed in the national perimeter (7.3.3) */
    private function referees(string $season, Scale $scale): array
    {
        $games = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT j.Code_competition, m.Matric_arbitre_principal, m.Matric_arbitre_secondaire
             FROM kp_match m JOIN kp_journee j ON j.Id = m.Id_journee WHERE j.Code_saison = ? AND m.Validation = ?',
            [$season, self::VALIDATED],
        ) as $row) {
            if (!$scale->isNational((string) $row['Code_competition'])) {
                continue;
            }
            foreach ([$row['Matric_arbitre_principal'], $row['Matric_arbitre_secondaire']] as $matric) {
                if ($matric !== null && (int) $matric > 0) {
                    $games[(int) $matric] = ($games[(int) $matric] ?? 0) + 1;
                }
            }
        }

        return array_map(
            static fn (array $row): Referee => new Referee((int) $row['Matric'], (string) $row['arbitre'], (string) $row['niveau'], $games[(int) $row['Matric']] ?? 0),
            $this->connection->fetchAllAssociative("SELECT Matric, arbitre, niveau FROM kp_arbitre WHERE arbitre IS NOT NULL AND arbitre <> ''"),
        );
    }

    /** @return list<ManualEntry> */
    private function manualEntries(string $season): array
    {
        return array_map(
            static fn (array $row): ManualEntry => new ManualEntry((int) $row['id'], (string) $row['rule_code'], $row['matric'] === null ? null : (int) $row['matric'], $row['club_code'] === null ? null : (string) $row['club_code'], (string) $row['detail']),
            $this->connection->fetchAllAssociative('SELECT id, rule_code, matric, club_code, detail FROM kp_club_ranking_entry WHERE season = ?', [$season]),
        );
    }

    /**
     * @param list<int> $matrics
     *
     * @return list<Licensee>
     */
    private function licensees(array $matrics): array
    {
        $licensees = [];
        foreach (array_chunk(array_values(array_unique($matrics)), 1000) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            foreach ($this->connection->fetchAllAssociative("SELECT Matric, Nom, Prenom, Numero_club, Origine, Numero_comite_reg FROM kp_licence WHERE Matric IN ($placeholders)", $chunk) as $row) {
                $licensees[] = self::licensee($row);
            }
        }

        return $licensees;
    }

    /**
     * Licensees whose name is one of the free-text officials (N-5). The collation of kp_licence ignores case and
     * accents; homonyms are all returned, so that an ambiguous name is reported.
     *
     * @param list<string> $names
     *
     * @return list<Licensee>
     */
    private function licenseesNamed(array $names): array
    {
        $licensees = [];
        foreach ($names as $name) {
            foreach ($this->connection->fetchAllAssociative(
                "SELECT Matric, Nom, Prenom, Numero_club, Origine, Numero_comite_reg FROM kp_licence
                 WHERE CONCAT(Nom, ' ', Prenom) = ? OR CONCAT(Prenom, ' ', Nom) = ?",
                [$name, $name],
            ) as $row) {
                $licensees[] = self::licensee($row);
            }
        }

        return $licensees;
    }

    /**
     * @param list<Gameday> $gamedays
     *
     * @return list<string>
     */
    private function officialNames(array $gamedays): array
    {
        $names = [];
        foreach ($gamedays as $gameday) {
            foreach ([...NameNormalizer::split($gameday->delegate), ...NameNormalizer::split($gameday->chiefReferee)] as $name) {
                $names[preg_replace('/\s+/', ' ', $name)] = true;
            }
        }

        return array_map('strval', array_keys($names));
    }

    /** @param array<string, mixed> $row */
    private static function licensee(array $row): Licensee
    {
        return new Licensee((int) $row['Matric'], (string) $row['Nom'], (string) $row['Prenom'], (string) $row['Numero_club'], (string) $row['Origine'],
            (string) $row['Numero_comite_reg'] === self::FOREIGN_REGION);
    }
}
