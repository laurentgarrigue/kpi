<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AUTH_HARDENING § 5 : second facteur (TOTP ou code par e-mail), codes de récupération, appareils de confiance.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE kp_user_second_factor (
    user_code VARCHAR(8) NOT NULL,
    method VARCHAR(16) DEFAULT NULL,
    totp_secret_encrypted VARBINARY(255) DEFAULT NULL,
    pending_totp_secret_encrypted VARBINARY(255) DEFAULT NULL,
    totp_last_timestep BIGINT UNSIGNED DEFAULT NULL,
    enrollment_required_since DATETIME DEFAULT NULL,
    activated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE kp_user_email_otp (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(8) NOT NULL,
    purpose VARCHAR(16) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user_time (user_code, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE kp_user_recovery_code (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(8) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE kp_user_trusted_device (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(8) NOT NULL,
    device_token_hash CHAR(64) NOT NULL,
    label VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kp_user_trusted_device');
        $this->addSql('DROP TABLE kp_user_recovery_code');
        $this->addSql('DROP TABLE kp_user_email_otp');
        $this->addSql('DROP TABLE kp_user_second_factor');
    }
}
