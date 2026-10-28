<?php

namespace App\Pdf;

use Doctrine\DBAL\Connection;

/**
 * Phase 0 of PDF_GOTENBERG.md (§ 1.2, § 12): the legacy admin PDF pages (admin/Feuille*.php) no longer answer to
 * anonymous requests. app4, which has no PHP session, asks api2 for a short-lived signed link; api2 checks the role
 * of the equivalent document of the catalogue (§ 7) and the perimeter of the user, then signs the page and its
 * parameters (PdfLinkSigner, checked by sources/commun/MySignedLink.php).
 */
class LegacyPdfLinks
{
    /** Legacy page => [document of the catalogue, options adding data] (§ 7, I1 to I5). */
    private const PAGES = [
        'FeuilleGroups.php' => [PdfCatalog::TEAMS_GROUPS, []],
        'FeuillePresence.php' => [PdfCatalog::PRESENCE, []],
        'FeuillePresenceEN.php' => [PdfCatalog::PRESENCE, []],
        'FeuillePresencePhoto.php' => [PdfCatalog::PRESENCE, []],
        'FeuillePresencePhoto2.php' => [PdfCatalog::PRESENCE, []],
        'FeuillePresencePhotoRef.php' => [PdfCatalog::PRESENCE, []],
        'FeuillePresenceCat.php' => [PdfCatalog::PRESENCE, []],
        'FeuillePresenceU21.php' => [PdfCatalog::PRESENCE, ['category' => 'U21']],
        'FeuillePresenceNaissance.php' => [PdfCatalog::PRESENCE, ['birthdate' => '1']],
        'FeuillePresenceVisa.php' => [PdfCatalog::PRESENCE, ['visa' => '1']],
        'FeuilleControle.php' => [PdfCatalog::SCRUTINEERING_CONTROL, []],
        'FeuilleControleEN.php' => [PdfCatalog::SCRUTINEERING_CONTROL, []],
        'FeuillePoolArbitres.php' => [PdfCatalog::REFEREES_POOL, []],
        'FeuilleInstances.php' => [PdfCatalog::INSTANCES, []],
        'FeuilleListeMatchs.php' => [PdfCatalog::GAMES_LIST, []],
        'FeuilleListeMatchsEN.php' => [PdfCatalog::GAMES_LIST, []],
        'FeuilleMatchMulti.php' => [PdfCatalog::SCORESHEET, []],
        'FeuilleCltChpt.php' => [PdfCatalog::RANKING, []],
        'FeuilleCltNiveau.php' => [PdfCatalog::RANKING, []],
        'FeuilleCltMulti.php' => [PdfCatalog::RANKING, []],
        'FeuilleCltChptDetail.php' => [PdfCatalog::RANKING_TEAM_DETAIL, []],
        'FeuilleCltNiveauDetail.php' => [PdfCatalog::RANKING_TEAM_DETAIL, []],
        'FeuilleCltNiveauJournee.php' => [PdfCatalog::RANKING_GAMEDAY_DETAIL, []],
        'FeuilleCltNiveauPhase.php' => [PdfCatalog::RANKING_PHASES, []],
        'FeuilleCltNiveauNiveau.php' => [PdfCatalog::RANKING_PHASES, []],
        'FeuilleStats.php' => [PdfCatalog::STATS, []],
        'FeuilleStatsEN.php' => [PdfCatalog::STATS, []],
        'FeuilleCards.php' => [PdfCatalog::CARDS, []],
    ];

    /** Names of the season, competition and team parameters across the legacy pages. */
    private const SEASON_PARAMETERS = ['season', 'S'];
    private const COMPETITION_PARAMETERS = ['compet', 'Compet', 'codeCompet'];
    private const TEAM_PARAMETERS = ['team', 'equipe'];

    private const PARAMETER_NAME = '/^[A-Za-z_][A-Za-z0-9_]{0,30}$/';
    private const PARAMETER_VALUE_MAX_LENGTH = 2000;

    public function __construct(
        private readonly PdfAccessChecker $access,
        private readonly PdfLinkSigner $signer,
        private readonly Connection $connection,
    ) {
    }

    public static function isLegacyPage(string $page): bool
    {
        return isset(self::PAGES[$page]);
    }

    /**
     * Signed path and query of the legacy page, or null when the user may not get it.
     *
     * @param array<string, mixed> $parameters query the page expects
     *
     * @return array{path: string, query: array<string, string>}|null
     *
     * @throws InvalidPdfRequest
     */
    public function sign(string $page, array $parameters): ?array
    {
        [$document, $options] = self::PAGES[$page] ?? throw new InvalidPdfRequest('Unknown legacy PDF page.');
        foreach ($parameters as $name => $value) {
            if (!is_string($name) || !preg_match(self::PARAMETER_NAME, $name) || (!is_string($value) && !is_int($value))
                || strlen((string) $value) > self::PARAMETER_VALUE_MAX_LENGTH) {
                throw new InvalidPdfRequest('Malformed legacy PDF parameter.');
            }
        }

        $season = self::first($parameters, self::SEASON_PARAMETERS);
        $competition = self::first($parameters, self::COMPETITION_PARAMETERS);
        $team = self::first($parameters, self::TEAM_PARAMETERS);
        if (!$this->access->isAllowed($document, $options, $season, $competition) || !$this->teamAllowed($document, $team)) {
            return null;
        }

        $path = '/admin/' . $page;

        return ['path' => $path, 'query' => $this->signer->sign($path, $parameters)];
    }

    /** A team of another club or of a competition out of the perimeter (PDF-AUTH-04): denied. */
    private function teamAllowed(string $document, ?string $team): bool
    {
        if ($team === null || !ctype_digit($team)) {
            return true;
        }
        $row = $this->connection->fetchAssociative(
            'SELECT Code_club, Code_compet, Code_saison FROM kp_competition_equipe WHERE Id = ?',
            [(int) $team],
        );
        if ($row === false) {
            return true;
        }
        if (!$this->access->isAllowed($document, [], (string) $row['Code_saison'], (string) $row['Code_compet'])) {
            return false;
        }
        $clubs = $this->access->allowedClubs();

        return $clubs === null || in_array($row['Code_club'], $clubs, true);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param list<string>         $names
     */
    private static function first(array $parameters, array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($parameters[$name]) && (string) $parameters[$name] !== '') {
                return (string) $parameters[$name];
            }
        }

        return null;
    }
}
