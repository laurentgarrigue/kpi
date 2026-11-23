<?php

namespace App\Report;

/**
 * Ce qu'il faut savoir du périmètre d'un rapport : saison, journées couvertes (pour les officiels D15 et les
 * destinataires D28), compétitions, date de fin (délai D18) et données KPI affichées en tête (D12).
 */
final class ReportPerimeter
{
    /**
     * @param array<int, string>   $gamedays    journée => compétition
     * @param array<int, list<int>> $gamedayEvents journée => événements
     * @param list<int>            $matchIds    matchs du périmètre (arbitres désignés, D15)
     * @param array<string, mixed> $header      données KPI pré-remplies (RAP-06 : figées à la soumission)
     */
    public function __construct(
        public readonly ReportScope $scope,
        public readonly string $scopeId,
        public readonly string $season,
        public readonly array $gamedays,
        public readonly array $gamedayEvents,
        public readonly array $matchIds,
        public readonly ?string $endDate,
        public readonly string $label,
        public readonly array $header,
    ) {
    }

    /** @return list<string> */
    public function competitions(): array
    {
        $codes = array_values(array_unique(array_values($this->gamedays)));
        if ($this->scope === ReportScope::Competition && !in_array($this->scopeId, $codes, true)) {
            $codes[] = $this->scopeId;
        }

        return $codes;
    }
}
