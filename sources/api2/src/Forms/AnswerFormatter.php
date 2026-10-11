<?php

namespace App\Forms;

use App\Cms\CmsRules;

/**
 * Texte affichable d'une réponse : libellé de l'option choisie, du club ou de la compétition plutôt que sa valeur
 * (e-mails, liste publique, export).
 */
final class AnswerFormatter
{
    /** @var array<string, array<string, string>>|null */
    private ?array $lists = null;

    public function __construct(private readonly FormOptions $options)
    {
    }

    /** @param array<string, mixed> $field */
    public function format(array $field, mixed $value, string $language): string
    {
        if ($value === null) {
            return '';
        }
        $labels = $this->labels($field, $language);
        $display = static fn (mixed $item): string => $labels[(string) $item] ?? (string) $item;

        return match (true) {
            is_bool($value) => $value ? '✓' : '',
            is_array($value) => implode(', ', array_map($display, $value)),
            default => $display($value),
        };
    }

    /** @return array<string, string> valeur → libellé */
    private function labels(array $field, string $language): array
    {
        $type = (string) $field['type'];
        if ($type === 'club' || $type === 'competition') {
            $this->lists ??= [
                'club' => array_column($this->options->clubs(), 'label', 'value'),
                'competition' => array_column($this->options->competitions(), 'label', 'value'),
            ];

            return $this->lists[$type];
        }
        $labels = [];
        foreach (json_decode((string) $field['options'], true) ?: [] as $option) {
            $labels[(string) $option['value']] = CmsRules::localized($option, 'label', $language) ?: (string) $option['value'];
        }

        return $labels;
    }
}
