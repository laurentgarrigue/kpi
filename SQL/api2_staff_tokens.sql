-- Table pour les tokens d'authentification staff/report
-- Utilisée par TokenAuthenticator de l'API2

CREATE TABLE IF NOT EXISTS kp_staff_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) UNIQUE NOT NULL COMMENT 'Token unique (généré avec bin2hex(random_bytes(32)))',
    user_email VARCHAR(255) NOT NULL COMMENT 'Email de l''utilisateur',
    scope VARCHAR(50) NOT NULL COMMENT 'staff, report, ou admin',
    event_id INT DEFAULT NULL COMMENT 'Restreindre l''accès à un événement spécifique (NULL = tous)',
    active TINYINT(1) DEFAULT 1 COMMENT '1 = actif, 0 = désactivé',
    expiry DATETIME NOT NULL COMMENT 'Date d''expiration du token',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
    created_by VARCHAR(255) DEFAULT NULL COMMENT 'Créé par (email admin)',
    last_used_at DATETIME DEFAULT NULL COMMENT 'Dernière utilisation',
    description VARCHAR(255) DEFAULT NULL COMMENT 'Description du token',

    INDEX idx_token (token),
    INDEX idx_active_expiry (active, expiry),
    INDEX idx_user_email (user_email),

    CONSTRAINT chk_scope CHECK (scope IN ('staff', 'report', 'admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tokens d''authentification pour les endpoints staff et report de l''API2';

-- Exemple d'insertion d'un token de test (valide 1 an)
-- INSERT INTO kp_staff_tokens (token, user_email, scope, expiry, description)
-- VALUES (
--     'test_token_dev_only_replace_in_production',
--     'admin@kayak-polo.info',
--     'admin',
--     DATE_ADD(NOW(), INTERVAL 1 YEAR),
--     'Token de développement - À REMPLACER EN PRODUCTION'
-- );

-- Fonction helper pour générer un nouveau token (à utiliser dans PHP)
-- php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
