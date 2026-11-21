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
    /** Second facteur (AUTH_HARDENING.md § 5, AUTH-2FA-16). */
    public const METHOD_TOTP = 'totp';
    public const METHOD_EMAIL_OTP = 'email_otp';
    public const METHOD_RECOVERY_CODE = 'recovery_code';

    public const REASON_UNKNOWN_USER = 'unknown_user';
    public const REASON_BAD_PASSWORD = 'bad_password';
    public const REASON_RATE_LIMITED = 'rate_limited';
    public const REASON_ACCOUNT_LOCKED = 'account_locked';
    /** Code de second facteur faux : compte dans le verrouillage comme un mauvais mot de passe (AUTH-2FA-10). */
    public const REASON_BAD_SECOND_FACTOR = 'bad_second_factor';

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
     * Seuls les échecs d'authentification comptent (AUTH-LK-03) : un refus `rate_limited` ou
     * `account_locked` ne vérifie aucun secret. Les compter verrouillait un compte sans un seul
     * mauvais mot de passe, et chaque essai pendant le verrou le prolongeait de 15 min.
     */
    private const LOCKING_REASONS = [self::REASON_BAD_PASSWORD, self::REASON_BAD_SECOND_FACTOR];

    /**
     * Secondes avant la levée du verrou (AUTH-LK-04), null si le compte n'est pas verrouillé.
     *
     * Le compte est verrouillé si {@see FAILURE_THRESHOLD} échecs (AUTH-LK-03) sont survenus
     * depuis le dernier succès dans la fenêtre glissante : un succès « reset » le compteur sans
     * table d'état ni job de déblocage (AUTH-LK-02). Pendant le verrou, aucun échec ne peut
     * s'ajouter (le secret n'est plus vérifié) : le verrou tombe quand le {@see FAILURE_THRESHOLD}ᵉ
     * échec le plus récent sort de la fenêtre.
     *
     * Le début de la fenêtre est calculé par PHP, comme `attempted_at` à l'écriture : comparer à
     * NOW() mélangerait le fuseau de PHP (UTC) et celui de MariaDB (CET), et la fenêtre ne
     * contiendrait jamais aucun échec. « Après le dernier succès » se lit sur `id` (auto-increment,
     * monotone) et non sur `attempted_at`, précis à la seconde seulement.
     */
    public function lockSecondsLeft(string $userCode): ?int
    {
        $sql = sprintf(<<<'SQL'
SELECT attempted_at
FROM kp_user_login_history
WHERE user_code = ?
  AND success = 0
  AND failure_reason IN (?, ?)
  AND attempted_at >= ?
  AND id > COALESCE(
      (SELECT MAX(id) FROM kp_user_login_history
       WHERE user_code = ? AND success = 1),
      0
  )
ORDER BY id DESC
LIMIT 1 OFFSET %d
SQL, self::FAILURE_THRESHOLD - 1);

        $now = new \DateTimeImmutable();
        $windowStart = $now->modify(sprintf('-%d minutes', self::WINDOW_MINUTES));
        $oldestLockingFailure = $this->connection->fetchOne($sql, [
            $userCode,
            ...self::LOCKING_REASONS,
            $windowStart->format('Y-m-d H:i:s'),
            $userCode,
        ]);
        if (!is_string($oldestLockingFailure)) {
            return null;
        }
        $liftsAt = (new \DateTimeImmutable($oldestLockingFailure))->modify(sprintf('+%d minutes', self::WINDOW_MINUTES));

        return max(1, $liftsAt->getTimestamp() - $now->getTimestamp());
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
