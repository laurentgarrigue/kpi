<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Date d'entrée en vigueur du second facteur obligatoire (AUTH_HARDENING.md § 5.2bis), décidée par l'exploitant :
 * AUTH_2FA_ENFORCED_FROM=AAAA-MM-JJ dans sources/api2/.env puis `make api2_restart`. Vide = aucune obligation.
 */
final class SecondFactorSettings
{
    private readonly ?\DateTimeImmutable $enforcedFrom;

    public function __construct(
        #[Autowire('%env(AUTH_2FA_ENFORCED_FROM)%')]
        string $enforcedFrom,
    ) {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($enforcedFrom));
        $this->enforcedFrom = $date === false ? null : $date;
    }

    public function enforcedFrom(): ?\DateTimeImmutable
    {
        return $this->enforcedFrom;
    }
}
