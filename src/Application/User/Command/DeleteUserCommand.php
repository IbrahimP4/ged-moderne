<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Domain\User\ValueObject\UserId;

final readonly class DeleteUserCommand
{
    public function __construct(
        public UserId $targetUserId,
        public UserId $deletedBy,
    ) {}
}
