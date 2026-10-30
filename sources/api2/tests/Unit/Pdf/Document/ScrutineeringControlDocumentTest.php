<?php

namespace App\Tests\Unit\Pdf\Document;

use App\Pdf\Document\ScrutineeringControlDocument;
use PHPUnit\Framework\TestCase;

/** Rows of the equipment control sheet (ex-FeuilleControle). */
final class ScrutineeringControlDocumentTest extends TestCase
{
    private const LABELS = [
        'player.staff' => 'Encadrement',
        'role.C' => 'Capitaine',
        'role.E' => 'Staff',
        'control.status_ok' => 'OK',
        'control.status_cosmetic' => 'Cosmétique',
        'control.status_safety' => 'Sécurité',
        'control.status_technical' => 'Technique',
    ];

    public function testStatusesAndStaffAfterThePlayers(): void
    {
        $rows = ScrutineeringControlDocument::rows([
            self::player('C', 1, kayak: 1, vest: 2, helmet: 4, paddles: 2),
            self::player('-', 2, kayak: 3),
            self::player('E', null),
            self::player('A', null),
        ], '2026', self::LABELS);

        self::assertCount(5, $rows, 'one separator before the staff');
        self::assertSame(['OK', 'Cosmétique', 'Technique', '2'], [$rows[0]['kayak'], $rows[0]['vest'], $rows[0]['helmet'], $rows[0]['paddles']]);
        self::assertSame(['Sécurité', '', '', ''], [$rows[1]['kayak'], $rows[1]['vest'], $rows[1]['helmet'], $rows[1]['paddles']]);
        self::assertSame(['_group' => 'Encadrement'], $rows[2]);
        self::assertSame(['Capitaine', '', 'Staff'], [$rows[0]['role'], $rows[1]['role'], $rows[3]['role']], 'roles by their label');
    }

    public function testAResetStatusIsBlank(): void
    {
        $rows = ScrutineeringControlDocument::rows([self::player('-', 3, kayak: 0, paddles: 0)], '2026', self::LABELS);

        self::assertSame('', $rows[0]['kayak']);
        self::assertSame('0', $rows[0]['paddles']);
    }

    /** @return array<string, mixed> */
    private static function player(string $role, ?int $number, ?int $kayak = null, ?int $vest = null, ?int $helmet = null, ?int $paddles = null): array
    {
        return [
            'Matric' => 100 + (int) $number, 'Reserve' => null, 'Origine' => '2026', 'Nom' => 'nom', 'Prenom' => 'prénom',
            'Numero' => $number, 'Capitaine' => $role,
            'kayak_status' => $kayak, 'vest_status' => $vest, 'helmet_status' => $helmet, 'paddle_count' => $paddles,
        ];
    }
}
