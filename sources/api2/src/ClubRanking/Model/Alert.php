<?php

namespace App\ClubRanking\Model;

/**
 * Something to check by hand after a calculation (§ 8): unpublished ranking, gameday without organiser, official
 * not matched to a licensee… A blocking alert means that a part of a rule could not be computed.
 */
final class Alert
{
    public const RANKING_NOT_PUBLISHED = 'ranking_not_published';
    public const COMPETITION_MISSING = 'competition_missing';
    public const ORGANISER_UNKNOWN = 'organiser_unknown';
    public const OFFICIAL_UNRESOLVED = 'official_unresolved';
    public const SELECTION_TEAM = 'selection_team';
    public const LICENCE_NOT_RENEWED = 'licence_not_renewed';
    public const EXCLUDED_ENTRY = 'excluded_entry';
    public const AFTER_SEASON = 'after_season';
    public const GAMEDAY_WITHOUT_DATE = 'gameday_without_date';

    /** @param array<string, mixed> $context */
    public function __construct(
        public readonly string $kind,
        public readonly string $message,
        public readonly ?string $ruleCode = null,
        public readonly bool $blocking = false,
        public readonly array $context = [],
    ) {
    }

    public function key(): string
    {
        return $this->kind . '|' . ($this->ruleCode ?? '') . '|' . $this->message;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'message' => $this->message,
            'rule' => $this->ruleCode,
            'blocking' => $this->blocking,
            'context' => $this->context,
        ];
    }
}
