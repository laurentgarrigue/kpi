<?php

namespace App\Tests\Unit\Designation;

use App\Designation\Authority;
use App\Designation\Requirement;
use App\Designation\RoleCatalog;
use App\Designation\ScopeType;
use PHPUnit\Framework\TestCase;

/** Catalogue des rôles de Désignation (FEATURE_DESIGNATIONS.md D3, D8, D16, D21, D23, D26, D27). */
final class RoleCatalogTest extends TestCase
{
    public function testEveryRoleOfTheSpecIsInTheCatalogue(): void
    {
        self::assertSame(
            ['RC', 'RESP_EQUIPE', 'DELEGUE_CNA', 'R1', 'JUGE_ARBITRE', 'CHEF_CONTROLE', 'RESP_TABLES', 'ARBITRE_NJ', 'REP_EQUIPES', 'JURY_APPEL', 'BUREAU_CNA', 'MEMBRE_CNA'],
            RoleCatalog::codes(),
        );
    }

    public function testDes12LabelsDependOnTheLevelOfTheCompetitionAndAreTranslated(): void
    {
        $expected = [
            // code => [national fr, autre fr, en]
            'DELEGUE_CNA' => ['Représentant CNA', 'Officiel principal', 'Chief Official'],
            'RC' => ['Responsable de compétition (RC)', 'Responsable de compétition (RC)', 'Competition Organiser'],
            'R1' => ['Responsable organisation (R1)', 'Responsable organisation (R1)', 'Technical Organiser'],
            'JUGE_ARBITRE' => ['Juge arbitre', 'Chef arbitre', 'Referee Manager'],
            'CHEF_CONTROLE' => ['Resp. contrôle matériel', 'Resp. contrôle matériel', 'Chief Scrutineer'],
            'RESP_TABLES' => ['Resp. tables de marque', 'Resp. tables de marque', 'Chief Table Official'],
            'JURY_APPEL' => ["Membre du jury d'appel", 'Membre du jury', 'Jury of Appeal member'],
        ];
        foreach ($expected as $code => [$national, $other, $en]) {
            $role = RoleCatalog::get($code);
            self::assertSame($national, $role->labelFor(true), "$code national");
            self::assertSame($other, $role->labelFor(false), "$code autre");
            self::assertSame($en, $role->labelFor(true, 'en'), "$code en");
            self::assertSame($national, $role->label, "$code libellé par défaut");
        }
        foreach (RoleCatalog::all() as $role) {
            self::assertNotSame('', $role->labelFor(false, 'en'), $role->code);
        }
    }

    public function testDes01ADelegateCannotBeDesignatedOnATeam(): void
    {
        self::assertFalse(RoleCatalog::get('DELEGUE_CNA')->allowsScope(ScopeType::Equipe));
    }

    public function testGamedayOfficialsCanBeDesignatedOnEventCompetitionOrGameday(): void
    {
        foreach (['DELEGUE_CNA', 'R1', 'JUGE_ARBITRE', 'CHEF_CONTROLE', 'RESP_TABLES', 'ARBITRE_NJ', 'REP_EQUIPES', 'JURY_APPEL'] as $code) {
            $role = RoleCatalog::get($code);
            foreach ([ScopeType::Evenement, ScopeType::Competition, ScopeType::Journee] as $scope) {
                self::assertTrue($role->allowsScope($scope), "$code sur {$scope->value}");
            }
            self::assertFalse($role->allowsScope(ScopeType::Niveau), $code);
        }
    }

    public function testScopesOfTheOtherRoles(): void
    {
        self::assertSame([ScopeType::Competition, ScopeType::Niveau], RoleCatalog::get('RC')->scopes);
        self::assertSame([ScopeType::Equipe], RoleCatalog::get('RESP_EQUIPE')->scopes);
        self::assertSame([ScopeType::Niveau], RoleCatalog::get('BUREAU_CNA')->scopes);
        self::assertSame([ScopeType::Niveau], RoleCatalog::get('MEMBRE_CNA')->scopes);
    }

    public function testD8MandateProfiles(): void
    {
        $expected = [
            'RC' => 4, 'RESP_EQUIPE' => 7, 'DELEGUE_CNA' => 5, 'R1' => 6, 'JUGE_ARBITRE' => 6, 'BUREAU_CNA' => 2,
            'ARBITRE_NJ' => null, 'REP_EQUIPES' => null, 'JURY_APPEL' => null, 'MEMBRE_CNA' => null,
            'CHEF_CONTROLE' => null, 'RESP_TABLES' => null,
        ];
        foreach ($expected as $code => $profile) {
            self::assertSame($profile, RoleCatalog::get($code)->mandateProfile, $code);
        }
    }

    public function testD8TheTeamManagerMandateIsLimitedToTheClubOfTheTeam(): void
    {
        self::assertTrue(RoleCatalog::get('RESP_EQUIPE')->mandateLimitedToTeamClub);
        self::assertFalse(RoleCatalog::get('RC')->mandateLimitedToTeamClub);
    }

