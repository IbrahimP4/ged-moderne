<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\User\ValueObject\UserId;

final class UserIdType extends AbstractUuidType
{
    public function getName(): string
    {
        return 'user_id';
    }

    protected function fromString(string $value): UserId
    {
        return UserId::fromString($value);
    }
}
