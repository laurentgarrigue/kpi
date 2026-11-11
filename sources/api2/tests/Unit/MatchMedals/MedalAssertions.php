<?php

namespace App\Tests\Unit\MatchMedals;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchMedals;
use App\MatchMedals\MedalThresholds;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\AwardScope;
use App\MatchMedals\Model\GameRecord;
use Symfony\Component\Yaml\Yaml;

/** Computes a game with every rule of src/MatchMedals/Rule and the thresholds of config/packages/match_medals.yaml. */
trait MedalAssertions
{
    private static function thresholds(array $override = []): MedalThresholds
    {
        $config = Yaml::parseFile(dirname(__DIR__, 3).'/config/packages/match_medals.yaml');

        return MedalThresholds::fromArray([...$config['parameters']['match_medals.thresholds'], ...$override]);
    }

    private static function compute(GameRecord $game, array $thresholds = []): MatchMedals
    {
        $rules = [];
        foreach (glob(dirname(__DIR__, 3).'/src/MatchMedals/Rule/*.php') as $file) {
            $class = 'App\\MatchMedals\\Rule\\'.basename($file, '.php');
            if (!(new \ReflectionClass($class))->isInstantiable()) {
                continue;
            }
            $rules[] = new $class();
        }

        return (new MatchMedalCalculator($rules))->compute($game, self::thresholds($thresholds));
    }

    /** @return list<string> codes given to a team side, sorted */
    private static function teamCodes(MatchMedals $medals, string $side): array
    {
        return self::codes($medals, AwardScope::Team, static fn (Award $a): bool => $a->side === $side);
    }

    /** @return list<string> codes given to a player */
    private static function playerCodes(MatchMedals $medals, int $matric): array
    {
        return self::codes($medals, AwardScope::Player, static fn (Award $a): bool => $a->matric === $matric);
    }

    /** @return list<string> codes given to a referee */
    private static function refereeCodes(MatchMedals $medals, int $matric): array
    {
        return self::codes($medals, AwardScope::Referee, static fn (Award $a): bool => $a->matric === $matric);
    }

    /** @return list<string> */
    private static function codes(MatchMedals $medals, AwardScope $scope, \Closure $filter): array
    {
        $codes = array_map(static fn (Award $a): string => $a->code, array_values(array_filter($medals->of($scope), $filter)));
        sort($codes);

        return $codes;
    }
}