    public function testD16NoBureauMandateForABaseProfileOfOneOrTwo(): void
    {
        $bureau = RoleCatalog::get('BUREAU_CNA');
        self::assertFalse($bureau->generatesMandateFor(1));
        self::assertFalse($bureau->generatesMandateFor(2));
        self::assertTrue($bureau->generatesMandateFor(3));
    }

    public function testMan03AMandateIsGeneratedWhateverTheBaseProfileForTheOtherRoles(): void
    {
        self::assertTrue(RoleCatalog::get('DELEGUE_CNA')->generatesMandateFor(7));
        self::assertTrue(RoleCatalog::get('DELEGUE_CNA')->generatesMandateFor(3));
        self::assertFalse(RoleCatalog::get('ARBITRE_NJ')->generatesMandateFor(8));
    }

    public function testDes05AndD23TheAuthorityIsCnaByDefaultAndOnlyANonPlayingRefereeMayComeFromTheOrganiser(): void
    {
        foreach (RoleCatalog::all() as $role) {
            self::assertSame(Authority::Cna, $role->defaultAuthority(), $role->code);
            self::assertSame($role->code === 'ARBITRE_NJ', $role->allowsAuthority(Authority::Organisateur), $role->code);
        }
    }

    public function testD26Requirements(): void
    {
        $expected = [
            'RC' => [Requirement::Required, Requirement::Required],
            'RESP_EQUIPE' => [Requirement::Required, Requirement::Optional],
            // international, régional, tournoi : représentant CNA / officiel principal et jury applicables, facultatifs
            'DELEGUE_CNA' => [Requirement::Required, Requirement::Optional],
            'R1' => [Requirement::Required, Requirement::Optional],
            'JUGE_ARBITRE' => [Requirement::Required, Requirement::Optional],
            'CHEF_CONTROLE' => [Requirement::Optional, Requirement::Optional],
            'RESP_TABLES' => [Requirement::Optional, Requirement::Optional],
            'ARBITRE_NJ' => [Requirement::Optional, Requirement::Optional],
            'REP_EQUIPES' => [Requirement::Optional, Requirement::Optional],
            'JURY_APPEL' => [Requirement::Optional, Requirement::Optional],
        ];
        foreach ($expected as $code => [$national, $other]) {
            self::assertSame($national, RoleCatalog::get($code)->requirement(true), "$code national");
            self::assertSame($other, RoleCatalog::get($code)->requirement(false), "$code autre");
        }
    }

    public function testD27TheDelegateMayDesignateTheTeamsRepresentativeAndTheAppealJury(): void
    {
        foreach (RoleCatalog::all() as $role) {
            self::assertSame(
                in_array($role->code, ['REP_EQUIPES', 'JURY_APPEL'], true),
                $role->designableByDelegate,
                $role->code,
            );
            self::assertSame(in_array($role->code, ['RC', 'BUREAU_CNA'], true) ? 2 : 3, $role->designableByMaxProfile, $role->code);
        }
    }

    public function testDes11LegacyColumnsOfTheGameday(): void
    {
        self::assertSame(['Delegue'], RoleCatalog::get('DELEGUE_CNA')->legacyColumns);
        self::assertSame(['Responsable_R1'], RoleCatalog::get('R1')->legacyColumns);
        self::assertSame(['ChefArbitre'], RoleCatalog::get('JUGE_ARBITRE')->legacyColumns);
        self::assertSame(['Rep_athletes'], RoleCatalog::get('REP_EQUIPES')->legacyColumns);
        self::assertSame(['Arb_nj1', 'Arb_nj2', 'Arb_nj3', 'Arb_nj4', 'Arb_nj5'], RoleCatalog::get('ARBITRE_NJ')->legacyColumns);
        self::assertSame(['Responsable_insc'], RoleCatalog::get('RC')->legacyColumns);
        self::assertSame([], RoleCatalog::get('JURY_APPEL')->legacyColumns);
        self::assertSame([], RoleCatalog::get('CHEF_CONTROLE')->legacyColumns);
        self::assertSame([], RoleCatalog::get('RESP_TABLES')->legacyColumns);
    }

    public function testSingleHolderRoles(): void
    {
        foreach (['DELEGUE_CNA', 'R1', 'JUGE_ARBITRE', 'CHEF_CONTROLE', 'RESP_TABLES', 'REP_EQUIPES'] as $code) {
            self::assertSame(1, RoleCatalog::get($code)->maxPerScope, $code);
        }
        self::assertSame(5, RoleCatalog::get('ARBITRE_NJ')->maxPerScope);
        self::assertNull(RoleCatalog::get('JURY_APPEL')->maxPerScope);
        self::assertNull(RoleCatalog::get('RC')->maxPerScope);
    }

    public function testAnUnknownRoleIsRejected(): void
    {
        self::assertFalse(RoleCatalog::has('CHEF_ARBITRE'));
        $this->expectException(\InvalidArgumentException::class);
        RoleCatalog::get('CHEF_ARBITRE');
    }
}
