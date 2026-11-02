<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Journal des tentatives de login — table `kp_user_login_history`.
 *
 * Dépose une ligne par tentative (succès ou échec, et pour les échecs : par cause) et
 * déduit l'état de verrouillage de la fenêtre glissante des échecs récents. Pas de
 * colonne de verrouillage sur `kp_user` : on reste stateless et auto-expirant.
 *
 * Spec : {@see DOC/developer/in-progress/AUTH_HARDENING.md § 2.2}.
 */
final class LoginAttemptLogger
{
    public const METHOD_PASSWORD = 'password';
    public const METHOD_PASSWORD_RESET = 'password_reset';

    public const REASON_UNKNOWN_USER = 'unknown_user';
    public const REASON_BAD_PASSWORD = 'bad_password';
    public const REASON_RATE_LIMITED = 'rate_limited';
    public const REASON_ACCOUNT_LOCKED = 'account_locked';

    /** Seuil de verrouillage : 5 échecs sur la fenêtre glissante déclenchent le lock (AUTH-LK-01). */
    public const FAILURE_THRESHOLD = 5;

    /** Durée de la fenêtre glissante en minutes. */
    public const WINDOW_MINUTES = 15;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Journalise un succès. $legacyHash=true signale un login remonté via le fallback MD5
     * (AUTH-MD-01) — métrique de progression vers le retrait du MD5.
     */
    public function logSuccess(string $userCode, string $method, bool $legacyHash, Request $request): void
    {
        $this->insert($userCode, true, $method, null, $legacyHash, $request);
    }

    /** Journalise un échec. $userCode peut être null si on ne connaît pas l'utilisateur. */
    public function logFailure(?string $userCode, string $reason, Request $request, string $method = self::METHOD_PASSWORD): void
    {
        $this->insert($userCode, false, $method, $reason, false, $request);
    }

    /**
     * Un compte est verrouillé si {@see FAILURE_THRESHOLD} échecs sont survenus depuis le
     * dernier succès dans la fenêtre glissante — autrement dit : on compte uniquement les
     * échecs postérieurs au dernier succès.
     *
     * Mécanique utile : un succès « reset » le compteur sans table d'état ni job de déblocage
     * (AUTH-LK-02).
     *
     * Le début de la fenêtre est calculé par PHP, comme `attempted_at` à l'écriture : comparer à
     * NOW() mélangerait le fuseau de PHP (UTC) et celui de MariaDB (CET), et la fenêtre ne
     * contiendrait jamais aucun échec. « Après le dernier succès » se lit sur `id` (auto-increment,
     * monotone) et non sur `attempted_at`, précis à la seconde seulement.
     */
    public function isAccountLocked(string $userCode): bool
    {
        $sql = <<<'SQL'
SELECT COUNT(*)
FROM kp_user_login_history
WHERE user_code = ?
  AND success = 0
  AND attempted_at >= ?
  AND id > COALESCE(
      (SELECT MAX(id) FROM kp_user_login_history
       WHERE user_code = ? AND success = 1),
      0
  )
SQL;

        $windowStart = (new \DateTimeImmutable(sprintf('-%d minutes', self::WINDOW_MINUTES)))->format('Y-m-d H:i:s');
        $failures = (int) $this->connection->executeQuery($sql, [
            $userCode,
            $windowStart,
            $userCode,
        ])->fetchOne();

        return $failures >= self::FAILURE_THRESHOLD;
    }

    private function insert(?string $userCode, bool $success, string $method, ?string $reason, bool $legacyHash, Request $request): void
    {
        $userAgent = (string) ($request->headers->get('User-Agent') ?? '');
        $this->connection->insert('kp_user_login_history', [
            'user_code' => $userCode,
            'success' => $success ? 1 : 0,
            'method' => $method,
            'failure_reason' => $reason,
            'legacy_hash' => $legacyHash ? 1 : 0,
            'ip' => (string) ($request->getClientIp() ?? ''),
            'user_agent' => mb_substr($userAgent, 0, 255),
            'attempted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
