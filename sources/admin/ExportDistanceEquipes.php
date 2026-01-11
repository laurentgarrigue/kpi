<?php
/**
 * Export des distances kilométriques des équipes d'une compétition
 *
 * Génère un fichier ODS avec :
 * - Feuille 1 : Kilométrage par équipe et par journée
 * - Feuille 2 : Matrice des distances inter-clubs
 *
 * @version 1.0.0
 */

require_once('../vendor/autoload.php');
require_once('../commun/MyBdd.php');
require_once('../commun/MyTools.php');
require_once('../commun/DistanceService.php');

use OpenSpout\Writer\ODS\Writer;
use OpenSpout\Writer\ODS\Options;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\Color;

if (!isset($_SESSION)) {
    session_start();
}
// Vérification authentification
if (!isset($_SESSION['User']) || $_SESSION['Profile'] > 6) {
    exit("Erreur : Accès non autorisé.");
}

// Chargement des langues
$langue = parse_ini_file("../commun/MyLang.ini", true);
$lang = (utyGetSession('lang') == 'en') ? $langue['en'] : $langue['fr'];

$myBdd = new MyBdd();
$distanceService = new DistanceService($myBdd->pdo);

// Récupérer la compétition depuis la session ou le paramètre
$codeCompet = utyGetGet('compet', utyGetSession('codeCompet', ''));
$codeSaison = ($codeCompet === 'POOL') ? 1000 : $myBdd->GetActiveSaison();

if (empty($codeCompet) || $codeCompet == '*') {
    exit("Erreur : Aucune compétition sélectionnée.");
}

// Vérifier que la compétition n'est pas internationale
$sql = "SELECT c.Code, c.Libelle, c.Code_niveau, cr.Code
        FROM kp_competition c
        LEFT JOIN kp_cd cd ON c.Code_niveau LIKE CONCAT(cd.Code, '%')
        LEFT JOIN kp_cr cr ON cd.Code_comite_reg = cr.Code
        WHERE c.Code = ? AND c.Code_saison = ?";
$stmt = $myBdd->pdo->prepare($sql);
$stmt->execute([$codeCompet, $codeSaison]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) {
    exit("Erreur : Compétition introuvable.");
}

// Pour les compétitions internationales (vérifier via les équipes)
$sql = "SELECT COUNT(*) as nb_intl
        FROM kp_competition_equipe ce
        JOIN kp_club c ON ce.Code_club = c.Code
        JOIN kp_cd cd ON c.Code_comite_dep = cd.Code
        WHERE ce.Code_compet = ? AND ce.Code_saison = ?
        AND cd.Code_comite_reg = '98'";
$stmt = $myBdd->pdo->prepare($sql);
$stmt->execute([$codeCompet, $codeSaison]);
$intlCount = $stmt->fetch(PDO::FETCH_ASSOC);

// Si toutes les équipes sont internationales, bloquer l'export
$sql = "SELECT COUNT(*) as nb_total FROM kp_competition_equipe WHERE Code_compet = ? AND Code_saison = ?";
$stmt = $myBdd->pdo->prepare($sql);
$stmt->execute([$codeCompet, $codeSaison]);
$totalCount = $stmt->fetch(PDO::FETCH_ASSOC);

if ($totalCount['nb_total'] > 0 && $intlCount['nb_intl'] == $totalCount['nb_total']) {
    exit("Erreur : Cette fonctionnalité n'est pas disponible pour les compétitions internationales.");
}

// ============================================================================
// 1. CHARGER LES ÉQUIPES AVEC COORDONNÉES DES CLUBS
// ============================================================================

$sql = "SELECT ce.Id, ce.Libelle as Equipe, ce.Code_club,
               c.Libelle as Club, c.Coord, c.Postal,
               cd.Code as Code_dep, cd.Code_comite_reg
        FROM kp_competition_equipe ce
        JOIN kp_club c ON ce.Code_club = c.Code
        LEFT JOIN kp_cd cd ON c.Code_comite_dep = cd.Code
        WHERE ce.Code_compet = ? AND ce.Code_saison = ?
        AND (cd.Code_comite_reg IS NULL OR cd.Code_comite_reg != '98')
        ORDER BY ce.Libelle";

$stmt = $myBdd->pdo->prepare($sql);
$stmt->execute([$codeCompet, $codeSaison]);

