<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Data\Gameday;
use App\ClubRanking\Model\Alert;
use App\ClubRanking\NameNormalizer;

/**
 * Groups the gamedays of the national perimeter (and the international competitions) into organised events (same
 * organiser, same place, overlapping or consecutive days) and
 * gives each its single nature, the highest that applies (§ 5.7): international > final of several categories >
 * final of one category or cup final > day of several categories > day of one category.
 */
final class OrganisedEvents
{
    /** @var list<OrganisedEvent> */
    private array $events = [];
    /** @var list<Alert> */
    private array $alerts = [];

    public function __construct(CalculationContext $context)
    {
        $gamedays = [];
        foreach ($context->data->gamedays as $gameday) {
            if (!$this->inPerimeter($gameday, $context)) {
                continue;
            }
            if ($gameday->start === null) {
                $this->alerts[] = new Alert(Alert::GAMEDAY_WITHOUT_DATE, sprintf('Journée %d (%s) sans date : non comptée.', $gameday->id, $gameday->competition), context: ['gameday' => $gameday->id, 'gameday_competitions' => [$gameday->id => $gameday->competition]]);
                continue;
            }
            $gamedays[] = $gameday;
        }
        foreach ($this->group($gamedays, $context) as $group) {
            $event = new OrganisedEvent($group, $this->nature($group, $context));
            if (!$context->data->isEligibleClub($event->organiser())) {
                $this->alerts[] = new Alert(Alert::ORGANISER_UNKNOWN, sprintf('%s : organisateur « %s » qui n\'est pas un club, aucun point.', $event->describe(), $event->organiser() !== '' ? $event->organiser() : ($group[0]->organiserLabel !== '' ? $group[0]->organiserLabel : 'non renseigné')), context: ['gamedays' => array_map(static fn (Gameday $gameday): int => $gameday->id, $group), 'gameday_competitions' => array_column(array_map(static fn (Gameday $gameday): array => [$gameday->id, $gameday->competition], $group), 1, 0)]);
                continue;
            }
            $this->events[] = $event;
        }
    }

    /** @return list<OrganisedEvent> events organised by an eligible club */
    public function events(): array
    {
        return $this->events;
    }

    /** @return list<Alert> */
    public function alerts(): array
    {
        return $this->alerts;
    }

    private function inPerimeter(Gameday $gameday, CalculationContext $context): bool
    {
        return $context->scale->isNational($gameday->competition) || $this->isInternational($gameday, $context);
    }

    private function isInternational(Gameday $gameday, CalculationContext $context): bool
    {
        return $context->data->competition($gameday->competition)?->isInternational() === true
            && !$context->scale->isNational($gameday->competition);
    }

    /**
     * @param list<Gameday> $gamedays
     *
     * @return list<non-empty-list<Gameday>>
     */
    private function group(array $gamedays, CalculationContext $context): array
    {
        // The phases of a tournament (CP) share the organiser of the place: one typed on a single phase is enough
        $tournamentOrganiser = [];
        foreach ($gamedays as $gameday) {
            if ($gameday->organiserCode !== '' && $context->data->competition($gameday->competition)?->isTournament() === true) {
                $tournamentOrganiser[NameNormalizer::normalize($gameday->place)] ??= $gameday->organiserCode;
            }
        }
        $isTournament = static fn (Gameday $gameday): bool => $context->data->competition($gameday->competition)?->isTournament() === true;
        $key = static fn (Gameday $gameday): string => ($isTournament($gameday) ? $tournamentOrganiser[NameNormalizer::normalize($gameday->place)] ?? $gameday->organiserCode : $gameday->organiserCode)
            . '|' . NameNormalizer::normalize($gameday->place);
        usort($gamedays, static fn (Gameday $a, Gameday $b): int => [$key($a), $a->start, $a->id] <=> [$key($b), $b->start, $b->id]);
        $groups = [];
        $current = [];
        $currentEnd = null;
        foreach ($gamedays as $gameday) {
            if ($current !== [] && $key($current[0]) === $key($gameday) && $gameday->start <= self::nextDay($currentEnd)) {
                $current[] = $gameday;
                $currentEnd = max($currentEnd, $gameday->lastDay());
                continue;
            }
            if ($current !== []) {
                $groups[] = $current;
            }
            $current = [$gameday];
            $currentEnd = $gameday->lastDay();
        }
        if ($current !== []) {
            $groups[] = $current;
        }

        return $groups;
    }

    /** Gamedays on consecutive days (Saturday, Sunday) belong to the same event. */
    private static function nextDay(string $date): string
    {
        return (new \DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
    }

    /** @param non-empty-list<Gameday> $gamedays */
    private function nature(array $gamedays, CalculationContext $context): string
    {
        $divisions = [];
        $finals = [];
        foreach ($gamedays as $gameday) {
            if ($this->isInternational($gameday, $context)) {
                return OrganisedEvent::INTERNATIONAL;
            }
            $division = $context->scale->categoryOf($gameday->competition)?->code ?? $gameday->competition;
            $divisions[$division] = true;
            if ($context->scale->isFinalCompetition($gameday->competition)) {
                $finals[$division] = true;
            }
        }

        return match (true) {
            count($finals) > 1 => OrganisedEvent::FINAL_MULTI,
            count($finals) === 1 => OrganisedEvent::FINAL_SINGLE,
            count($divisions) > 1 => OrganisedEvent::DAY_MULTI,
            default => OrganisedEvent::DAY_SINGLE,
        };
    }
}
