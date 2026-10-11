<?php

namespace App\Forms;

use App\PublicResults\CompetitionRules;
use Doctrine\DBAL\Connection;

/**
 * Listes des champs « club » et « compétition » (FEATURE_FORMS.md § 2, FRM-11) : clubs ayant au moins une équipe ;
 * compétitions publiées, non terminées, de la saison active et de la suivante.
 */
final class FormOptions
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array{value: string, label: string}> */
    public function clubs(): array
    {
        return array_map(
            static fn (array $row): array => ['value' => (string) $row['Code'], 'label' => (string) $row['Libelle']],
            $this->connection->fetchAllAssociative(
                'SELECT c.Code, c.Libelle FROM kp_club c WHERE EXISTS (SELECT 1 FROM kp_equipe e WHERE e.Code_club = c.Code) ORDER BY c.Libelle',
            ),
        );
    }

    /** @return list<array{value: string, label: string}> valeur `{saison}/{code}` */
    public function competitions(): array
    {
        $active = $this->connection->fetchOne("SELECT Code FROM kp_saison WHERE Etat = 'A' ORDER BY Code DESC LIMIT 1");
        if ($active === false) {
            return [];
        }
        $seasons = [(string) $active, (string) ((int) $active + 1)];
        $rows = $this->connection->fetchAllAssociative(
            "SELECT c.Code, c.Code_saison, c.Libelle, c.Soustitre, c.Titre_actif FROM kp_competition c
            WHERE c.Code_saison IN (?, ?) AND c.Publication = 'O' AND c.Statut <> 'END'
            ORDER BY c.Code_saison, c.Code_niveau, c.GroupOrder, c.Code",
            $seasons,
        );

        return array_map(static fn (array $row): array => [
            'value' => $row['Code_saison'] . '/' . $row['Code'],
            'label' => sprintf('%s (%s)', CompetitionRules::displayTitle((string) $row['Titre_actif'], $row['Libelle'], $row['Soustitre']), $row['Code_saison']),
        ], $rows);
    }
}
