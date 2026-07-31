<?php

/**
 * Bootstrap PHPUnit — api2 (Phase 4 du plan CI/CD).
 *
 * Charge l'autoloader puis les variables d'environnement Symfony. On tolère
 * l'absence de `.env` : en CI le job copie `.env.dist`, et la suite `unit`
 * (logique pure) n'a de toute façon besoin d'aucune variable.
 */

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}
