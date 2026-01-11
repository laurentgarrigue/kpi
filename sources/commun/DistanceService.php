<?php
/**
 * Service de calcul de distances routières via OpenRouteService API
 *
 * Documentation: https://openrouteservice.org/dev/#/api-docs
 * Inscription gratuite: https://openrouteservice.org/dev/#/signup
 * Limite gratuite: 2000 requêtes/jour
 *
 * @version 1.0.0
 */

class DistanceService
{
    private $apiKey;
    private $apiUrl = 'https://api.openrouteservice.org/v2/directions/driving-car';
    private $matrixUrl = 'https://api.openrouteservice.org/v2/matrix/driving-car';
    private $pdo;

    /**
     * Constructeur
     * @param PDO $pdo Connexion PDO à la base de données
     * @param string|null $apiKey Clé API OpenRouteService (optionnel, utilise ORS_API_KEY si non fourni)
     */
    public function __construct($pdo, $apiKey = null)
    {
        $this->pdo = $pdo;
        $this->apiKey = $apiKey ?? (defined('ORS_API_KEY') ? ORS_API_KEY : '');
    }

    /**
     * Vérifie si la clé API est configurée
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->apiKey);
    }

    /**
     * Calcule la distance routière entre deux points
     *
     * @param float $latFrom Latitude origine
     * @param float $lonFrom Longitude origine
     * @param float $latTo Latitude destination
     * @param float $lonTo Longitude destination
     * @return array ['distance_km' => float, 'duration_min' => int, 'source' => 'api'|'haversine']
     */
    public function getDistance($latFrom, $lonFrom, $latTo, $lonTo)
    {
        // Validation des coordonnées
        if (!$this->validateCoordinates($latFrom, $lonFrom) ||
            !$this->validateCoordinates($latTo, $lonTo)) {
            return ['distance_km' => null, 'duration_min' => null, 'source' => 'invalid', 'error' => 'Coordonnées invalides'];
        }

        // Si même position, distance = 0
        if (abs($latFrom - $latTo) < 0.0001 && abs($lonFrom - $lonTo) < 0.0001) {
            return ['distance_km' => 0, 'duration_min' => 0, 'source' => 'same_location'];
        }

        // Essayer l'API OpenRouteService
        if ($this->isConfigured()) {
            $result = $this->callOrsApi($latFrom, $lonFrom, $latTo, $lonTo);
            if ($result !== null) {
                return $result;
            }
        }

        // Fallback: calcul Haversine (vol d'oiseau) avec facteur de correction routier
        return $this->calculateHaversine($latFrom, $lonFrom, $latTo, $lonTo);
    }

