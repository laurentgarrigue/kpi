<?php

namespace App\EventListener;

use App\Entity\User;
use App\Security\MandateApplier;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Reads the X-Active-Mandate header from the request and applies
 * the corresponding mandate filters to the authenticated User entity.
 *
 * This ensures that all controllers using $user->getAllowedSeasons() etc.
 * automatically get the mandate's restrictions instead of the user's base filters.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 0)]
class ActiveMandateListener
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly MandateApplier $mandates,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $mandateId = $request->headers->get('X-Active-Mandate');

        if ($mandateId === null || $mandateId === '') {
            return;
        }

        $mandateId = (int) $mandateId;

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->mandates->apply($user, $mandateId);
    }
}
