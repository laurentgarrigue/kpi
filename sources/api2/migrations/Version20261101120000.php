<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Durcissement de l'authentification app4, phase 1a : journal des tentatives de login.
 *
 * Spec : DOC/developer/in-progress/AUTH_HARDENING.md § 2.3.
 *
 * Table dédiée (pas de surcharge de `kp_journal`, qui est le journal fonctionnel) :
 * - lecture pattern = « lignes de la fenêtre glissante pour (user_code | ip) » → index composites,
 * - cycle de vie découplé (purge à 90 jours via commande console à venir),
 * - une seule table responsable d'un seul sujet (sécurité).
 */
final class Version20261101120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AUTH_HARDENING phase 1a : journal des tentatives de login (kp_user_login_history).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE kp_user_login_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(8) DEFAULT NULL,
    success TINYINT(1) NOT NULL,
    method VARCHAR(32) NOT NULL,
    failure_reason VARCHAR(32) DEFAULT NULL,
    legacy_hash TINYINT(1) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    attempted_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_user_time (user_code, attempted_at),
    KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kp_user_login_history');
    }
}
