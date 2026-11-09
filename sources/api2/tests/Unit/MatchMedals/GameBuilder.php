<?php

namespace App\Tests\Unit\MatchMedals;

use App\MatchMedals\Model\GameEvent;
use App\MatchMedals\Model\GameRecord;
use App\MatchMedals\Model\Player;
use App\MatchMedals\Model\Referee;

/** Test data builder: a validated, published game; facts are added with their remaining time « mm:ss ». */
final class GameBuilder
{
    private string $type = 'C';
    private ?string $finalPeriod = 'M2';
    private bool $isValidated = true;
    private bool $isPublished = true;
    /** @var list<GameEvent> */
    private array $events = [];
    /** @var list<Referee> */
    private array $referees = [];
    private ?string $scoreA = null;
    private ?string $scoreB = null;

    public static function game(): self
    {
        return new self();
    }

    public function knockout(): self
    {
        $this->type = GameRecord::TYPE_KNOCKOUT;

        return $this;
    }

    public function endedIn(string $period): self
    {
        $this->finalPeriod = $period;

        return $this;
    }

    public function notValidated(): self
    {
        $this->isValidated = false;

        return $this;
    }

    public function score(string $a, string $b): self
    {
        [$this->scoreA, $this->scoreB] = [$a, $b];

        return $this;
    }

    public function goal(string $side, string $period, ?string $remaining, ?int $player = null, bool $coach = false): self
    {
        $this->events[] = new GameEvent('e'.count($this->events), $side, GameEvent::GOAL, $period, self::seconds($remaining), $player === null ? null : new Player($player, $coach));

        return $this;
    }

    public function card(string $side, string $code, string $period, string $remaining, int $player = 99): self
    {
        $this->events[] = new GameEvent('e'.count($this->events), $side, $code, $period, self::seconds($remaining), new Player($player));

        return $this;
    }

    /** Goals scored one after another, in the given side order, a minute apart, in the first half. */
    public function goals(string $sides, int $player = 0): self
    {
        foreach (str_split($sides) as $i => $side) {
            $this->goal($side, 'M1', sprintf('%02d:00', 9 - $i % 9), $player === 0 ? null : $player);
        }

        return $this;
    }

    public function referee(int $matric, string $role = Referee::PRINCIPAL): self
    {
        $this->referees[] = new Referee($matric, $role);

        return $this;
    }

    public function build(): GameRecord
    {
        $count = static fn (string $side, array $events): int => count(array_filter($events, static fn (GameEvent $e): bool => $e->isGoal() && $e->side === $side && $e->period !== 'TB'));

        return new GameRecord(
            1,
            $this->type,
            $this->finalPeriod,
            $this->scoreA ?? (string) $count('A', $this->events),
            $this->scoreB ?? (string) $count('B', $this->events),
            $this->isValidated,
            $this->isPublished,
            true,
            $this->events,
            $this->referees,
        );
    }

    private static function seconds(?string $remaining): ?int
    {
        if ($remaining === null) {
            return null;
        }
        [$minutes, $seconds] = array_map('intval', explode(':', $remaining));

        return $minutes * 60 + $seconds;
    }
}
