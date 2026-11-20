<?php

namespace App\EventListener;

use App\Security\PasswordRenewalService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Un compte « mot de passe à renouveler » n'a plus accès à api2 avec un JWT émis avant le marquage
 * (AUTH-RN-03) : le jeton est refusé (401), app4 renvoie vers /login, et le login exige le renouvellement.
 */
final class PasswordRenewalJwtListener
{
    public function __construct(private readonly PasswordRenewalService $renewals)
    {
    }

    #[AsEventListener(event: Events::JWT_DECODED)]
    public function onDecoded(JWTDecodedEvent $event): void
    {
        $userCode = $event->getPayload()['username'] ?? null;
        if (is_string($userCode) && $this->renewals->isRequired($userCode)) {
            $event->markAsInvalid();
        }
    }
}
