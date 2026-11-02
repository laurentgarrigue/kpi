<?php

namespace App\Security;

use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reconnaissance d'appareil + notification « nouvelle connexion » — AUTH_HARDENING.md § 3 (phase 1b).
 *
 * L'empreinte est volontairement grossière (IP /24 + famille de user-agent) : on veut qu'un
 * utilisateur se reconnectant depuis la même box et le même navigateur ne reçoive pas d'email
 * à chaque fois. Un changement de ville, de FAI ou d'appareil déclenche en revanche l'alerte.
 */
final class KnownDeviceService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly NotificationService $notificationService,
        private readonly string $app4Url,
    ) {
    }

    /**
     * Reconnaît ou enregistre l'appareil. Retourne true si c'est un nouvel appareil (notification
     * envoyée dans la foulée).
     */
    public function recognizeAndNotify(User $user, string $userEmail, Request $request): bool
    {
        $fingerprint = $this->fingerprint($request);
        $label = $this->label($request);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $row = $this->connection->fetchAssociative(
            'SELECT id, revoked_at FROM kp_user_known_device WHERE user_code = ? AND fingerprint = ?',
            [$user->getCode(), $fingerprint],
        );

        if ($row && $row['revoked_at'] === null) {
            // Appareil connu et actif : on met juste à jour last_seen_at, pas d'email.
            $this->connection->update(
                'kp_user_known_device',
                ['last_seen_at' => $now],
                ['id' => $row['id']],
            );
            return false;
        }

        // Nouvel appareil (jamais vu ou précédemment révoqué).
        $revokeToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $revokeHash = hash('sha256', $revokeToken);

        if ($row) {
            // Précédemment révoqué : on le ressuscite avec un nouveau revoke_token.
            $this->connection->update('kp_user_known_device', [
                'revoked_at' => null,
                'revoke_token_hash' => $revokeHash,
                'last_seen_at' => $now,
                'label' => $label,
            ], ['id' => $row['id']]);
        } else {
            $this->connection->insert('kp_user_known_device', [
                'user_code' => $user->getCode(),
                'fingerprint' => $fingerprint,
                'label' => $label,
                'revoke_token_hash' => $revokeHash,
                'created_at' => $now,
                'last_seen_at' => $now,
            ]);
        }

        if ($userEmail !== '') {
            $this->notificationService->sendNewDeviceAlert($userEmail, $user->getCode(), $label, $revokeToken);
        }

        return true;
    }

    /**
     * Révoque l'appareil correspondant au token signé (lien dans l'email). Révoque aussi tous
     * les refresh tokens actifs de l'utilisateur pour que l'intrus perde l'accès immédiatement.
     *
     * @return string|null Le code utilisateur si réussi, null si token inconnu/déjà utilisé.
     */
    public function revokeByToken(string $revokeToken, RefreshTokenService $refreshTokens): ?string
    {
        $hash = hash('sha256', $revokeToken);
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_code FROM kp_user_known_device WHERE revoke_token_hash = ? AND revoked_at IS NULL',
            [$hash],
        );
        if (!$row) {
            return null;
        }

        $this->connection->update(
            'kp_user_known_device',
            [
                'revoked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'revoke_token_hash' => null,
            ],
            ['id' => $row['id']],
        );
        $refreshTokens->revokeAllFor($row['user_code']);

        return $row['user_code'];
    }

    /**
     * Expose l'empreinte de la requête courante pour que les appelants puissent la poser sur
     * les refresh tokens et comparer « cet appareil » dans la liste (AUTH-DV-03).
     */
    public function currentFingerprint(Request $request): string
    {
        return $this->fingerprint($request);
    }

    /**
     * Liste les appareils actifs du compte, les plus récemment vus en premier (AUTH-DV-03).
     *
     * @return list<array{id:int, label:string, fingerprint:string, created_at:string, last_seen_at:string}>
     */
    public function listActiveFor(string $userCode): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, label, fingerprint, created_at, last_seen_at
             FROM kp_user_known_device
             WHERE user_code = ? AND revoked_at IS NULL
             ORDER BY last_seen_at DESC, id DESC',
            [$userCode],
        );

        return array_map(fn(array $r) => [
            'id' => (int) $r['id'],
            'label' => $r['label'],
            'fingerprint' => $r['fingerprint'],
            'created_at' => $r['created_at'],
            'last_seen_at' => $r['last_seen_at'],
        ], $rows);
    }

    /**
     * Révoque un appareil par id, et uniquement s'il appartient à l'utilisateur fourni (AUTH-DV-04).
     * Idempotent : la 2ᵉ invocation sur un device déjà révoqué retourne false mais ne lève pas.
     * Journalise dans kp_journal.
     *
     * @return bool true si une ligne active a été révoquée, false sinon (id inconnu, autre user, déjà révoqué).
     */
    public function revokeById(int $deviceId, string $userCode, RefreshTokenService $refreshTokens, string $journalReason): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, fingerprint FROM kp_user_known_device
             WHERE id = ? AND user_code = ? AND revoked_at IS NULL',
            [$deviceId, $userCode],
        );
        if (!$row) {
            return false;
        }

        $this->connection->update('kp_user_known_device', [
            'revoked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'revoke_token_hash' => null,
        ], ['id' => $row['id']]);

        $refreshTokens->revokeByDeviceFingerprint($userCode, $row['fingerprint']);
        $this->logRevocation($userCode, $journalReason);

        return true;
    }

    /**
     * Révoque tous les appareils actifs du compte sauf celui qui correspond à $currentFingerprint (AUTH-DV-05).
     * Retourne le nombre d'appareils révoqués (hors courant).
     */
    public function revokeAllOthersFor(string $userCode, string $currentFingerprint, RefreshTokenService $refreshTokens): int
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, fingerprint FROM kp_user_known_device
             WHERE user_code = ? AND revoked_at IS NULL AND fingerprint <> ?',
            [$userCode, $currentFingerprint],
        );
        if (count($rows) === 0) {
            return 0;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($rows as $r) {
            $this->connection->update('kp_user_known_device', [
                'revoked_at' => $now,
                'revoke_token_hash' => null,
            ], ['id' => $r['id']]);
            $refreshTokens->revokeByDeviceFingerprint($userCode, $r['fingerprint']);
        }
        $this->logRevocation($userCode, 'Révocation « déconnecter toutes les autres sessions » depuis /profile/security');

        return count($rows);
    }

    /** Combien de lignes `kp_user_known_device` existent pour (user, id), révoquées ou non — utilisé pour distinguer 404 et « déjà révoqué ». */
    public function countAnyFor(string $userCode, int $deviceId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM kp_user_known_device WHERE id = ? AND user_code = ?',
            [$deviceId, $userCode],
        );
    }

    /** Empreinte stockée d'un appareil (quel que soit son état). Null si inexistant ou appartenant à un autre user. */
    public function fingerprintOf(string $userCode, int $deviceId): ?string
    {
        $fp = $this->connection->fetchOne(
            'SELECT fingerprint FROM kp_user_known_device WHERE id = ? AND user_code = ?',
            [$deviceId, $userCode],
        );
        return is_string($fp) ? $fp : null;
    }

    private function logRevocation(string $userCode, string $journalText): void
    {
        try {
            $this->connection->prepare(
                "INSERT INTO kp_journal (Dates, Users, Actions, Journal) VALUES (NOW(), ?, 'Révocation appareil', ?)"
            )->executeStatement([$userCode, $journalText]);
        } catch (\Exception) {
            // Le journal fonctionnel est best-effort : son indisponibilité ne doit pas faire échouer la révocation.
        }
    }

    /**
     * Empreinte : IP masquée en /24 (IPv4) ou /48 (IPv6) + famille de user-agent. Suffisant
     * pour reconnaître « le même ordinateur à la maison » sans emprisonner l'utilisateur dans
     * une IP exacte qui change tous les jours.
     */
    private function fingerprint(Request $request): string
    {
        $ip = $request->getClientIp() ?? '0.0.0.0';
        $ua = (string) $request->headers->get('User-Agent', '');
        return hash('sha256', $this->maskIp($ip) . '|' . $this->uaFamily($ua));
    }

    private function label(Request $request): string
    {
        $ua = (string) $request->headers->get('User-Agent', '');
        $ip = $request->getClientIp() ?? '0.0.0.0';
        return substr($this->uaFamily($ua) . ' / ' . $ip, 0, 100);
    }

    private function maskIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 3)) . '::';
        }
        $parts = explode('.', $ip);
        return count($parts) === 4 ? "{$parts[0]}.{$parts[1]}.{$parts[2]}.0" : $ip;
    }

    private function uaFamily(string $ua): string
    {
        $lower = strtolower($ua);
        return match (true) {
            str_contains($lower, 'firefox') => 'Firefox',
            str_contains($lower, 'edg/') => 'Edge',
            str_contains($lower, 'chrome') => 'Chrome',
            str_contains($lower, 'safari') => 'Safari',
            str_contains($lower, 'opera') || str_contains($lower, 'opr/') => 'Opera',
            default => 'Autre',
        };
    }
}
