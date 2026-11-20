<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Chiffre les secrets TOTP stockés en base (AUTH_HARDENING.md § 5.5) avec libsodium `secretbox` et une clé
 * dédiée, AUTH_2FA_ENCRYPTION_KEY (32 octets en base64), distincte de kernel.secret pour pouvoir la gérer à part.
 *
 * Sans clé valide, isConfigured() est faux : l'enrôlement TOTP est refusé, le code par e-mail reste possible.
 */
final class SecondFactorSecretBox
{
    private readonly ?string $key;

    public function __construct(
        #[Autowire('%env(AUTH_2FA_ENCRYPTION_KEY)%')]
        string $base64Key,
    ) {
        $key = base64_decode($base64Key, true);
        $this->key = is_string($key) && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $key : null;
    }

    public function isConfigured(): bool
    {
        return $this->key !== null;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return $nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->requireKey());
    }

    public function decrypt(string $sealed): ?string
    {
        if ($this->key === null || strlen($sealed) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $nonce = substr($sealed, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(substr($sealed, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);

        return $plaintext === false ? null : $plaintext;
    }

    private function requireKey(): string
    {
        return $this->key ?? throw new \LogicException('AUTH_2FA_ENCRYPTION_KEY is missing or is not 32 bytes of base64');
    }
}
