<?php

// Feuille de marque PDF publique : retirée (DOC/specs/PDF_GOTENBERG.md § 7.3.2). Le public consulte la feuille de
// match d'app2 ; l'ancienne URL y redirige (301), vers le premier match de la liste. Supprimée en phase 6.
require_once('commun/MyParams.php');

$firstGame = (int) explode(',', (string) ($_GET['listMatch'] ?? ''))[0];
$target = rtrim(URL_APP, '/') . ($firstGame > 0 ? '/game/' . $firstGame : '/');

header('Location: ' . $target, true, 301);
exit;
