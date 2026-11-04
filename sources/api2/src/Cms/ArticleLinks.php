<?php

namespace App\Cms;

use App\PublicResults\CompetitionRules;
use Doctrine\DBAL\Connection;

/**
 * Liens d'un article vers les pages de résultats (FEATURE_CMS.md § 3, NEWS-04) : compétition « {saison}/{code} »,
 * événement (id), club (code). Seules les cibles publiées sont résolues ; libellé et chemin du site public.
 */
final class ArticleLinks
{
    private const COMPETITION_REF = '~^(\d{4})/([A-Za-z0-9_-]{1,12})$~';
    private const EVENT_REF = '~^\d{1,9}$~';
    private const CLUB_REF = '~^[A-Za-z0-9_-]{1,6}$~';

    public function __construct(private readonly Connection $connection)
    {
    }

    public static function isValidRef(string $kind, string $ref): bool
    {
        return match ($kind) {
            'competition' => preg_match(self::COMPETITION_REF, $ref) === 1,
            'event' => preg_match(self::EVENT_REF, $ref) === 1,
            'club' => preg_match(self::CLUB_REF, $ref) === 1,
            default => false,
        };
    }

    /** @return array{kind: string, label: string, path: string}|null null si la cible n'existe pas ou n'est pas publiée */
    public function resolve(string $kind, string $ref): ?array
    {
        $label = match ($kind) {
            'competition' => $this->competitionLabel($ref),
            'event' => $this->eventLabel($ref),
            'club' => $this->clubLabel($ref),
            default => null,
        };
        if ($label === null) {
            return null;
        }
        $path = match ($kind) {
            'competition' => '/competitions/' . $ref,
            'event' => '/events/' . $ref,
            default => '/clubs?club=' . $ref,
        };

        return ['kind' => $kind, 'label' => $label, 'path' => $path];
    }

    private function competitionLabel(string $ref): ?string
    {
        if (preg_match(self::COMPETITION_REF, $ref, $match) !== 1) {
            return null;
        }
        $row = $this->connection->fetchAssociative(
            "SELECT Libelle, Soustitre, Titre_actif FROM kp_competition WHERE Code_saison = ? AND Code = ? AND Publication = 'O'",
            [$match[1], $match[2]],
        );

        return $row === false ? null : sprintf('%s %s', CompetitionRules::displayTitle((string) $row['Titre_actif'], $row['Libelle'], $row['Soustitre']), $match[1]);
    }

    private function eventLabel(string $ref): ?string
    {
        $label = $this->connection->fetchOne("SELECT Libelle FROM kp_evenement WHERE Id = ? AND Publication = 'O'", [(int) $ref]);

        return $label === false ? null : (string) $label;
    }

    private function clubLabel(string $ref): ?string
    {
        $label = $this->connection->fetchOne('SELECT Libelle FROM kp_club WHERE Code = ?', [$ref]);

        return $label === false ? null : (string) $label;
    }
}
