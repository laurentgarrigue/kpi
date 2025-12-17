<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class TokenUser implements UserInterface
{
    public function __construct(
        private string $email,
        private array $roles = []
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        // Garantir que ROLE_USER est toujours présent
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
        // Rien à faire ici - pas de credentials sensibles
    }
}
