<?php

namespace App\PublicSite\Ics;

/** Événement iCalendar en journée entière : une journée de compétition (API_PUBLIC_TRANSVERSE.md § 3.2). */
final class IcsEvent
{
    public function __construct(
        public readonly string $uid,
        public readonly \DateTimeImmutable $start,
        public readonly \DateTimeImmutable $end,
        public readonly string $summary,
        public readonly ?string $location,
        public readonly ?string $url,
        public readonly ?\DateTimeImmutable $lastModified = null,
    ) {
    }

    /** UID stable d'une journée, identique dans l'abonnement de la compétition et dans le fichier de la journée. */
    public static function gamedayUid(int $gamedayId): string
    {
        return sprintf('gameday-%d@kayak-polo.info', $gamedayId);
    }
}
