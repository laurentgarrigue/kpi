<?php

namespace App\Designation;

/**
 * Périmètre d'un mandat généré, mis au format des colonnes de kp_user_mandat (FEATURE_DESIGNATIONS.md D6, D10) :
 * saisons et compétitions en `|A|B|`, journées et clubs séparés par des virgules. Dans kp_user_mandat un filtre vide
 * veut dire « sans restriction » : sans compétition, il n'y a pas de mandat ({@see isEmpty()}).
 *
 * - Une Désignation sur une compétition donne la compétition entière (les phases ajoutées ensuite sont couvertes).
 * - Une Désignation sur un événement ou une journée donne ces journées et leurs compétitions ; si le mandat mêle les
 *   deux, le filtre des journées liste aussi toutes celles des compétitions entières, sinon elles seraient exclues.
 */
final class MandatePerimeter
{
    /**
     * @param list<string>        $competitions           compétitions désignées en entier (ou des équipes, du niveau)
     * @param array<int, string>  $gamedays               journée => compétition, pour les Désignations sur événement ou journée
     * @param array<int, string>  $gamedaysOfCompetitions journée => compétition, toutes celles de $competitions
     * @param list<string>        $clubs                  clubs des équipes (responsable d'équipe)
     */
    public function __construct(
        private readonly string $season,
        private readonly array $competitions = [],
        private readonly array $gamedays = [],
        private readonly array $gamedaysOfCompetitions = [],
        private readonly array $clubs = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->allCompetitions() === [];
    }

    /** @return array{filtre_saison: string, filtre_competition: string, filtre_journee: string, limitation_equipe_club: string|null, id_evenement: string} */
    public function columns(): array
    {
        $gamedayIds = [];
        if ($this->gamedays !== []) {
            $gamedayIds = array_keys($this->gamedays + $this->gamedaysOfCompetitions);
            sort($gamedayIds);
        }
        $clubs = array_values(array_unique($this->clubs));
        sort($clubs);

        return [
            'filtre_saison' => self::pipe([$this->season]),
            'filtre_competition' => self::pipe($this->allCompetitions()),
            'filtre_journee' => implode(',', $gamedayIds),
            'limitation_equipe_club' => $clubs === [] ? null : implode(',', $clubs),
            'id_evenement' => '',
        ];
    }

    /** @return list<string> */
    private function allCompetitions(): array
    {
        $codes = array_values(array_unique([...$this->competitions, ...array_values($this->gamedays)]));
        sort($codes);

        return $codes;
    }

    /** @param list<string> $values */
    private static function pipe(array $values): string
    {
        return $values === [] ? '' : '|' . implode('|', $values) . '|';
    }
}
