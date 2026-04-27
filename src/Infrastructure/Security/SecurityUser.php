<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adaptateur : pont entre l'entité Domain User et l'interface Symfony Security.
 *
 * Le Domain User ne connaît pas Symfony. Ce wrapper vit en Infrastructure
 * et implémente les contrats Symfony sans polluer le Domain.
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private readonly User $domainUser,
    ) {}

    public function getUserIdentifier(): string
    {
        $username = $this->domainUser->getUsername();

        return $username !== '' ? $username : throw new \LogicException('Username cannot be empty.');
    }

    public function getRoles(): array
    {
        return $this->domainUser->isAdmin()
            ? ['ROLE_ADMIN', 'ROLE_USER']
            : ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->domainUser->getHashedPassword();
    }

    public function eraseCredentials(): void
    {
        // Les credentials sont dans la DB — rien à effacer en mémoire
    }

    public function getDomainUser(): User
    {
        return $this->domainUser;
    }
}
