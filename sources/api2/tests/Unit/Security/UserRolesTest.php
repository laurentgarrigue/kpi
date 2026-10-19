<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Profil 10 « Contrôle équipement » (PAGE_SCRUTINEERING.md § 2) : rôle dédié, hors de la hiérarchie linéaire,
 * et mandat qui prime sur le profil principal (ADM-SCR-07).
 */
final class UserRolesTest extends TestCase
{
    private function user(int $niveau): User
    {
        return (new User())->setCode('T')->setNiveau($niveau);
    }

    public function testProfileTenIsTheScrutineer(): void
    {
        $user = $this->user(10);

        self::assertContains('ROLE_SCRUTINEER', $user->getRoles());
        self::assertTrue($user->isScrutineer());
    }

    public function testProfileNineStaysScorerOnly(): void
    {
        $user = $this->user(9);

        self::assertNotContains('ROLE_SCRUTINEER', $user->getRoles());
        self::assertFalse($user->isScrutineer());
    }

    public function testMandateOverridesTheScrutineerProfile(): void
    {
        $scrutineer = $this->user(10);
        $scrutineer->applyMandate(1, 5, null, null, null, null, null);
        self::assertFalse($scrutineer->isScrutineer());
        self::assertContains('ROLE_DELEGATE', $scrutineer->getRoles());

        $delegate = $this->user(5);
        $delegate->applyMandate(2, 10, null, null, null, null, null);
        self::assertTrue($delegate->isScrutineer());
    }

    public function testScrutineerIgnoresClubLimitation(): void
    {
        $user = $this->user(10);
        $user->setLimitClubs('C001');

        self::assertNull($user->getAllowedClubs());
    }
}
