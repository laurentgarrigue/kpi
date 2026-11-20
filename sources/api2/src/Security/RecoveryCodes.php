<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * 10 codes de récupération à usage unique, affichés une seule fois et stockés hachés en bcrypt
 * (AUTH_HARDENING.md § 5, AUTH-2FA-06). Format « abcde-fghij », sans caractères ambigus.
 */
final class RecoveryCodes
{
    public const COUNT = 10;
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const HALF_LENGTH = 5;
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Remplace les codes du compte ; les anciens ne valent plus rien.
     *
     * @return list<string> codes en clair, à montrer une seule fois
     */
    public function regenerate(string $userCode): array
    {
        $this->deleteFor($userCode);
        $now = $this->clock->now()->format(self::DATE_FORMAT);
        $codes = [];
        for ($i = 0; $i < self::COUNT; $i++) {
            $code = $this->randomHalf() . '-' . $this->randomHalf();
            $codes[] = $code;
            $this->connection->insert('kp_user_recovery_code', [
                'user_code' => $userCode,
                'code_hash' => password_hash(self::normalize($code), PASSWORD_BCRYPT),
                'created_at' => $now,
            ]);
        }

        return $codes;
    }

    public function consume(string $userCode, string $code): bool
    {
        $normalized = self::normalize($code);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, code_hash FROM kp_user_recovery_code WHERE user_code = ? AND used_at IS NULL',
            [$userCode],
        );
        foreach ($rows as $row) {
            if (password_verify($normalized, (string) $row['code_hash'])) {
                $this->connection->executeStatement(
                    'UPDATE kp_user_recovery_code SET used_at = ? WHERE id = ?',
                    [$this->clock->now()->format(self::DATE_FORMAT), $row['id']],
                );

                return true;
            }
        }

        return false;
    }

    public function remainingFor(string $userCode): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM kp_user_recovery_code WHERE user_code = ? AND used_at IS NULL',
            [$userCode],
        );
    }

    public function deleteFor(string $userCode): void
    {
        $this->connection->executeStatement('DELETE FROM kp_user_recovery_code WHERE user_code = ?', [$userCode]);
    }

    /** Un code de récupération n'est jamais un code TOTP ou e-mail (6 chiffres). */
    public static function looksLikeRecoveryCode(string $code): bool
    {
        return strlen(self::normalize($code)) === 2 * self::HALF_LENGTH;
    }

    private static function normalize(string $code): string
    {
        return strtolower(preg_replace('/[\s-]+/', '', $code) ?? '');
    }

    private function randomHalf(): string
    {
        $half = '';
        for ($i = 0; $i < self::HALF_LENGTH; $i++) {
            $half .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $half;
    }
}
