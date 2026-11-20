<?php

namespace App\Security;

use Doctrine\DBAL\Connection;

/** Mandats d'un compte (kp_user_mandat) tels qu'app4 les reçoit avec la session. */
final class UserMandates
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array{id: int, libelle: mixed, niveau: int, filters: array<string, ?array<mixed>>}> */
    public function forUser(string $userCode): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, libelle, niveau, filtre_saison, filtre_competition, limitation_equipe_club, filtre_journee, id_evenement
             FROM kp_user_mandat WHERE user_code = ? ORDER BY id',
            [$userCode]
        );

        return array_map(fn (array $row) => [
            'id' => (int) $row['id'],
            'libelle' => $row['libelle'],
            'niveau' => (int) $row['niveau'],
            'filters' => [
                'seasons' => self::parsePipeFilter($row['filtre_saison']),
                'competitions' => self::parsePipeFilter($row['filtre_competition']),
                'clubs' => self::parseCommaFilter($row['limitation_equipe_club']),
                'journees' => self::parseCommaFilterInt($row['filtre_journee']),
                'events' => self::parsePipeFilterInt($row['id_evenement']),
            ],
        ], $rows);
    }

    private static function parsePipeFilter(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return array_values(array_filter(explode('|', trim($value, '|')), fn ($v) => $v !== ''));
    }

    private static function parseCommaFilter(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return array_values(array_filter(explode(',', $value), fn ($v) => trim($v) !== ''));
    }

    private static function parsePipeFilterInt(?string $value): ?array
    {
        $values = self::parsePipeFilter($value);
        return $values !== null ? array_map('intval', $values) : null;
    }

    private static function parseCommaFilterInt(?string $value): ?array
    {
        $values = self::parseCommaFilter($value);
        return $values !== null ? array_map('intval', $values) : null;
    }
}
