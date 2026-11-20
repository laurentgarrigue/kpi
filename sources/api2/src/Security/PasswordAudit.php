<?php

namespace App\Security;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Inventaire des mots de passe de kp_user (AUTH_HARDENING.md § 2bis, AUTH-RN-01) : type de hash par compte,
 * dernier login connu (journal de sécurité api2 ou kp_journal, qui couvre aussi le legacy) et e-mail.
 */
final class PasswordAudit
{
    public const KIND_MD5 = 'md5';
    public const KIND_BCRYPT = 'bcrypt';
    public const KIND_EMPTY = 'empty';
    public const KIND_OTHER = 'other';

    /** Actions de kp_journal écrites à chaque connexion : legacy (`Connexion`) et api2 (`Connexion Admin`). */
    private const JOURNAL_LOGIN_ACTIONS = ['Connexion', 'Connexion Admin'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array{code: string, identity: string, profile: int, email: string, kind: string, hasEmail: bool, lastLogin: ?string}>
     */
    public function accounts(): array
    {
        $lastLogins = $this->lastLogins();
        $rows = $this->connection->fetchAllAssociative('SELECT Code, Pwd, Identite, Mail, Niveau FROM kp_user ORDER BY Code');

        return array_map(function (array $row) use ($lastLogins): array {
            $email = trim((string) $row['Mail']);

            return [
                'code' => (string) $row['Code'],
                'identity' => (string) $row['Identite'],
                'profile' => (int) $row['Niveau'],
                'email' => $email,
                'kind' => self::kindOf((string) $row['Pwd']),
                'hasEmail' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                'lastLogin' => $lastLogins[$row['Code']] ?? null,
            ];
        }, $rows);
    }

    public static function kindOf(string $storedHash): string
    {
        return match (true) {
            $storedHash === '' => self::KIND_EMPTY,
            PasswordVerifier::isLegacyMd5Hash($storedHash) => self::KIND_MD5,
            password_get_info($storedHash)['algo'] === PASSWORD_BCRYPT => self::KIND_BCRYPT,
            default => self::KIND_OTHER,
        };
    }

    /** @return array<string, string> code → date du dernier login réussi (la plus récente des deux sources) */
    private function lastLogins(): array
    {
        $history = $this->connection->fetchAllKeyValue(
            'SELECT user_code, MAX(attempted_at) FROM kp_user_login_history WHERE success = 1 AND user_code IS NOT NULL GROUP BY user_code',
        );
        $journal = $this->connection->fetchAllKeyValue(
            'SELECT Users, MAX(Dates) FROM kp_journal WHERE Actions IN (?) GROUP BY Users',
            [self::JOURNAL_LOGIN_ACTIONS],
            [ArrayParameterType::STRING],
        );

        $lastLogins = [];
        foreach ([$history, $journal] as $source) {
            foreach ($source as $code => $date) {
                if ($date !== null && (!isset($lastLogins[$code]) || $date > $lastLogins[$code])) {
                    $lastLogins[$code] = (string) $date;
                }
            }
        }

        return $lastLogins;
    }
}
