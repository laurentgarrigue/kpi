<?php

namespace App\EventListener;

use App\Security\KnownDeviceService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lie le JWT d'accès à l'appareil qui l'a obtenu, pour qu'une révocation d'appareil
 * (AUTH_HARDENING.md § 3bis) coupe aussi l'access token encore valide (JWT_TTL), pas seulement
 * les refresh tokens. Les JWT émis avant ce claim (sans `dfp`) restent acceptés jusqu'à expiration.
 */
final class DeviceBoundJwtListener
{
    private const CLAIM = 'dfp';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly KnownDeviceService $knownDeviceService,
    ) {
    }

    #[AsEventListener(event: Events::JWT_CREATED)]
    public function onCreated(JWTCreatedEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $payload = $event->getData();
        $payload[self::CLAIM] = $this->knownDeviceService->currentFingerprint($request);
        $event->setData($payload);
    }

    #[AsEventListener(event: Events::JWT_DECODED)]
    public function onDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $fingerprint = $payload[self::CLAIM] ?? null;
        $userCode = $payload['username'] ?? null;
        if (!is_string($fingerprint) || !is_string($userCode)) {
            return;
        }

        if ($this->knownDeviceService->isRevoked($userCode, $fingerprint)) {
            $event->markAsInvalid();
        }
    }
}
