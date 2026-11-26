<?php

namespace App\RuleCatalog;

/** Builders of the sections of a RuleCatalogDescription. */
final class RuleSection
{
    public const YES = 'oui';
    public const NO = 'non';

    /**
     * @param array<string, string>             $columns label of each column, by key of the rows
     * @param list<array<string, string|int>>   $rows
     *
     * @return array<string, mixed>
     */
    public static function table(string $title, array $columns, array $rows): array
    {
        return [
            'title' => $title,
            'columns' => array_map(static fn (string $key, string $label): array => ['key' => $key, 'label' => $label], array_keys($columns), $columns),
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, string|int> $pairs value of each label
     *
     * @return array<string, mixed>
     */
    public static function pairs(string $title, array $pairs): array
    {
        return [
            'title' => $title,
            'pairs' => array_map(static fn (string $label, string|int $value): array => [$label, (string) $value], array_keys($pairs), $pairs),
        ];
    }

    /**
     * @param list<string> $lines
     *
     * @return array<string, mixed>
     */
    public static function lines(string $title, array $lines): array
    {
        return ['title' => $title, 'lines' => $lines];
    }

    public static function yesNo(bool $value): string
    {
        return $value ? self::YES : self::NO;
    }

    /** @param list<string>|null $values */
    public static function list(?array $values, string $empty = '—'): string
    {
        return $values === null || $values === [] ? $empty : implode(', ', $values);
    }
}
