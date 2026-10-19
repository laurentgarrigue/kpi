<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Last journal entry of a team, shown as "Dernière modification … par Prénom NOM" on the team presence sheet and on
 * the scrutineering screen (PAGE_SCRUTINEERING.md § 5.1). The two are kept apart: the scrutineering actions
 * ("Scrut …") do not change the composition, and the composition actions do not change the equipment.
 */
final class TeamLastUpdateService
{
    private const SCRUTINEERING_ACTIONS = 'Scrut %';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array{date: string, user: string, action: string}|null */
    public function presence(int $teamId): ?array
    {
        return $this->lastUpdate($teamId, 'NOT LIKE');
    }

    /** @return array{date: string, user: string, action: string}|null */
    public function scrutineering(int $teamId): ?array
    {
        return $this->lastUpdate($teamId, 'LIKE');
    }

    /** "Prénom NOM" from the licence, else the account identity, else the account code. */
    public static function displayName(string $code, ?string $firstName, ?string $lastName, ?string $identity): string
    {
        $fullName = trim(($firstName ?? '') . ' ' . mb_strtoupper($lastName ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        return trim($identity ?? '') ?: $code;
    }

    /** @return array{date: string, user: string, action: string}|null */
    private function lastUpdate(int $teamId, string $actionOperator): ?array
    {
        // Journal entries use two formats depending on origin: api2 "Equipe {id} - …" (or "Equipe {id} <- …" for a
        // copy) and legacy admin "Equipe : {id} - …". The trailing space keeps team 12 from matching team 123. Only a
        // numeric account code is a licence number (a string compared with the numeric Matric would be cast to 0).
        $row = $this->connection->fetchAssociative(
            "SELECT j.Dates, j.Users, j.Actions, u.Identite, l.Nom, l.Prenom
             FROM kp_journal j
             LEFT JOIN kp_user u ON u.Code = j.Users
             LEFT JOIN kp_licence l ON j.Users REGEXP '^[0-9]+$' AND l.Matric = j.Users
             WHERE (j.Journal LIKE ? OR j.Journal LIKE ?) AND j.Actions $actionOperator ?
             ORDER BY j.Dates DESC, j.Id DESC
             LIMIT 1",
            ["%Equipe {$teamId} %", "%Equipe : {$teamId} %", self::SCRUTINEERING_ACTIONS],
        );

        if (!$row) {
            return null;
        }

        return [
            'date' => $row['Dates'],
            'user' => self::displayName((string) $row['Users'], $row['Prenom'], $row['Nom'], $row['Identite']),
            'action' => $row['Actions'],
        ];
    }
}
