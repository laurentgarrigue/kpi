<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AUTH_HARDENING phase 2 : lien OAuth entre un compte KPI et un fournisseur externe
 * (initialement Google). Un compte KPI peut lier au plus un identifiant par fournisseur.
 */
final class Version20261102110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AUTH_HARDENING phase 2 : kp_user_oauth (lien compte KPI ↔ identifiant OAuth).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE kp_user_oauth (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(8) NOT NULL,
    provider VARCHAR(20) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    email_at_link VARCHAR(100) NOT NULL,
    linked_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_subject (provider, subject),
    UNIQUE KEY uq_user_provider (user_code, provider),
    KEY fk_user (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kp_user_oauth');
    }
}