    /**
     * Calcule une matrice de distances entre plusieurs points
     * Optimisé pour réduire le nombre d'appels API
     *
     * @param array $locations Array de ['lat' => float, 'lon' => float, 'id' => string]
     * @return array Matrice [id_from][id_to] => ['distance_km' => float, 'duration_min' => int]
     */
    public function getDistanceMatrix($locations)
    {
        $matrix = [];
        $count = count($locations);

        // Initialiser la matrice
        foreach ($locations as $loc) {
            $matrix[$loc['id']] = [];
        }

        // Si API configurée et moins de 50 points, utiliser l'API Matrix
        if ($this->isConfigured() && $count <= 50) {
            $result = $this->callOrsMatrixApi($locations);
            if ($result !== null) {
                return $result;
            }
        }

        // Sinon, calcul point à point (avec Haversine si pas d'API)
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                $fromId = $locations[$i]['id'];
                $toId = $locations[$j]['id'];

                if ($i === $j) {
                    $matrix[$fromId][$toId] = ['distance_km' => 0, 'duration_min' => 0];
                } elseif (isset($matrix[$toId][$fromId])) {
                    // Symétrie : A→B = B→A (approximation acceptable pour ce cas d'usage)
                    $matrix[$fromId][$toId] = $matrix[$toId][$fromId];
                } else {
                    $result = $this->getDistance(
                        $locations[$i]['lat'], $locations[$i]['lon'],
                        $locations[$j]['lat'], $locations[$j]['lon']
                    );
                    $matrix[$fromId][$toId] = [
                        'distance_km' => $result['distance_km'],
                        'duration_min' => $result['duration_min']
                    ];
                }
            }
        }

        return $matrix;
    }

    /**
     * Appel API OpenRouteService pour une route simple
     */
    private function callOrsApi($latFrom, $lonFrom, $latTo, $lonTo)
    {
        $url = $this->apiUrl . '?start=' . $lonFrom . ',' . $latFrom . '&end=' . $lonTo . ',' . $latTo;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->apiKey,
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return null;
        }

        $data = json_decode($response, true);

        if (isset($data['features'][0]['properties']['summary'])) {
            $summary = $data['features'][0]['properties']['summary'];
            return [
                'distance_km' => round($summary['distance'] / 1000), // mètres -> km, arrondi
                'duration_min' => round($summary['duration'] / 60),  // secondes -> minutes
                'source' => 'api'
            ];
        }

        return null;
    }

    /**
     * Appel API OpenRouteService Matrix pour plusieurs points
     */
    private function callOrsMatrixApi($locations)
    {
        // Préparer les coordonnées au format [lon, lat]
        $coordinates = [];
        foreach ($locations as $loc) {
            $coordinates[] = [$loc['lon'], $loc['lat']];
        }

        $postData = json_encode([
            'locations' => $coordinates,
            'metrics' => ['distance', 'duration'],
            'units' => 'km'
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->matrixUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return null;
        }

        $data = json_decode($response, true);

        if (!isset($data['distances']) || !isset($data['durations'])) {
            return null;
        }

        $matrix = [];
        $count = count($locations);

        for ($i = 0; $i < $count; $i++) {
            $fromId = $locations[$i]['id'];
            $matrix[$fromId] = [];

            for ($j = 0; $j < $count; $j++) {
                $toId = $locations[$j]['id'];
                $matrix[$fromId][$toId] = [
                    'distance_km' => round($data['distances'][$i][$j]), // Arrondi à l'entier
                    'duration_min' => round($data['durations'][$i][$j] / 60)
                ];
            }
        }

        return $matrix;
    }

    /**
     * Calcul de distance Haversine (vol d'oiseau) avec facteur de correction routier
     * Facteur 1.3 = approximation route vs vol d'oiseau en France
     */
    private function calculateHaversine($latFrom, $lonFrom, $latTo, $lonTo)
    {
        $earthRadius = 6371; // km

        $latFromRad = deg2rad($latFrom);
        $latToRad = deg2rad($latTo);
        $deltaLat = deg2rad($latTo - $latFrom);
        $deltaLon = deg2rad($lonTo - $lonFrom);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($latFromRad) * cos($latToRad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distanceVolOiseau = $earthRadius * $c;
        $distanceRoutiere = $distanceVolOiseau * 1.3; // Facteur de correction routier

        // Estimation durée: 70 km/h moyenne
        $duration = ($distanceRoutiere / 70) * 60;

        return [
            'distance_km' => round($distanceRoutiere),
            'duration_min' => round($duration),
            'source' => 'haversine'
        ];
    }

    /**
     * Valide des coordonnées GPS
     */
    private function validateCoordinates($lat, $lon)
    {
        return is_numeric($lat) && is_numeric($lon) &&
               $lat >= -90 && $lat <= 90 &&
               $lon >= -180 && $lon <= 180;
    }

    /**
     * Parse une chaîne de coordonnées au format "lat,lon"
     * @param string $coordString
     * @return array|null ['lat' => float, 'lon' => float] ou null si invalide
     */
    public static function parseCoordString($coordString)
    {
        if (empty($coordString)) {
            return null;
        }

        $parts = explode(',', $coordString);
        if (count($parts) !== 2) {
            return null;
        }

        $lat = floatval(trim($parts[0]));
        $lon = floatval(trim($parts[1]));

        if ($lat == 0 && $lon == 0) {
            return null;
        }

        return ['lat' => $lat, 'lon' => $lon];
    }

    /**
     * Recherche les coordonnées d'une ville dans la table villes_france_free
     *
     * @param string $villeName Nom de la ville
     * @param string|null $departement Code département (optionnel, améliore la précision)
     * @return array|null ['lat' => float, 'lon' => float, 'ville' => string] ou null
     */
    public function findCityCoordinates($villeName, $departement = null)
    {
        if (empty($villeName)) {
            return null;
        }

        // Normaliser le nom de ville
        $villeNormalized = $this->normalizeVilleName($villeName);

        // Recherche exacte d'abord
        $sql = "SELECT ville_nom_reel, ville_latitude_deg, ville_longitude_deg, ville_departement
                FROM villes_france_free
                WHERE ville_nom_simple = ? ";
        $params = [$villeNormalized];

        if ($departement) {
            $sql .= "AND ville_departement = ? ";
            $params[] = $departement;
        }

        $sql .= "ORDER BY ville_population_2012 DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return [
                'lat' => floatval($row['ville_latitude_deg']),
                'lon' => floatval($row['ville_longitude_deg']),
                'ville' => $row['ville_nom_reel'],
                'departement' => $row['ville_departement']
            ];
        }

        // Recherche approximative (LIKE)
        $sql = "SELECT ville_nom_reel, ville_latitude_deg, ville_longitude_deg, ville_departement
                FROM villes_france_free
                WHERE ville_nom_simple LIKE ? ";
        $params = [$villeNormalized . '%'];

        if ($departement) {
            $sql .= "AND ville_departement = ? ";
            $params[] = $departement;
        }

        $sql .= "ORDER BY ville_population_2012 DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return [
                'lat' => floatval($row['ville_latitude_deg']),
                'lon' => floatval($row['ville_longitude_deg']),
                'ville' => $row['ville_nom_reel'],
                'departement' => $row['ville_departement']
            ];
        }

        return null;
    }

    /**
     * Normalise un nom de ville pour la recherche
     * Supprime accents, tirets, apostrophes, met en majuscules
     */
    private function normalizeVilleName($name)
    {
        // Supprimer les accents
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        // Mettre en majuscules
        $name = strtoupper($name);
        // Supprimer caractères spéciaux sauf espaces
        $name = preg_replace('/[^A-Z0-9 ]/', '', $name);
        // Supprimer espaces multiples
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }

    /**
     * Récupère les coordonnées d'un club depuis la base de données
     *
     * @param string $codeClub Code du club
     * @return array|null ['lat' => float, 'lon' => float, 'libelle' => string] ou null
     */
    public function getClubCoordinates($codeClub)
    {
        $sql = "SELECT c.Libelle, c.Coord, c.Postal, c.Code_comite_dep,
                       cd.Code_comite_reg
                FROM kp_club c
                LEFT JOIN kp_cd cd ON c.Code_comite_dep = cd.Code
                WHERE c.Code = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$codeClub]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Exclure les clubs internationaux (code_comite_reg = 98)
        if ($row['Code_comite_reg'] == '98') {
            return null;
        }

        // Si coordonnées déjà présentes
        if (!empty($row['Coord'])) {
            $coords = self::parseCoordString($row['Coord']);
            if ($coords) {
                $coords['libelle'] = $row['Libelle'];
                $coords['code'] = $codeClub;
                return $coords;
            }
        }

        // Sinon, essayer de trouver via l'adresse postale
        if (!empty($row['Postal'])) {
            // Extraire la ville de l'adresse (format: "... CODE_POSTAL VILLE")
            if (preg_match('/(\d{5})\s+(.+)$/i', $row['Postal'], $matches)) {
                $departement = substr($matches[1], 0, 2);
                $ville = $matches[2];

                $coords = $this->findCityCoordinates($ville, $departement);
                if ($coords) {
                    $coords['libelle'] = $row['Libelle'];
                    $coords['code'] = $codeClub;
                    return $coords;
                }
            }
        }

        return ['lat' => null, 'lon' => null, 'libelle' => $row['Libelle'], 'code' => $codeClub];
    }
}

