<?php

namespace App\Tests\Unit\Designation;

use App\Designation\Authority;
use App\Designation\DesignationRules;
use App\Designation\ScopeType;
use PHPUnit\Framework\TestCase;

/** Validation et droits d'une Désignation avant écriture (FEATURE_DESIGNATIONS.md DES-01, DES-05 à DES-07, D23). */
final class DesignationRulesTest extends TestCase
{
    public function testDes01ARoleOnAScopeTheCatalogueDoesNotAllowIsRejected(): void
    {
        self::assertSame(DesignationRules::ERROR_SCOPE, DesignationRules::validate('DELEGUE_CNA', ScopeType::Equipe, Authority::Cna, 0));
    }

    public function testAnUnknownRoleIsRejected(): void
    {
        self::assertSame(DesignationRules::ERROR_ROLE, DesignationRules::validate('CHEF_ARBITRE', ScopeType::Journee, Authority::Cna, 0));
    }

    public function testD23OnlyANonPlayingRefereeMayBeDesignatedByTheOrganiser(): void
    {
        self::assertNull(DesignationRules::validate('ARBITRE_NJ', ScopeType::Journee, Authority::Organisateur, 0));
        self::assertSame(DesignationRules::ERROR_AUTHORITY, DesignationRules::validate('R1', ScopeType::Journee, Authority::Organisateur, 0));
    }

    public function testASingleHolderRoleAlreadyHeldOnTheScopeIsRejected(): void
    {
        self::assertSame(DesignationRules::ERROR_FULL, DesignationRules::validate('DELEGUE_CNA', ScopeType::Evenement, Authority::Cna, 1));
        self::assertNull(DesignationRules::validate('ARBITRE_NJ', ScopeType::Journee, Authority::Cna, 4));
        self::assertSame(DesignationRules::ERROR_FULL, DesignationRules::validate('ARBITRE_NJ', ScopeType::Journee, Authority::Cna, 5));
        self::assertNull(DesignationRules::validate('JURY_APPEL', ScopeType::Journee, Authority::Cna, 12));
    }

    public function testDes06OnlyProfilesOneAndTwoDesignate(): void
    {
        self::assertTrue(DesignationRules::canDesignate('R1', 1, false));
        self::assertTrue(DesignationRules::canDesignate('R1', 2, false));
        foreach (range(3, 10) as $profile) {
            self::assertFalse(DesignationRules::canDesignate('R1', $profile, false), "profil $profile");
        }
    }

    public function testDes07TheDelegateOfTheScopeDesignatesTheTeamsRepresentativeAndTheAppealJuryOnly(): void
    {
        self::assertTrue(DesignationRules::canDesignate('REP_EQUIPES', 5, true));
        self::assertTrue(DesignationRules::canDesignate('JURY_APPEL', 5, true));
        self::assertFalse(DesignationRules::canDesignate('JURY_APPEL', 5, false));
        self::assertFalse(DesignationRules::canDesignate('R1', 5, true));
    }
}
