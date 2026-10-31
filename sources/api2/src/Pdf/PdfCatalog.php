<?php

namespace App\Pdf;

/**
 * Documents of the PDF catalogue and who may get them (PDF_GOTENBERG.md § 7, DROITS_PAR_PROFIL.md « Documents PDF »).
 *
 * The admin route (`/admin/pdf/{doc}`) requires the minimum role of the document, plus the role of each option that
 * adds data; the public route (`/pdf/{doc}`) only serves the documents listed as public, with published data.
 */
final class PdfCatalog
{
    public const TEAMS_GROUPS = 'teams-groups';
    public const PRESENCE = 'presence';
    public const SCRUTINEERING_CONTROL = 'scrutineering-control';
    public const REFEREES_POOL = 'referees-pool';
    public const INSTANCES = 'instances';
    public const GAMES_LIST = 'games-list';
    public const GAMES_GRID = 'games-grid';
    public const SCORESHEET = 'scoresheet';
    public const RANKING = 'ranking';
    public const RANKING_TEAM_DETAIL = 'ranking-team-detail';
    public const RANKING_GAMEDAY_DETAIL = 'ranking-gameday-detail';
    public const RANKING_PHASES = 'ranking-phases';
    public const RANKING_TIEBREAK = 'ranking-tiebreak';
    public const STATS = 'stats';
    public const CARDS = 'cards';
    public const QRCODES = 'qrcodes';
    public const QRCODE_APP = 'qrcode-app';

    /** Minimum role of each admin document (§ 7.2 to § 7.5). */
    private const ADMIN_ROLES = [
        self::TEAMS_GROUPS => 'ROLE_VIEWER',
        self::PRESENCE => 'ROLE_PRESENCE_VIEWER',
        self::SCRUTINEERING_CONTROL => 'ROLE_SCRUTINEER',
        self::REFEREES_POOL => 'ROLE_ADMIN',
        self::INSTANCES => 'ROLE_VIEWER',
        self::GAMES_LIST => 'ROLE_SCORER',
        self::SCORESHEET => 'ROLE_SCORER',
        self::RANKING => 'ROLE_ORGANIZER',
        self::RANKING_TEAM_DETAIL => 'ROLE_ORGANIZER',
        self::RANKING_GAMEDAY_DETAIL => 'ROLE_ORGANIZER',
        self::RANKING_PHASES => 'ROLE_ORGANIZER',
        self::RANKING_TIEBREAK => 'ROLE_ORGANIZER',
        self::STATS => 'ROLE_VIEWER',
        self::CARDS => 'ROLE_ORGANIZER',
    ];

    /** Options that add data, with their own role (§ 7.2): `option` or `option=value`. */
    private const OPTION_ROLES = [
        self::PRESENCE => [
            'visa' => 'ROLE_ORGANIZER',
            'view=birthyear' => 'ROLE_ADMIN',
            'category=U21' => 'ROLE_ORGANIZER',
        ],
    ];

    /** Documents served by the public route, published data only (§ 7.3 to § 7.5). */
    private const PUBLIC_DOCUMENTS = [
        self::GAMES_LIST,
        self::GAMES_GRID,
        self::RANKING,
        self::RANKING_TEAM_DETAIL,
        self::RANKING_GAMEDAY_DETAIL,
        self::RANKING_PHASES,
        self::QRCODES,
        self::QRCODE_APP,
    ];

    public static function isAdminDocument(string $document): bool
    {
        return isset(self::ADMIN_ROLES[$document]);
    }

    public static function isPublicDocument(string $document): bool
    {
        return in_array($document, self::PUBLIC_DOCUMENTS, true);
    }

    public static function adminRole(string $document): ?string
    {
        return self::ADMIN_ROLES[$document] ?? null;
    }

    /**
     * Roles required by the options of a request, on top of the document role.
     *
     * @param array<string, string> $options
     *
     * @return list<string>
     */
    public static function optionRoles(string $document, array $options): array
    {
        $roles = [];
        foreach (self::OPTION_ROLES[$document] ?? [] as $option => $role) {
            [$name, $value] = array_pad(explode('=', $option, 2), 2, null);
            $given = $options[$name] ?? null;
            if ($given !== null && ($value === null ? $given === '1' : strcasecmp($given, $value) === 0)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
