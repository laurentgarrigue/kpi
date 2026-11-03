<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AUTH_HARDENING phase 1b : refresh tokens et appareils connus.
 *
 * - `kp_user_refresh_token` : stocke le hash SHA-256 du token opaque, la chaîne de rotation
 *   (replaced_by) et la révocation. Permet la détection de rejeu (AUTH-RT-03).
 * - `kp_user_known_device` : empreinte (IP /24 + user-agent famille) et libellé lisible,
 *   pour reconnaître un appareil et éviter la notification à chaque visite (AUTH-DV-01).
 */
final class Version20261102100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AUTH_HARDENING phase 1b : kp_user_refresh_token + kp_user_known_device.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE kp_user_refresh_token (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(8) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    device_fingerprint VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    replaced_by INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token_hash (token_hash),
    KEY idx_user_active (user_code, revoked_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE kp_user_known_device (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(8) NOT NULL,
    fingerprint CHAR(64) NOT NULL,
    label VARCHAR(100) NOT NULL,
    revoke_token_hash CHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_fingerprint (user_code, fingerprint),
    KEY idx_revoke_token (revoke_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kp_user_known_device');
        $this->addSql('DROP TABLE kp_user_refresh_token');
    }
}
