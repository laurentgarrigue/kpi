#!/usr/bin/env php
<?php

/**
 * Générateur de tokens pour l'API2
 * Usage: php bin/generate_token.php <user_email> <scope> [event_id] [days_valid]
 *
 * Scopes disponibles: staff, report, admin
 *
 * Exemples:
 *   php bin/generate_token.php admin@kayak-polo.info admin 365
 *   php bin/generate_token.php scrutineer@kayak-polo.info staff 226 30
 *   php bin/generate_token.php referee@kayak-polo.info report 226 7
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->loadEnv(dirname(__DIR__) . '/.env');

// Parse arguments
$userEmail = $argv[1] ?? null;
$scope = $argv[2] ?? null;
$eventId = $argv[3] ?? null;
$daysValid = $argv[4] ?? 30;

if (!$userEmail || !$scope) {
    echo "Usage: php bin/generate_token.php <user_email> <scope> [event_id] [days_valid]\n";
    echo "\n";
    echo "Arguments:\n";
    echo "  user_email    Email de l'utilisateur\n";
    echo "  scope         staff, report, ou admin\n";
    echo "  event_id      (Optionnel) ID de l'événement pour restreindre l'accès\n";
    echo "  days_valid    (Optionnel) Nombre de jours de validité (défaut: 30)\n";
    echo "\n";
    echo "Exemples:\n";
    echo "  php bin/generate_token.php admin@kayak-polo.info admin 365\n";
    echo "  php bin/generate_token.php scrutineer@kayak-polo.info staff 226 30\n";
    echo "  php bin/generate_token.php referee@kayak-polo.info report 226 7\n";
    exit(1);
}

// Validate scope
$validScopes = ['staff', 'report', 'admin'];
if (!in_array($scope, $validScopes)) {
    echo "❌ Erreur: scope invalide. Doit être: staff, report, ou admin\n";
    exit(1);
}

// Generate token
$token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime("+{$daysValid} days"));

// Connect to database
$databaseUrl = $_ENV['DATABASE_URL'] ?? 'mysql://root:root@kpi_db:3306/kayak_polo';

// Parse DATABASE_URL (peut contenir des paramètres comme ?serverVersion=...)
preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:\/]+):(\d+)\/([^\?]+)/', $databaseUrl, $matches);

if (!$matches) {
    echo "❌ Erreur: DATABASE_URL invalide: {$databaseUrl}\n";
    exit(1);
}

[, $user, $pass, $host, $port, $dbname] = $matches;

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname}", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Insert token
    $sql = "INSERT INTO kp_staff_tokens (token, user_email, scope, event_id, expiry, description, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $description = "Generated via CLI for {$scope} access";
    if ($eventId) {
        $description .= " (event {$eventId})";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $token,
        $userEmail,
        $scope,
        $eventId,
        $expiry,
        $description,
        'cli-script'
    ]);

    echo "✅ Token créé avec succès!\n";
    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Token:        {$token}\n";
    echo "User:         {$userEmail}\n";
    echo "Scope:        {$scope}\n";
    echo "Event ID:     " . ($eventId ?: '(tous les événements)') . "\n";
    echo "Expiration:   {$expiry} ({$daysValid} jours)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n";
    echo "Utilisation:\n";
    if ($scope === 'staff') {
        echo "  curl -k \"https://kpi.localhost/api2/staff/{$token}/teams/226\"\n";
        echo "  curl -k \"https://kpi.localhost/api2/staff/{$token}/players/123\"\n";
    } elseif ($scope === 'report') {
        echo "  curl -k \"https://kpi.localhost/api2/report/{$token}/game/456\"\n";
    } else {
        echo "  curl -k \"https://kpi.localhost/api2/staff/{$token}/teams/226\"\n";
        echo "  curl -k \"https://kpi.localhost/api2/report/{$token}/game/456\"\n";
    }
    echo "\n";

} catch (PDOException $e) {
    echo "❌ Erreur de base de données: " . $e->getMessage() . "\n";
    exit(1);
}
