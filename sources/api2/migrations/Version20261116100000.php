<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renouvellement forcé des mots de passe (AUTH_HARDENING.md § 2bis) : indicateur hors de kp_user,
 * table partagée avec le legacy.
 */
final class Version20261116100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AUTH_HARDENING § 2bis : kp_user_password_renewal (mot de passe à renouveler).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE kp_user_password_renewal (
    user_code VARCHAR(8) NOT NULL,
    required_at DATETIME DEFAULT NULL,
    renewed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kp_user_password_renewal');
    }
}
