#!/usr/bin/env php
<?php

/**
 * Vérification de l'expiration des tokens
 * Usage: php bin/check_token_expiry.php [days_warning]
 *
 * Affiche les tokens qui expirent dans les N jours
 * Par défaut : 30 jours
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->loadEnv(dirname(__DIR__) . '/.env');

// Parse arguments
$daysWarning = (int)($argv[1] ?? 30);

// Connect to database
$databaseUrl = $_ENV['DATABASE_URL'] ?? 'mysql://root:root@kpi_db:3306/kayak_polo';
preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:\/]+):(\d+)\/([^\?]+)/', $databaseUrl, $matches);

if (!$matches) {
    echo "❌ Erreur: DATABASE_URL invalide\n";
    exit(1);
}

[, $user, $pass, $host, $port, $dbname] = $matches;

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname}", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get tokens expiring soon
    $stmt = $pdo->prepare("
        SELECT
            token,
            user_email,
            scope,
            event_id,
            expiry,
            DATEDIFF(expiry, NOW()) as days_remaining
        FROM kp_staff_tokens
        WHERE active = 1
        AND expiry > NOW()
        AND expiry <= DATE_ADD(NOW(), INTERVAL :days DAY)
        ORDER BY expiry ASC
    ");

    $stmt->execute(['days' => $daysWarning]);
    $expiring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get expired tokens
    $stmt = $pdo->prepare("
        SELECT
            token,
            user_email,
            scope,
            event_id,
            expiry,
            DATEDIFF(NOW(), expiry) as days_expired
        FROM kp_staff_tokens
        WHERE active = 1
        AND expiry <= NOW()
        ORDER BY expiry DESC
    ");

    $stmt->execute();
    $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Display results
    echo "\n";
    echo "🔍 Vérification des tokens d'authentification\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";

    if (!empty($expired)) {
        echo "❌ TOKENS EXPIRÉS (" . count($expired) . ")\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        foreach ($expired as $token) {
            echo sprintf(
                "  Email:    %s\n  Scope:    %s\n  Event ID: %s\n  Expiré:   %s (il y a %d jours)\n  Token:    %s...\n\n",
                $token['user_email'],
                $token['scope'],
                $token['event_id'] ?? 'tous',
                $token['expiry'],
                $token['days_expired'],
                substr($token['token'], 0, 16)
            );
        }
        echo "\n⚠️  ACTION REQUISE : Désactiver ou renouveler ces tokens\n\n";
    } else {
        echo "✅ Aucun token expiré\n\n";
    }

    if (!empty($expiring)) {
        echo "⚠️  TOKENS EXPIRANT BIENTÔT (" . count($expiring) . " dans les {$daysWarning} jours)\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        foreach ($expiring as $token) {
            echo sprintf(
                "  Email:       %s\n  Scope:       %s\n  Event ID:    %s\n  Expire le:   %s (dans %d jours)\n  Token:       %s...\n\n",
                $token['user_email'],
                $token['scope'],
                $token['event_id'] ?? 'tous',
                $token['expiry'],
                $token['days_remaining'],
                substr($token['token'], 0, 16)
            );
        }
        echo "\n💡 Conseil : Renouveler ces tokens avant expiration\n\n";
    } else {
        echo "✅ Aucun token n'expire dans les {$daysWarning} prochains jours\n\n";
    }

    // Get all active tokens stats
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN expiry > NOW() THEN 1 ELSE 0 END) as valid,
            SUM(CASE WHEN expiry <= NOW() THEN 1 ELSE 0 END) as expired
        FROM kp_staff_tokens
        WHERE active = 1
    ");

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "📊 STATISTIQUES\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  Tokens actifs:  {$stats['total']}\n";
    echo "  Tokens valides: {$stats['valid']}\n";
    echo "  Tokens expirés: {$stats['expired']}\n";
    echo "\n";

} catch (PDOException $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "\n";
    exit(1);
}
