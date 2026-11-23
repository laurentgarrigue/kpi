<?php

namespace App\Report;

/**
 * Un champ d'un type de rapport. `sum` est calculé côté serveur (somme de champs `score`), jamais saisi.
 */
final class ReportField
{
    public const TEXT = 'text';
    public const TEXTAREA = 'textarea';
    public const NUMBER = 'number';
    public const DATE = 'date';
    public const TIME = 'time';
    public const CHECKBOX = 'checkbox';
    public const SELECT = 'select';
    public const SCORE = 'score';
    public const SUM = 'sum';
    /** Lignes répétées ; `rowsFrom` = lignes pré-remplies depuis les données KPI (`officials`, `referees`). */
    public const TABLE = 'table';
    public const MAX_TABLE_ROWS = 200;
    public const MAX_TEXT_LENGTH = 255;
    public const MAX_TEXTAREA_LENGTH = 10000;

    /**
     * @param list<string> $options valeurs possibles d'un `select`
     * @param list<string> $sumOf   champs additionnés par un `sum`
     * @param list<ReportField> $columns colonnes d'un `table`
     * @param string|null $rowsFrom donnée KPI qui pré-remplit les lignes d'un `table`
     * @param bool $readonly colonne pré-remplie depuis KPI, non modifiable
     * @param string|null $prefill donnée KPI (chemin « matches.planned ») qui pré-remplit le champ, corrigible ensuite
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $labelFr,
        public readonly string $labelEn,
        public readonly bool $required = false,
        public readonly array $options = [],
        public readonly ?int $max = null,
        public readonly array $sumOf = [],
        public readonly array $columns = [],
        public readonly ?string $rowsFrom = null,
        public readonly bool $readonly = false,
        public readonly ?string $prefill = null,
    ) {
    }

    public function isComputed(): bool
    {
        return $this->type === self::SUM;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'type' => $this->type,
            'label' => ['fr' => $this->labelFr, 'en' => $this->labelEn],
            'required' => $this->required,
            'options' => $this->options,
            'max' => $this->max,
            'sumOf' => $this->sumOf,
            'columns' => array_map(static fn (ReportField $column): array => $column->toArray(), $this->columns),
            'rowsFrom' => $this->rowsFrom,
            'readonly' => $this->readonly ?: null,
            'prefill' => $this->prefill,
        ], static fn (mixed $value): bool => $value !== [] && $value !== null && $value !== false);
    }
}