$equipes = [];
$clubs = []; // Pour éviter les doublons (plusieurs équipes du même club)
$clubsLocations = []; // Pour la matrice

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $coords = null;

    // Essayer de parser les coordonnées du club
    if (!empty($row['Coord'])) {
        $coords = DistanceService::parseCoordString($row['Coord']);
    }

    // Si pas de coordonnées, essayer via l'adresse postale
    if (!$coords && !empty($row['Postal'])) {
        if (preg_match('/(\d{5})\s+(.+)$/i', $row['Postal'], $matches)) {
            $departement = substr($matches[1], 0, 2);
            $ville = $matches[2];
            $coords = $distanceService->findCityCoordinates($ville, $departement);
        }
    }

    $equipes[] = [
        'id' => $row['Id'],
        'equipe' => $row['Equipe'],
        'code_club' => $row['Code_club'],
        'club' => $row['Club'],
        'lat' => $coords ? $coords['lat'] : null,
        'lon' => $coords ? $coords['lon'] : null,
        'coords_ok' => ($coords && $coords['lat'] && $coords['lon'])
    ];

    // Stocker les clubs uniques pour la matrice
    if (!isset($clubs[$row['Code_club']])) {
        $clubs[$row['Code_club']] = [
            'code' => $row['Code_club'],
            'libelle' => $row['Club'],
            'lat' => $coords ? $coords['lat'] : null,
            'lon' => $coords ? $coords['lon'] : null
        ];

        if ($coords && $coords['lat'] && $coords['lon']) {
            $clubsLocations[] = [
                'id' => $row['Code_club'],
                'lat' => $coords['lat'],
                'lon' => $coords['lon']
            ];
        }
    }
}

if (empty($equipes)) {
    exit("Erreur : Aucune équipe trouvée pour cette compétition.");
}

// ============================================================================
// 2. CHARGER LES JOURNÉES AVEC COORDONNÉES DES LIEUX
// ============================================================================

$sql = "SELECT j.Id, j.Nom, j.Libelle, j.Lieu, j.Departement, j.Date_debut
        FROM kp_journee j
        WHERE j.Code_competition = ? AND j.Code_saison = ?
        ORDER BY j.Date_debut, j.Id";

$stmt = $myBdd->pdo->prepare($sql);
$stmt->execute([$codeCompet, $codeSaison]);

$journees = [];
$journeesLocations = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $coords = null;
    $lieuMatch = null;

    // Essayer de trouver les coordonnées du lieu
    if (!empty($row['Lieu'])) {
        $coords = $distanceService->findCityCoordinates($row['Lieu'], $row['Departement']);
        if ($coords) {
            $lieuMatch = $coords['ville'];
        }
    }

    $journees[] = [
        'id' => $row['Id'],
        'nom' => $row['Nom'] ?: $row['Libelle'],
        'lieu' => $row['Lieu'],
        'lieu_match' => $lieuMatch,
        'departement' => $row['Departement'],
        'date' => $row['Date_debut'],
        'lat' => $coords ? $coords['lat'] : null,
        'lon' => $coords ? $coords['lon'] : null,
        'coords_ok' => ($coords && $coords['lat'] && $coords['lon'])
    ];

    if ($coords && $coords['lat'] && $coords['lon']) {
        $journeesLocations[] = [
            'id' => 'J' . $row['Id'],
            'lat' => $coords['lat'],
            'lon' => $coords['lon']
        ];
    }
}

// ============================================================================
// 3. CALCULER LES DISTANCES
// ============================================================================

// 3.1 Matrice des distances inter-clubs
$clubMatrix = [];
if (count($clubsLocations) >= 2) {
    $clubMatrix = $distanceService->getDistanceMatrix($clubsLocations);
}

// 3.2 Distances équipes -> journées
$equipesDistances = [];
foreach ($equipes as &$equipe) {
    $equipe['distances'] = [];
    $equipe['total_km'] = 0;
    $equipe['nb_deplacements'] = 0;

    foreach ($journees as $journee) {
        $distance = null;

        if ($equipe['coords_ok'] && $journee['coords_ok']) {
            $result = $distanceService->getDistance(
                $equipe['lat'], $equipe['lon'],
                $journee['lat'], $journee['lon']
            );
            $distance = $result['distance_km'];

            if ($distance !== null) {
                // Aller-retour
                $equipe['total_km'] += ($distance * 2);
                $equipe['nb_deplacements']++;
            }
        }

        $equipe['distances'][$journee['id']] = $distance;
    }
}
unset($equipe);

// ============================================================================
// 4. GÉNÉRER LE FICHIER ODS
// ============================================================================

$fileName = 'Distances_' . $codeCompet . '_' . date('Y-m-d') . '.ods';
$tempFile = '/tmp/' . $fileName;

