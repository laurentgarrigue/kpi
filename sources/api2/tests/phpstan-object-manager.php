<?php

// Bootstrap minimal pour l'extension phpstan-doctrine : lui fournit un
// EntityManager réel afin de typer précisément entités et repositories.
// Référencé par phpstan.dist.neon (parameters.doctrine.objectManagerLoader).
//
// On démarre le kernel Symfony en env `dev` (le container y est déjà compilé,
// cf. containerXmlPath) et on en sort le `doctrine.orm.entity_manager`.

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