/*
 * ============================================================================
 * DOCUMENTATION POUR IMPLEMENTATION FUTURE DU CACHE
 * ============================================================================
 *
 * Pour éviter les appels API répétés et optimiser les performances,
 * un système de cache peut être ajouté ultérieurement.
 *
 * 1. CRÉER LA TABLE DE CACHE
 * --------------------------
 *
 * CREATE TABLE kp_distance_cache (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     coord_origine VARCHAR(50) NOT NULL COMMENT 'Format: lat,lon',
 *     coord_destination VARCHAR(50) NOT NULL COMMENT 'Format: lat,lon',
 *     distance_km DECIMAL(10,2) NOT NULL,
 *     duree_min INT NOT NULL,
 *     source VARCHAR(20) NOT NULL COMMENT 'api ou haversine',
 *     date_calcul DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     INDEX idx_coords (coord_origine, coord_destination),
 *     UNIQUE KEY uk_trajet (coord_origine, coord_destination)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * 2. MODIFIER LA MÉTHODE getDistance()
 * ------------------------------------
 *
 * Ajouter avant l'appel API:
 *
 *     // Vérifier le cache
 *     $cacheKey = $this->getCacheKey($latFrom, $lonFrom, $latTo, $lonTo);
 *     $cached = $this->getFromCache($cacheKey);
 *     if ($cached !== null) {
 *         return $cached;
 *     }
 *
 * Ajouter après un résultat API réussi:
 *
 *     // Stocker en cache
 *     $this->saveToCache($cacheKey, $result);
 *
 * 3. MÉTHODES DE CACHE À AJOUTER
 * ------------------------------
 *
 * private function getCacheKey($latFrom, $lonFrom, $latTo, $lonTo) {
 *     // Arrondir pour grouper les coordonnées proches (précision ~100m)
 *     return sprintf("%.3f,%.3f", $latFrom, $lonFrom) . '|' .
 *            sprintf("%.3f,%.3f", $latTo, $lonTo);
 * }
 *
 * private function getFromCache($key) {
 *     list($from, $to) = explode('|', $key);
 *     $sql = "SELECT distance_km, duree_min, source FROM kp_distance_cache
 *             WHERE coord_origine = ? AND coord_destination = ?
 *             AND date_calcul > DATE_SUB(NOW(), INTERVAL 30 DAY)";
 *     $stmt = $this->pdo->prepare($sql);
 *     $stmt->execute([$from, $to]);
 *     $row = $stmt->fetch();
 *     if ($row) {
 *         return [
 *             'distance_km' => (int)$row['distance_km'],
 *             'duration_min' => (int)$row['duree_min'],
 *             'source' => 'cache'
 *         ];
 *     }
 *     return null;
 * }
 *
 * private function saveToCache($key, $result) {
 *     list($from, $to) = explode('|', $key);
 *     $sql = "INSERT INTO kp_distance_cache (coord_origine, coord_destination, distance_km, duree_min, source)
 *             VALUES (?, ?, ?, ?, ?)
 *             ON DUPLICATE KEY UPDATE distance_km = VALUES(distance_km),
 *                                     duree_min = VALUES(duree_min),
 *                                     date_calcul = NOW()";
 *     $stmt = $this->pdo->prepare($sql);
 *     $stmt->execute([$from, $to, $result['distance_km'], $result['duration_min'], $result['source']]);
 * }
 *
 * 4. NETTOYAGE DU CACHE
 * ---------------------
 *
 * Ajouter un cron job pour nettoyer les entrées anciennes:
 *
 * DELETE FROM kp_distance_cache WHERE date_calcul < DATE_SUB(NOW(), INTERVAL 90 DAY);
 *
 */
