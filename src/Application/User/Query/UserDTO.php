<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Domain\User\Entity\User;

final readonly class UserDTO
{
    public function __construct(
        public string $id,
        public string $username,
        public string $email,
        public bool $isAdmin,
        public bool $isActive,
        public string $createdAt,
    ) {}

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId()->getValue(),
            username: $user->getUsername(),
            email: $user->getEmail(),
            isAdmin: $user->isAdmin(),
            isActive: $user->isActive(),
            createdAt: $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
