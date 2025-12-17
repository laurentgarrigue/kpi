#!/usr/bin/env php
<?php

/**
 * Rotation d'un token existant
 * Usage: php bin/rotate_token.php <old_token_prefix> [days_valid]
 *
 * Trouve le token par son préfixe, le désactive, et génère un nouveau token
 * avec les mêmes paramètres (user_email, scope, event_id)
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->loadEnv(dirname(__DIR__) . '/.env');

// Parse arguments
$oldTokenPrefix = $argv[1] ?? null;
$daysValid = (int)($argv[2] ?? 365);

if (!$oldTokenPrefix || strlen($oldTokenPrefix) < 8) {
    echo "Usage: php bin/rotate_token.php <token_prefix> [days_valid]\n";
    echo "\n";
    echo "Arguments:\n";
    echo "  token_prefix  Les 8+ premiers caractères du token à renouveler\n";
    echo "  days_valid    Nombre de jours de validité du nouveau token (défaut: 365)\n";
    echo "\n";
    echo "Exemples:\n";
    echo "  php bin/rotate_token.php 4aa7a96d 1825  # Renouveler pour 5 ans\n";
    echo "  php bin/rotate_token.php 42928d5c       # Renouveler pour 1 an (défaut)\n";
    exit(1);
}

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

    // Find old token
    $stmt = $pdo->prepare("
        SELECT token, user_email, scope, event_id, expiry, active
        FROM kp_staff_tokens
        WHERE token LIKE :prefix
        ORDER BY created_at DESC
        LIMIT 1
    ");

    $stmt->execute(['prefix' => $oldTokenPrefix . '%']);
    $oldToken = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldToken) {
        echo "❌ Aucun token trouvé commençant par '{$oldTokenPrefix}'\n";
        exit(1);
    }

    echo "\n";
    echo "🔄 Rotation de token\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";
    echo "📋 ANCIEN TOKEN\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  Email:    {$oldToken['user_email']}\n";
    echo "  Scope:    {$oldToken['scope']}\n";
    echo "  Event ID: " . ($oldToken['event_id'] ?? 'tous') . "\n";
    echo "  Expire:   {$oldToken['expiry']}\n";
    echo "  Actif:    " . ($oldToken['active'] ? 'Oui' : 'Non') . "\n";
    echo "  Token:    " . substr($oldToken['token'], 0, 16) . "...\n";
    echo "\n";

    // Confirm
    echo "⚠️  Cette action va:\n";
    echo "  1. Désactiver l'ancien token\n";
    echo "  2. Générer un nouveau token avec les mêmes paramètres\n";
    echo "  3. Validité du nouveau token: {$daysValid} jours\n";
    echo "\n";
    echo "Continuer ? [y/N] ";

    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (trim(strtolower($line)) !== 'y') {
        echo "❌ Opération annulée\n";
        exit(0);
    }
    fclose($handle);

    // Generate new token
    $newToken = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime("+{$daysValid} days"));

    // Deactivate old token
    $stmt = $pdo->prepare("
        UPDATE kp_staff_tokens
        SET active = 0
        WHERE token = :token
    ");
    $stmt->execute(['token' => $oldToken['token']]);

    // Create new token
    $stmt = $pdo->prepare("
        INSERT INTO kp_staff_tokens (token, user_email, scope, event_id, expiry, description, created_by)
        VALUES (:token, :user_email, :scope, :event_id, :expiry, :description, :created_by)
    ");

    $stmt->execute([
        'token' => $newToken,
        'user_email' => $oldToken['user_email'],
        'scope' => $oldToken['scope'],
        'event_id' => $oldToken['event_id'],
        'expiry' => $expiry,
        'description' => "Rotation du token " . substr($oldToken['token'], 0, 8) . "...",
        'created_by' => 'rotate_token.php'
    ]);

    echo "\n";
    echo "✅ Token renouvelé avec succès!\n";
    echo "\n";
    echo "📋 NOUVEAU TOKEN\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  Token:    {$newToken}\n";
    echo "  Email:    {$oldToken['user_email']}\n";
    echo "  Scope:    {$oldToken['scope']}\n";
    echo "  Event ID: " . ($oldToken['event_id'] ?? 'tous') . "\n";
    echo "  Expire:   {$expiry} ({$daysValid} jours)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n";
    echo "⚠️  ACTION REQUISE:\n";
    echo "  1. Mettre à jour le token dans .env.production\n";
    echo "  2. Redéployer l'application concernée\n";
    echo "\n";
    echo "Exemple pour app2:\n";
    echo "  API2_STAFF_TOKEN={$newToken}\n";
    echo "\n";

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
