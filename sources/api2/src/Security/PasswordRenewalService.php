<?php

namespace App\Security;

use Doctrine\DBAL\Connection;

/**
 * Renouvellement forcé des mots de passe en début de saison (AUTH_HARDENING.md § 2bis).
 *
 * L'indicateur vit dans kp_user_password_renewal et non dans kp_user, table partagée avec le legacy :
 * `required_at` non NULL = le compte doit choisir un nouveau mot de passe avant d'accéder à app4 ;
 * `renewed_at` = date du dernier mot de passe choisi par l'utilisateur lui-même (renouvellement ou
 * « mot de passe oublié »), qui exempte le compte d'une campagne lancée après cette date.
 */
final class PasswordRenewalService
{
    public const JOURNAL_ACTION = 'Renouvellement mdp';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function isRequired(string $userCode): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM kp_user_password_renewal WHERE user_code = ? AND required_at IS NOT NULL',
            [$userCode],
        ) !== false;
    }

    /**
     * Marque « à renouveler » chaque compte qui a un mot de passe et ne l'a pas renouvelé depuis
     * $renewedSince. Les comptes sans mot de passe (Google seul) ne sont pas concernés.
     *
     * @return list<string> codes des comptes nouvellement marqués
     */
    public function requireRenewal(\DateTimeImmutable $renewedSince, bool $dryRun = false): array
    {
        /** @var list<string> $codes */
        $codes = $this->connection->fetchFirstColumn(
            "SELECT u.Code FROM kp_user u
             LEFT JOIN kp_user_password_renewal r ON r.user_code = u.Code
             WHERE u.Pwd IS NOT NULL AND u.Pwd <> ''
               AND r.required_at IS NULL
               AND (r.renewed_at IS NULL OR r.renewed_at < ?)
             ORDER BY u.Code",
            [$renewedSince->format('Y-m-d H:i:s')],
        );
        if ($dryRun) {
            return $codes;
        }

        foreach ($codes as $code) {
            $this->connection->executeStatement(
                'INSERT INTO kp_user_password_renewal (user_code, required_at) VALUES (?, NOW())
                 ON DUPLICATE KEY UPDATE required_at = NOW()',
                [$code],
            );
        }

        return $codes;
    }

    /** L'utilisateur vient de choisir lui-même un mot de passe conforme : levée de l'indicateur + kp_journal (AUTH-RN-05). */
    public function markRenewed(string $userCode, string $journalText): void
    {
        $this->connection->executeStatement(
            'INSERT INTO kp_user_password_renewal (user_code, required_at, renewed_at) VALUES (?, NULL, NOW())
             ON DUPLICATE KEY UPDATE required_at = NULL, renewed_at = NOW()',
            [$userCode],
        );
        $this->connection->executeStatement(
            'INSERT INTO kp_journal (Dates, Users, Actions, Journal) VALUES (NOW(), ?, ?, ?)',
            [$userCode, self::JOURNAL_ACTION, $journalText],
        );
    }

    public function countPending(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_user_password_renewal WHERE required_at IS NOT NULL');
    }
}
