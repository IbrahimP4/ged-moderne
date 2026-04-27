<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Domain\User\ValueObject\UserId;

final readonly class ChangeUserRoleCommand
{
    public function __construct(
        public UserId $targetUserId,
        public bool   $makeAdmin,
        public UserId $changedBy,
    ) {}
}
