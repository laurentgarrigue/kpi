<?php

namespace App\ClubRanking\Model;

/**
 * One line of a table of article 7 of the annex (§ 3.1): its exact wording, its points and how it is computed.
 * The code is the article and the rank in the table (7.3.5).
 */
final class Rule
{
    public const ARTICLE_PATTERN = '/^7\.[1-9]$/';

    /** @param array<string, mixed> $params parameters of the calculation type */
    public function __construct(
        public readonly string $article,
        public readonly int $rank,
        public readonly string $label,
        public readonly string $shortLabel,
        public readonly int $points,
        public readonly RuleType $type,
        public readonly array $params = [],
        public readonly string $note = '',
        public readonly ?int $id = null,
    ) {
    }

    public function code(): string
    {
        return $this->article . '.' . $this->rank;
    }

    /** @return list<string> */
    public function stringList(string $param): array
    {
        $value = $this->params[$param] ?? [];

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    public function bool(string $param, bool $default): bool
    {
        $value = $this->params[$param] ?? $default;

        return is_bool($value) ? $value : $default;
    }

    public function string(string $param, string $default): string
    {
        $value = $this->params[$param] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public function int(string $param, int $default): int
    {
        $value = $this->params[$param] ?? $default;

        return is_int($value) ? $value : $default;
    }
}
