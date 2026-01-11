<?php
// Configuration Generale
define('NUM_VERSION', '5.38.73');

// Décalage horaire -35 minutes pour affichage des prochains matchs + match courant (kpmatchs.php)
define('DECALAGE_MINUTES', '-395 minutes');

// OpenRouteService API Key pour le calcul des distances routières
// Inscription gratuite: https://openrouteservice.org/dev/#/signup (2000 requêtes/jour)
// Laisser vide pour utiliser le calcul Haversine (vol d'oiseau avec facteur correctif)
define('ORS_API_KEY', '');

// Composer autoloader
require_once(__DIR__ . '/../vendor/autoload.php');

require_once('MyParams.php');

define('FPDF_FONTPATH', 'font/');
