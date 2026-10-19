<?php

namespace App\Tests\Unit\Scrutineering;

use App\Scrutineering\EquipmentRules;
use PHPUnit\Framework\TestCase;

/** Bornes des contrôles d'équipement (PAGE_SCRUTINEERING.md § 3.2, ADM-SCR-08). */
final class EquipmentRulesTest extends TestCase
{
    public function testStatusesGoFromZeroToFour(): void
    {
        foreach (['kayak_status', 'vest_status', 'helmet_status'] as $parameter) {
            self::assertTrue(EquipmentRules::isValid($parameter, 0));
            self::assertTrue(EquipmentRules::isValid($parameter, 4));
            self::assertFalse(EquipmentRules::isValid($parameter, 5));
        }
    }

    public function testPaddlesGoFromZeroToSix(): void
    {
        self::assertTrue(EquipmentRules::isValid('paddle_count', 6));
        self::assertFalse(EquipmentRules::isValid('paddle_count', 7));
    }

    public function testNegativeValuesAndUnknownParametersAreRejected(): void
    {
        self::assertFalse(EquipmentRules::isValid('kayak_status', -1));
        self::assertFalse(EquipmentRules::isValid('comment', 1));
    }

    public function testJournalLabelsAreTheFrenchEquipmentNames(): void
    {
        self::assertSame('kayak', EquipmentRules::journalLabel('kayak_status'));
        self::assertSame('gilet', EquipmentRules::journalLabel('vest_status'));
        self::assertSame('casque', EquipmentRules::journalLabel('helmet_status'));
        self::assertSame('pagaies', EquipmentRules::journalLabel('paddle_count'));
    }
}
