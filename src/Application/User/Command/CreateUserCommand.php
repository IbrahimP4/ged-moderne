<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Domain\User\ValueObject\UserId;

final readonly class CreateUserCommand
{
    public function __construct(
        public string $username,
        public string $email,
        public string $plainPassword,
        public bool $isAdmin,
        public UserId $createdBy,
    ) {}
}
