<?php

namespace App\Tests\Unit\PublicResults;

use App\PublicResults\Scope\EventScopeFactory;
use App\PublicResults\Scope\GamedayScope;
use App\PublicResults\Scope\TournamentScope;
use PHPUnit\Framework\TestCase;

final class EventScopeFactoryTest extends TestCase
{
    public function testIdsBelow3000AreTournaments(): void
    {
        $scope = EventScopeFactory::fromEventId(2999);

        self::assertInstanceOf(TournamentScope::class, $scope);
        self::assertSame([2999], $scope->parameters());
        self::assertFalse($scope->isSingleGameday());
    }

    public function testIdsFrom3000AreChampionshipGamedays(): void
    {
        $scope = EventScopeFactory::fromEventId(3000);

        self::assertInstanceOf(GamedayScope::class, $scope);
        self::assertSame('j.Id = ?', $scope->condition());
        self::assertTrue($scope->isSingleGameday());
    }
}
