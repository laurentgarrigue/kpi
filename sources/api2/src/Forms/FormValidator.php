<?php

namespace App\Forms;

use App\Cms\ValidationErrors;

/**
 * Validation serveur des réponses d'un formulaire (FRM-02, FRM-11) : obligatoires, types, bornes, valeurs de liste.
 * Renvoie les réponses normalisées ; les erreurs portent un code par champ.
 */
final class FormValidator
{
    private const TEXT_MAX = 255;
    private const TEXTAREA_MAX = 5000;
    private const PHONE_PATTERN = '/^\+?[0-9 ().-]{6,20}$/';

    public function __construct(private readonly FormOptions $options)
    {
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed>       $input
     *
     * @return array<string, mixed> réponses normalisées, par clé de champ
     */
    public function validate(array $fields, array $input, ValidationErrors $errors): array
    {
        $answers = [];
        $lists = [];
        foreach ($fields as $field) {
            $key = (string) $field['field_key'];
            $type = (string) $field['type'];
            $raw = $input[$key] ?? null;
            if (self::isEmpty($raw)) {
                if ((int) $field['required'] === 1) {
                    $errors->add($key, 'required');
                }
                continue;
            }
            $allowed = match ($type) {
                'club' => $lists['club'] ??= array_column($this->options->clubs(), 'value'),
                'competition' => $lists['competition'] ??= array_column($this->options->competitions(), 'value'),
                default => array_column(json_decode((string) $field['options'], true) ?: [], 'value'),
            };
            $value = $this->value($type, $raw, $field, $allowed);
            if (is_string($value) && str_starts_with($value, '!')) {
                $errors->add($key, substr($value, 1));
                continue;
            }
            $answers[$key] = $value;
        }

        return $answers;
    }

    /**
     * Valeur normalisée, ou code d'erreur préfixé par « ! ».
     *
     * @param array<string, mixed> $field
     * @param list<mixed>          $allowed
     */
    private function value(string $type, mixed $raw, array $field, array $allowed): mixed
    {
        if ($type === 'checkbox') {
            $values = array_values(array_unique(array_map('strval', is_array($raw) ? $raw : [$raw])));
            if ($allowed === []) {
                return in_array($values[0] ?? '', ['1', 'on', 'true', 'yes'], true) ? true : '!invalid';
            }

            return array_diff($values, $allowed) === [] ? $values : '!invalid';
        }
        if (!is_scalar($raw)) {
            return '!invalid';
        }
        $text = trim((string) $raw);

        return match ($type) {
            'text' => mb_strlen($text) <= self::TEXT_MAX ? $text : '!too_long',
            'textarea' => mb_strlen($text) <= self::TEXTAREA_MAX ? $text : '!too_long',
            'email' => filter_var($text, FILTER_VALIDATE_EMAIL) !== false && strlen($text) <= self::TEXT_MAX ? $text : '!invalid',
            'phone' => preg_match(self::PHONE_PATTERN, $text) === 1 ? $text : '!invalid',
            'number' => self::number($text, $field),
            'date' => self::date($text),
            'select', 'radio', 'club', 'competition' => in_array($text, $allowed, true) ? $text : '!invalid',
            default => '!invalid',
        };
    }

    /** @param array<string, mixed> $field */
    private static function number(string $text, array $field): int|float|string
    {
        if (!is_numeric($text)) {
            return '!invalid';
        }
        $number = $text + 0;
        if ($field['min_value'] !== null && $number < (float) $field['min_value']) {
            return '!min';
        }
        if ($field['max_value'] !== null && $number > (float) $field['max_value']) {
            return '!max';
        }

        return $number;
    }

    private static function date(string $text): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $text);

        return $date !== false && $date->format('Y-m-d') === $text ? $text : '!invalid';
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || (is_string($value) && trim($value) === '');
    }
}
