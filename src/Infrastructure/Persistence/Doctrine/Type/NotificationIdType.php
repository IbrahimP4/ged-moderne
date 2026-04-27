<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Notification\ValueObject\NotificationId;

final class NotificationIdType extends AbstractUuidType
{
    public function getName(): string
    {
        return 'notification_id';
    }

    protected function fromString(string $value): NotificationId
    {
        return NotificationId::fromString($value);
    }
}