try {
    $options = new Options();
    $writer = new Writer($options);
    $writer->openToFile($tempFile);

    // Style pour les en-têtes
    $headerStyle = new Style();
    $headerStyle->setFontBold();
    $headerStyle->setBackgroundColor('CCCCCC');

    // Style pour les cellules N/A
    $naStyle = new Style();
    $naStyle->setFontColor('999999');

    // ========================================================================
    // FEUILLE 1 : Kilométrage par équipe
    // ========================================================================

    $sheet1 = $writer->getCurrentSheet();
    $sheet1->setName('Km par equipe');

    // En-tête
    $headerCells = ['Equipe', 'Club', 'Code Club', 'Total km (A/R)', 'Nb deplacements'];
    foreach ($journees as $journee) {
        $headerCells[] = $journee['nom'] . "\n" . ($journee['lieu'] ?: '?');
    }
    $headerRow = Row::fromValues($headerCells, $headerStyle);
    $writer->addRow($headerRow);

    // Données des équipes
    foreach ($equipes as $equipe) {
        $rowData = [
            $equipe['equipe'],
            $equipe['club'],
            $equipe['code_club'],
            $equipe['coords_ok'] ? $equipe['total_km'] . ' km' : 'N/A',
            $equipe['coords_ok'] ? $equipe['nb_deplacements'] : 'N/A'
        ];

        foreach ($journees as $journee) {
            $dist = $equipe['distances'][$journee['id']] ?? null;
            if ($dist === null) {
                $rowData[] = 'N/A';
            } elseif ($dist == 0) {
                $rowData[] = '0 (local)';
            } else {
                $rowData[] = $dist . ' km';
            }
        }

        $writer->addRow(Row::fromValues($rowData));
    }

    // Ligne vide puis légende
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['Legende:']));
    $writer->addRow(Row::fromValues(['- Distances en kilometres (aller simple)']));
    $writer->addRow(Row::fromValues(['- Total km = somme des allers-retours']));
    $writer->addRow(Row::fromValues(['- N/A = coordonnees non disponibles']));

    if (!$distanceService->isConfigured()) {
        $writer->addRow(Row::fromValues(['- Calcul: estimation vol d\'oiseau x 1.3 (API non configuree)']));
    } else {
        $writer->addRow(Row::fromValues(['- Calcul: distances routieres via OpenRouteService']));
    }

    // ========================================================================
    // FEUILLE 2 : Matrice des distances inter-clubs
    // ========================================================================

    $sheet2 = $writer->addNewSheetAndMakeItCurrent();
    $sheet2->setName('Matrice inter-clubs');

    // En-tête de la matrice
    $matrixHeader = ['Club \\ Club'];
    foreach ($clubs as $club) {
        $matrixHeader[] = $club['code'];
    }
    $writer->addRow(Row::fromValues($matrixHeader, $headerStyle));

    // Lignes de la matrice
    foreach ($clubs as $clubFrom) {
        $rowData = [$clubFrom['code'] . ' - ' . $clubFrom['libelle']];

        foreach ($clubs as $clubTo) {
            if ($clubFrom['code'] === $clubTo['code']) {
                $rowData[] = '-';
            } elseif (isset($clubMatrix[$clubFrom['code']][$clubTo['code']])) {
                $dist = $clubMatrix[$clubFrom['code']][$clubTo['code']]['distance_km'];
                $rowData[] = ($dist !== null) ? $dist . ' km' : 'N/A';
            } else {
                // Calcul à la volée si pas dans la matrice
                if ($clubFrom['lat'] && $clubFrom['lon'] && $clubTo['lat'] && $clubTo['lon']) {
                    $result = $distanceService->getDistance(
                        $clubFrom['lat'], $clubFrom['lon'],
                        $clubTo['lat'], $clubTo['lon']
                    );
                    $rowData[] = ($result['distance_km'] !== null) ? $result['distance_km'] . ' km' : 'N/A';
                } else {
                    $rowData[] = 'N/A';
                }
            }
        }

        $writer->addRow(Row::fromValues($rowData));
    }

    // Informations supplémentaires
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['Competition: ' . $codeCompet . ' - ' . ($competition['Libelle'] ?? '')]));
    $writer->addRow(Row::fromValues(['Saison: ' . $codeSaison]));
    $writer->addRow(Row::fromValues(['Export: ' . date('d/m/Y H:i')]));
    $writer->addRow(Row::fromValues(['Nombre d\'equipes: ' . count($equipes)]));
    $writer->addRow(Row::fromValues(['Nombre de journees: ' . count($journees)]));

    // Avertissements sur les données manquantes
    $clubsSansCoord = array_filter($clubs, fn($c) => !$c['lat'] || !$c['lon']);
    $journeesSansCoord = array_filter($journees, fn($j) => !$j['coords_ok']);

    if (!empty($clubsSansCoord)) {
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['ATTENTION - Clubs sans coordonnees:']));
        foreach ($clubsSansCoord as $club) {
            $writer->addRow(Row::fromValues(['  - ' . $club['code'] . ' - ' . $club['libelle']]));
        }
    }

    if (!empty($journeesSansCoord)) {
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['ATTENTION - Journees sans coordonnees:']));
        foreach ($journeesSansCoord as $journee) {
            $writer->addRow(Row::fromValues(['  - ' . $journee['nom'] . ' (lieu: ' . ($journee['lieu'] ?: 'non renseigne') . ')']));
        }
    }

    $writer->close();

    // Vérifier que le fichier a été créé
    if (!file_exists($tempFile)) {
        exit("Erreur : Fichier non créé.");
    }

    // Envoyer le fichier
    header('Content-Type: application/vnd.oasis.opendocument.spreadsheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0');

    readfile($tempFile);
    unlink($tempFile);
    exit;

} catch (Exception $e) {
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
    exit("Erreur : " . $e->getMessage());
}
