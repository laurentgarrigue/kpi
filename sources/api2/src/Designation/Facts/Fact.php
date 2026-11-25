<?php

namespace App\Designation\Facts;

/**
 * Un fait du relevé (FEATURE_DESIGNATIONS.md D19, § 6) : jamais un montant, une quantité rattachée à un club (null =
 * club à attribuer à la main), avec sa source (REL-04).
 */
final class Fact
{
    public const GAMEDAY_HOSTED = 'gameday_hosted';
    public const GAMEDAY_PITCHES = 'gameday_pitches';
    public const FINAL_HOSTED = 'final_hosted';
    public const PODIUM = 'podium';
    public const RC_PROVIDED = 'rc_provided';
    public const OFFICIAL_PROVIDED = 'official_provided';
    /** Un type de fait par couleur : les pénalités diffèrent (annexe 8 § 2.2), le relevé se filtre par type. */
    public const CARD_YELLOW = 'card_yellow';
    public const CARD_RED = 'card_red';
    public const CARD_BLACK = 'card_black';
    public const OUTFIT_WARNING = 'outfit_warning';
    public const INFRACTION = 'infraction';

    public function __construct(
        public readonly ?string $club,
        public readonly string $fact,
        public readonly string $qualifier,
        public readonly int $quantity,
        public readonly string $detail,
        public readonly string $sourceType,
        public readonly string $sourceId,
    ) {
    }
}
