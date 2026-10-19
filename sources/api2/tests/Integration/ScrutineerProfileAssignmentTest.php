<?php

namespace App\Tests\Integration;

/**
 * Le profil 10 « Contrôle équipement » n'est attribué que par les profils 1 et 2, utilisateur comme mandat
 * (PAGE_SCRUTINEERING.md § 2.2, ADM-SCR-20). Comptes : TADMIN (profil 2), TDIV (profil 3), TCLUB (profil 7).
 */
final class ScrutineerProfileAssignmentTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->beginWrites();
    }

    /** @return array<string, mixed> */
    private static function scrutineer(string $code): array
    {
        return ['code' => $code, 'identite' => 'Nouveau Contrôleur', 'mail' => strtolower($code) . '@example.test', 'niveau' => 10,
            'filtreSaison' => '|2997|', 'filtreCompetition' => '|SCA|'];
    }

    public function testAdmScr20OnlyProfiles1And2CreateAScrutineer(): void
    {
        $refused = $this->requestAs('TDIV', 'POST', '/admin/users', self::scrutineer('TNEW3'), 403);
        self::assertSame('PROFILE_RESTRICTED', $refused['code']);

        $this->requestAs('TADMIN', 'POST', '/admin/users', self::scrutineer('TNEW2'), 201);
    }

    public function testAdmScr20OnlyProfiles1And2GiveAScrutineerMandate(): void
    {
        $mandate = ['libelle' => 'Contrôle', 'niveau' => 10, 'filtreSaison' => '|2997|', 'filtreCompetition' => '|SCA|'];

        $refused = $this->requestAs('TDIV', 'POST', '/admin/users/TCLUB/mandats', $mandate, 403);
        self::assertSame('PROFILE_RESTRICTED', $refused['code']);
    }
}
