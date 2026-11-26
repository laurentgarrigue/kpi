<?php

namespace App\Eligibility;

use App\RuleCatalog\RuleCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescription;
use App\RuleCatalog\RuleSection;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/** Values of PlayerEligibilityRules for the `rules-catalog` PDF (FEATURE_RULES_CATALOG_PDF.md § 4.1). */
#[AsTaggedItem(priority: 60)]
final class PlayerEligibilityRulesDescriber implements RuleCatalogDescriber
{
    public const CODE = 'player-eligibility';

    /** Colour of each paddle code of PlayerEligibilityRules::PAGAIE_RANKS. */
    public const PAGAIE_COLOURS = [
        'PAGB' => 'Blanche', 'PAGJ' => 'Jaune', 'PAGV' => 'Verte', 'PAGBL' => 'Bleue', 'PAGR' => 'Rouge', 'PAGN' => 'Noire',
    ];

    /** Statuses of kp_competition_equipe_joueur.Capitaine. */
    public const STATUSES = ['-' => 'Joueur', 'C' => 'Capitaine', 'E' => 'Encadrement', 'A' => 'Arbitre (non joueur)', 'X' => 'Inactif'];

    private const LEVELS = [PlayerEligibilityRules::LEVEL_NATIONAL => 'National', PlayerEligibilityRules::LEVEL_REGIONAL => 'Régional'];

    public function code(): string
    {
        return self::CODE;
    }

    public function describe(string $season): RuleCatalogDescription
    {
        return new RuleCatalogDescription(
            title: 'Joueur en règle',
            version: null,
            sources: ['sources/api2/src/Eligibility/PlayerEligibilityRules.php', 'DOC/developer/reference/PLAYER_ELIGIBILITY_RULES.md'],
            sections: [
                RuleSection::table('Critères par niveau de compétition', ['criterion' => 'Critère', ...self::LEVELS], $this->criteria($season)),
                RuleSection::table('Échelle des pagaies (eau calme)', ['rank' => 'Rang', 'code' => 'Code', 'colour' => 'Couleur'], array_map(
                    static fn (string $code, int $rank): array => ['rank' => $rank, 'code' => $code, 'colour' => self::PAGAIE_COLOURS[$code]],
                    array_keys(PlayerEligibilityRules::PAGAIE_RANKS),
                    PlayerEligibilityRules::PAGAIE_RANKS,
                )),
                RuleSection::pairs('Surclassement', [
                    'Compétitions qui l\'exigent' => RuleSection::list(PlayerEligibilityRules::SURCLASSEMENT_COMPETITIONS),
                    'Catégories qui n\'en ont jamais besoin' => RuleSection::list(PlayerEligibilityRules::SURCLASSEMENT_EXEMPT_CATEGORIES),
                ]),
                RuleSection::pairs('Statuts et forçage d\'un joueur non en règle', [
                    'Statuts soumis aux règles' => self::statuses(PlayerEligibilityRules::PLAYING_STATUSES),
                    'Profils autorisés à forcer un contrôle bloquant' => sprintf('profils 1 à %d', PlayerEligibilityRules::FORCE_MAX_PROFILE),
                    'Statuts possibles en forçant un ajout' => self::statuses(PlayerEligibilityRules::FORCE_ADD_STATUSES),
                    'Statuts possibles en forçant un changement de statut' => self::statuses(PlayerEligibilityRules::FORCE_STATUS_CHANGE_STATUSES),
                    'Statut donné à la copie vers une compétition bloquante' => self::statuses([PlayerEligibilityRules::COPY_INELIGIBLE_STATUS]),
                ]),
                RuleSection::lines('Niveau d\'une compétition', [
                    'National : code commençant par « N » (championnats) ou « CF » (Coupe de France).',
                    'Régional : niveau de la compétition « REG ».',
                    'Autres compétitions : aucun contrôle.',
                ]),
            ],
        );
    }

    /** @return list<array<string, string>> */
    private function criteria(string $season): array
    {
        $rows = [
            'enforcement' => 'Contrôle',
            'licenceSeason' => 'Licence renouvelée pour la saison',
            'licenceTypes' => 'Types de licence acceptés',
            'certificateCK' => 'Certificat médical compétition (CK)',
            'minPagaieECA' => 'Pagaie minimale',
            'pagaieEffect' => 'Pagaie insuffisante',
            'surclassement' => 'Surclassement exigé',
        ];
        $table = [];
        foreach ($rows as $criterion => $label) {
            $row = ['criterion' => $label];
            foreach (array_keys(self::LEVELS) as $level) {
                $row[$level] = $this->value($criterion, $level, $season);
            }
            $table[] = $row;
        }

        return $table;
    }

    private function value(string $criterion, string $level, string $season): string
    {
        $rule = PlayerEligibilityRules::RULES[$level];
        $blocking = $rule['enforcement'] === PlayerEligibilityRules::ENFORCEMENT_BLOCK;

        return match ($criterion) {
            'enforcement' => $blocking ? 'bloquant (joueur refusé)' : 'alerte seulement',
            'licenceTypes' => RuleSection::list($rule['licenceTypes'], 'tous'),
            'minPagaieECA' => $rule['minPagaieECA'] === null ? '—' : sprintf('%s (%s)', self::PAGAIE_COLOURS[$rule['minPagaieECA']], $rule['minPagaieECA']),
            'pagaieEffect' => match (true) {
                in_array(PlayerEligibilityRules::ERROR_PAGAIE, PlayerEligibilityRules::advisoryErrors($level, $season), true) => 'signalée sans bloquer',
                $blocking => 'bloquante',
                default => 'alerte',
            },
            default => RuleSection::yesNo((bool) $rule[$criterion]),
        };
    }

    /** @param list<string> $codes */
    private static function statuses(array $codes): string
    {
        return implode(', ', array_map(static fn (string $code): string => sprintf('%s (%s)', self::STATUSES[$code], $code), $codes));
    }
}
