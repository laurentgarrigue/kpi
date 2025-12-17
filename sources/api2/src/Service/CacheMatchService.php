<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

class CacheMatchService
{
    private string $cacheDir;

    public function __construct(
        private Connection $connection,
        string $projectDir
    ) {
        $this->cacheDir = $projectDir . '/var/cache/wsm';
        (new Filesystem())->mkdir($this->cacheDir);
    }

    /**
     * Créer le cache JSON complet d'un match
     */
    public function createMatchCache(int $matchId): array
    {
        $matchData = $this->getMatchData($matchId);

        if (empty($matchData)) {
            throw new \RuntimeException("Match {$matchId} not found");
        }

        $events = $this->getMatchEvents($matchId);
        $players = $this->getMatchPlayers($matchId);
        $timer = $this->getMatchTimer($matchId);

        $cache = [
            'match' => $matchData,
            'events' => $events,
            'players' => $players,
            'timer' => $timer,
            'last_update' => time(),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Sauvegarder en JSON
        $fileName = $this->getCacheFileName($matchId);
        file_put_contents($fileName, json_encode($cache, JSON_PRETTY_PRINT));

        return $cache;
    }

    /**
     * Lire le cache d'un match
     */
    public function readMatchCache(int $matchId): ?array
    {
        $fileName = $this->getCacheFileName($matchId);

        if (!file_exists($fileName)) {
            return null;
        }

        $content = file_get_contents($fileName);
        return json_decode($content, true);
    }

    /**
     * Supprimer le cache d'un match
     */
    public function deleteMatchCache(int $matchId): bool
    {
        $fileName = $this->getCacheFileName($matchId);

        if (file_exists($fileName)) {
            return unlink($fileName);
        }

        return false;
    }

    /**
     * Vérifier si le cache existe
     */
    public function cacheExists(int $matchId): bool
    {
        return file_exists($this->getCacheFileName($matchId));
    }

    /**
     * Obtenir le nom du fichier cache
     */
    private function getCacheFileName(int $matchId): string
    {
        return $this->cacheDir . '/match_' . $matchId . '.json';
    }

    /**
     * Récupérer les données du match
     */
    private function getMatchData(int $matchId): array
    {
        $sql = "SELECT m.*,
                       ta.Libelle as team_a_name, ta.Code_club as team_a_club, ta.logo as team_a_logo,
                       tb.Libelle as team_b_name, tb.Code_club as team_b_club, tb.logo as team_b_logo,
                       c.Libelle as competition_name
                FROM kp_match m
                LEFT JOIN kp_competition_equipe ta ON m.Id_equipeA = ta.Id
                LEFT JOIN kp_competition_equipe tb ON m.Id_equipeB = tb.Id
                LEFT JOIN kp_journee j ON m.Id_journee = j.Id
                LEFT JOIN kp_competition c ON j.Code_competition = c.Code
                WHERE m.Id = ?";

        return $this->connection->fetchAssociative($sql, [$matchId]) ?: [];
    }

    /**
     * Récupérer les événements du match
     */
    private function getMatchEvents(int $matchId): array
    {
        $sql = "SELECT md.*,
                       l.Nom as player_name, l.Prenom as player_firstname
                FROM kp_match_detail md
                LEFT JOIN kp_licence l ON md.Competiteur = l.Matric
                WHERE md.Id_match = ?
                ORDER BY md.Periode, md.Temps";

        return $this->connection->fetchAllAssociative($sql, [$matchId]);
    }

    /**
     * Récupérer les joueurs du match
     */
    private function getMatchPlayers(int $matchId): array
    {
        $sql = "SELECT mj.*,
                       l.Nom as player_name, l.Prenom as player_firstname, l.Sexe as gender
                FROM kp_match_joueur mj
                LEFT JOIN kp_licence l ON mj.Matric = l.Matric
                WHERE mj.Id_match = ?
                ORDER BY mj.Equipe, mj.Numero";

        return $this->connection->fetchAllAssociative($sql, [$matchId]);
    }

    /**
     * Récupérer les données du chronomètre
     */
    private function getMatchTimer(int $matchId): ?array
    {
        $sql = "SELECT * FROM kp_chrono WHERE IdMatch = ?";
        $result = $this->connection->fetchAssociative($sql, [$matchId]);

        return $result ?: null;
    }

    /**
     * Créer un cache pour plusieurs matchs (batch)
     */
    public function createBatchCache(array $matchIds): array
    {
        $results = [];

        foreach ($matchIds as $matchId) {
            try {
                $results[$matchId] = [
                    'success' => true,
                    'cache' => $this->createMatchCache($matchId)
                ];
            } catch (\Exception $e) {
                $results[$matchId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Nettoyer les vieux caches (> 7 jours)
     */
    public function cleanOldCaches(int $days = 7): int
    {
        $count = 0;
        $cutoffTime = time() - ($days * 24 * 60 * 60);

        $files = glob($this->cacheDir . '/match_*.json');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
