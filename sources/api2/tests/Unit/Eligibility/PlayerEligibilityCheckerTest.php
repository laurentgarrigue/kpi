<?php

namespace App\Tests\Unit\Eligibility;

use App\Eligibility\PlayerEligibilityChecker;
use App\Eligibility\PlayerEligibilityRules as Rules;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Règles « joueur en règle » (PlayerEligibilityRules) appliquées par PlayerEligibilityChecker.
 *
 * On teste les deux fonctions pures : evaluate() (quels critères échouent) et
 * decide() (bloquer / forcer / alerter). Le SQL (checkPlayer, enforceOnCopiedRoster)
 * n'est pas couvert ici.
 */
final class PlayerEligibilityCheckerTest extends TestCase
{
    private PlayerEligibilityChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new PlayerEligibilityChecker($this->createStub(Connection::class));
    }

    private const COMPLIANT = [
        'Origine' => '2026',
        'Type_licence' => 'Carte 1 an Compétition',
        'Etat_certificat_CK' => 'OUI',
        'Pagaie_ECA' => 'PAGV',
        'date_surclassement' => null,
    ];

    public function testLevelDetection(): void
    {
        self::assertSame(Rules::LEVEL_NATIONAL, Rules::levelFor('N1H', 'NAT'));
        self::assertSame(Rules::LEVEL_NATIONAL, Rules::levelFor('CFH', 'NAT'));
        self::assertSame(Rules::LEVEL_REGIONAL, Rules::levelFor('REG-BRE', 'REG'));
        self::assertNull(Rules::levelFor('T-ACI', 'INT'));
        self::assertNull(Rules::levelFor('ECA', null));
    }

    public function testCompliantPlayerPassesBothLevels(): void
    {
        self::assertSame([], $this->checker->evaluate(self::COMPLIANT, Rules::LEVEL_NATIONAL, '2026', 'N1H', 'SEN'));
        self::assertSame([], $this->checker->evaluate(self::COMPLIANT, Rules::LEVEL_REGIONAL, '2026', 'REG-BRE', 'SEN'));
    }

    public function testNationalCriteria(): void
    {
        $row = ['Origine' => '2025', 'Type_licence' => 'Carte 1 an Loisir', 'Etat_certificat_CK' => 'NON', 'Pagaie_ECA' => 'PAGJ'];
        self::assertSame(
            [Rules::ERROR_LICENCE_SEASON, Rules::ERROR_LICENCE_TYPE, Rules::ERROR_CERTIFICATE, Rules::ERROR_PAGAIE, Rules::ERROR_SURCLASSEMENT],
            $this->checker->evaluate($row, Rules::LEVEL_NATIONAL, '2026', 'N1H', 'CAD')
        );
        // Licence compétition exigée en national aussi
        self::assertSame([Rules::ERROR_LICENCE_TYPE], $this->checker->evaluate(['Type_licence' => null] + self::COMPLIANT, Rules::LEVEL_NATIONAL, '2026', 'N1H', 'SEN'));
    }

    public function testSurclassement(): void
    {
        $withDate = ['date_surclassement' => '2026-01-10'] + self::COMPLIANT;
        self::assertSame([], $this->checker->evaluate($withDate, Rules::LEVEL_NATIONAL, '2026', 'N1H', 'CAD'));
        // Compétition non listée ou catégorie exemptée
        self::assertSame([], $this->checker->evaluate(self::COMPLIANT, Rules::LEVEL_NATIONAL, '2026', 'NPO', 'CAD'));
        self::assertSame([], $this->checker->evaluate(self::COMPLIANT, Rules::LEVEL_NATIONAL, '2026', 'N1H', 'JUN'));
        // Vétérans à partir de V5 : surclassement obligatoire
        self::assertSame([Rules::ERROR_SURCLASSEMENT], $this->checker->evaluate(self::COMPLIANT, Rules::LEVEL_NATIONAL, '2026', 'N1H', 'V5'));
    }

    public function testRegionalCriteria(): void
    {
        // Pagaie jaune suffit en régional, pas en national
        $yellow = ['Pagaie_ECA' => 'PAGJ'] + self::COMPLIANT;
        self::assertSame([], $this->checker->evaluate($yellow, Rules::LEVEL_REGIONAL, '2026', 'REG-BRE', 'CAD'));
        self::assertSame([Rules::ERROR_PAGAIE], $this->checker->evaluate($yellow, Rules::LEVEL_NATIONAL, '2026', 'NPO', 'SEN'));

        $row = ['Origine' => '2026', 'Type_licence' => 'Carte 1 an Loisir', 'Etat_certificat_CK' => 'NON', 'Pagaie_ECA' => 'PAGB'];
        self::assertSame(
            [Rules::ERROR_LICENCE_TYPE, Rules::ERROR_CERTIFICATE, Rules::ERROR_PAGAIE],
            $this->checker->evaluate($row, Rules::LEVEL_REGIONAL, '2026', 'REG-BRE', 'CAD')
        );
    }

    public function testUnknownOrMissingLicenceFailsEverything(): void
    {
        self::assertSame(
            [Rules::ERROR_LICENCE_SEASON, Rules::ERROR_LICENCE_TYPE, Rules::ERROR_CERTIFICATE, Rules::ERROR_PAGAIE],
            $this->checker->evaluate([], Rules::LEVEL_REGIONAL, '2026', 'REG-BRE', '')
        );
        self::assertContains(Rules::ERROR_PAGAIE, $this->checker->evaluate(['Pagaie_ECA' => 'PAGI'] + self::COMPLIANT, Rules::LEVEL_REGIONAL, '2026', 'REG-BRE', 'SEN'));
    }

    public function testDecideCompliantAlwaysAllowed(): void
    {
        $d = $this->checker->decide(Rules::LEVEL_NATIONAL, [], PlayerEligibilityChecker::OPERATION_ADD, '-', false, 7);
        self::assertTrue($d['allowed']);
    }

    public function testDecideNationalAdd(): void
    {
        $errors = [Rules::ERROR_CERTIFICATE];
        $add = PlayerEligibilityChecker::OPERATION_ADD;

        // Refusé sans forçage, quel que soit le statut
        self::assertFalse($this->checker->decide(Rules::LEVEL_NATIONAL, $errors, $add, 'A', false, 1)['allowed']);
        // Forçage profil <= 2 uniquement vers E / A
        self::assertTrue($this->checker->decide(Rules::LEVEL_NATIONAL, $errors, $add, 'A', true, 2)['allowed']);
        self::assertTrue($this->checker->decide(Rules::LEVEL_NATIONAL, $errors, $add, 'E', true, 1)['allowed']);
        self::assertFalse($this->checker->decide(Rules::LEVEL_NATIONAL, $errors, $add, '-', true, 1)['allowed']);
        // Profil 3 : jamais, et canForce = false
        $d = $this->checker->decide(Rules::LEVEL_NATIONAL, $errors, $add, 'A', true, 3);
        self::assertFalse($d['allowed']);
        self::assertFalse($d['canForce']);
    }

    public function testDecideNationalStatusChange(): void
    {
        $errors = [Rules::ERROR_PAGAIE];
        $status = PlayerEligibilityChecker::OPERATION_STATUS_CHANGE;

        // Statuts non joueurs toujours autorisés
        foreach (['E', 'A', 'X'] as $s) {
            self::assertTrue($this->checker->decide(Rules::LEVEL_NATIONAL, $errors, $status, $s, false, 7)['allowed'], $s);
        }
        // Joueur / capitaine : bloqué, forçable par profil <= 2
        foreach (['-', 'C'] as $s) {
            self::assertFalse($this->checker->decide(Rules::LEVEL_NATIONAL, $errors, $status, $s, false, 1)['allowed'], $s);
            self::assertFalse($this->checker->decide(Rules::LEVEL_NATIONAL, $errors, $status, $s, true, 3)['allowed'], $s);
            $d = $this->checker->decide(Rules::LEVEL_NATIONAL, $errors, $status, $s, true, 2);
            self::assertTrue($d['allowed'], $s);
        }
    }

    public function testDecideRegionalOnlyWarns(): void
    {
        $errors = [Rules::ERROR_CERTIFICATE];

        $d = $this->checker->decide(Rules::LEVEL_REGIONAL, $errors, PlayerEligibilityChecker::OPERATION_ADD, 'E', false, 7);
        self::assertTrue($d['allowed']);
        self::assertSame($errors, $d['warnings']);

        $d = $this->checker->decide(Rules::LEVEL_REGIONAL, $errors, PlayerEligibilityChecker::OPERATION_STATUS_CHANGE, 'C', false, 7);
        self::assertTrue($d['allowed']);
        self::assertSame($errors, $d['warnings']);

        // Passage vers un statut non joueur : rien à signaler
        $d = $this->checker->decide(Rules::LEVEL_REGIONAL, $errors, PlayerEligibilityChecker::OPERATION_STATUS_CHANGE, 'X', false, 7);
        self::assertSame([], $d['warnings']);
    }
}
