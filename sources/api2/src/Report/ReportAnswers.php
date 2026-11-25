<?php

namespace App\Report;

/**
 * Validation et normalisation des réponses d'un rapport selon sa définition (FEATURE_DESIGNATIONS.md D13). Un
 * brouillon peut être incomplet ; à la soumission, les champs obligatoires sont exigés. Les champs calculés
 * (`sum`) sont recalculés ici et ne se saisissent jamais.
 */
final class ReportAnswers
{
    public const ERROR_REQUIRED = 'required';
    public const ERROR_INVALID = 'invalid';
    public const ERROR_UNKNOWN = 'unknown';

    /**
     * @param array<string, mixed> $answers
     *
     * @return array{answers: array<string, mixed>, errors: array<string, string>}
     */
    public static function normalize(ReportTypeDefinition $definition, array $answers, bool $forSubmit): array
    {
        $fields = $definition->fields();
        $errors = [];
        $clean = [];
        foreach ($answers as $key => $value) {
            if (!isset($fields[$key])) {
                $errors[$key] = self::ERROR_UNKNOWN;
            }
        }
        foreach ($fields as $key => $field) {
            if ($field->isComputed()) {
                continue;
            }
            $value = $answers[$key] ?? null;
            if ($value === null || $value === '') {
                if ($forSubmit && $field->required) {
                    $errors[$key] = self::ERROR_REQUIRED;
                }
                continue;
            }
            $normalized = self::value($field, $value);
            if ($normalized === null) {
                $errors[$key] = self::ERROR_INVALID;
                continue;
            }
            $clean[$key] = $normalized;
        }
        foreach ($fields as $key => $field) {
            if ($field->isComputed()) {
                $clean[$key] = array_sum(array_map(static fn (string $of): int => (int) ($clean[$of] ?? 0), $field->sumOf));
            }
        }

        return ['answers' => $clean, 'errors' => $errors];
    }

    /**
     * Lignes d'un tableau : chaque cellule est validée selon sa colonne ; une colonne en lecture seule (pré-remplie
     * depuis KPI) est gardée telle quelle si c'est un texte ou un nombre.
     *
     * @return list<array<string, mixed>>|null
     */
    private static function rows(ReportField $field, mixed $value): ?array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > ReportField::MAX_TABLE_ROWS) {
            return null;
        }
        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                return null;
            }
            $clean = [];
            foreach ($field->columns as $column) {
                $cell = $row[$column->key] ?? null;
                if ($cell === null || $cell === '') {
                    continue;
                }
                $cell = $column->readonly ? (is_string($cell) || is_int($cell) ? $cell : null) : self::value($column, $cell);
                if ($cell === null) {
                    return null;
                }
                $clean[$column->key] = $cell;
            }
            if ($clean !== []) {
                $rows[] = $clean;
            }
        }

        return $rows;
    }

    /** Date réelle au format AAAA-MM-JJ : PHP accepte « 2998-02-30 » en le décalant au 2 mars, on le refuse. */
    private static function isCalendarDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function value(ReportField $field, mixed $value): mixed
    {
        return match ($field->type) {
            ReportField::TEXT => is_string($value) && mb_strlen($value) <= ReportField::MAX_TEXT_LENGTH ? trim($value) : null,
            ReportField::TEXTAREA => is_string($value) && mb_strlen($value) <= ReportField::MAX_TEXTAREA_LENGTH ? trim($value) : null,
            ReportField::NUMBER => is_int($value) || is_float($value) ? $value : null,
            ReportField::DATE => is_string($value) && self::isCalendarDate($value) ? $value : null,
            ReportField::TIME => is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : null,
            ReportField::CHECKBOX => is_bool($value) ? $value : null,
            ReportField::SELECT => is_string($value) && in_array($value, $field->options, true) ? $value : null,
            ReportField::SCORE => is_int($value) && $value >= 0 && $value <= (int) $field->max ? $value : null,
            ReportField::TABLE => self::rows($field, $value),
            default => null,
        };
    }
}
