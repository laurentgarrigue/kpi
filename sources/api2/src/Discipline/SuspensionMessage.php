<?php

namespace App\Discipline;

/**
 * Texte des e-mails de suspension et d'annulation (FEATURE_CARD_SUSPENSIONS.md § 5). Pur, pour être testé sans mailer.
 */
final class SuspensionMessage
{
    private const SECTION_LABELS = [2 => 'Nationale'];

    /**
     * @param array<string, mixed> $context    CardSuspensionRepository::context()
     * @param list<SuspensionRule> $otherRules   règles déclenchées par le même carton, non retenues (S8)
     * @param int|null             $gamedayTotal total de la journée sportive d'une règle « au total » (S15)
     */
    public function __construct(
        private readonly int $matric,
        private readonly array $context,
        private readonly CardColor $color,
        private readonly SuspensionRule $rule,
        private readonly int $section,
        private readonly int $tally,
        private readonly int $matches,
        private readonly bool $appealJury,
        private readonly array $otherRules = [],
        private readonly ?string $reportLink = null,
        private readonly ?int $gamedayTotal = null,
    ) {
    }

    public function subject(): string
    {
        return sprintf('KPI — Suspension : %s (%d)', $this->context['player'], $this->matric);
    }

    public function cancellationSubject(): string
    {
        return sprintf('KPI — Suspension annulée : %s (%d)', $this->context['player'], $this->matric);
    }

    public function body(): string
    {
        $lines = [
            'Bonjour,',
            '',
            "KPI a relevé un cumul de cartons qui entraîne une suspension selon l'article 50 du règlement.",
            '',
            ...$this->facts(),
            sprintf('Règle : %s (%s)', $this->rule->labelFr, $this->tallyLabel()),
            sprintf('Suspension : %s', $this->suspensionLabel()),
        ];
        if ($this->otherRules !== []) {
            $lines[] = sprintf('Autres règles atteintes par ce carton : %s', implode(' ; ', array_map(static fn (SuspensionRule $rule): string => $rule->labelFr, $this->otherRules)));
        }
        if ($this->appealJury) {
            $lines[] = '';
            $lines[] = $this->reportLink === null
                ? "Rappel : le jury d'appel doit être réuni."
                : sprintf("Rappel : un rapport de jury d'appel a été ouvert en brouillon, à compléter et soumettre par le président du jury : %s", $this->reportLink);
        }

        return implode("\n", [...$lines, '', 'KPI constate le cumul ; la décision reste à la commission et au jury d\'appel.', '', 'Cordialement,', "L'équipe KPI"]);
    }

    public function cancellationBody(): string
    {
        return implode("\n", [
            'Bonjour,',
            '',
            'La suspension suivante, notifiée par KPI, est annulée : la feuille de match a été corrigée (match dévalidé, carton modifié ou supprimé).',
            '',
            ...$this->facts(),
            sprintf('Règle : %s', $this->rule->labelFr),
            sprintf('Suspension annulée : %s', $this->suspensionLabel()),
            '',
            'Si un autre cumul est atteint après la correction, il fait l\'objet d\'une nouvelle notification.',
            '',
            'Cordialement,',
            "L'équipe KPI",
        ]);
    }

    /** @return list<string> */
    private function facts(): array
    {
        $c = $this->context;

        return [
            sprintf('Joueur : %s, licence %d%s', $c['player'], $this->matric, empty($c['club']) ? '' : sprintf(', club %s', $c['club'])),
            sprintf('Compétition : %s (%s), section %s', $c['competition'] ?? '', $c['Code_competition'] ?? '', self::SECTION_LABELS[$this->section] ?? (string) $this->section),
            sprintf('Journée : %s%s', $c['gameday'] ?? '', empty($c['Lieu']) ? '' : ' — ' . $c['Lieu']),
            sprintf('Match : n° %s du %s, %s - %s', $c['Numero_ordre'] ?? $c['Id'] ?? '', $c['Date_match'] ?? '', $c['team_a'] ?? '?', $c['team_b'] ?? '?'),
            sprintf('Carton déclencheur : %s', $this->color->labelFr()),
        ];
    }

    private function tallyLabel(): string
    {
        $unit = match ($this->rule->scope) {
            TallyScope::Game => 'du match',
            TallyScope::Gameday => 'de la journée',
            TallyScope::Season => sprintf('de la saison, section %s', self::SECTION_LABELS[$this->section] ?? (string) $this->section),
        };
        $what = $this->rule->distinctGames ? 'match avec carton' : 'carton';

        return sprintf('%d%s %s %s', $this->tally, $this->tally === 1 ? 'er' : 'e', $what, $unit);
    }

    private function suspensionLabel(): string
    {
        $label = self::matchesLabel($this->matches);
        if ($this->gamedayTotal !== null && $this->gamedayTotal !== $this->matches) {
            $label = $this->matches === 0
                ? sprintf('aucun match de plus, %d matchs au total pour la journée', $this->gamedayTotal)
                : sprintf('%d de plus, soit %d matchs au total pour la journée', $this->matches, $this->gamedayTotal);
        }

        return $this->appealJury ? $label . " + jury d'appel" : $label;
    }

    private static function matchesLabel(int $matches): string
    {
        return $matches === 1 ? '1 match (le match suivant)' : sprintf('%d matchs', $matches);
    }
}
