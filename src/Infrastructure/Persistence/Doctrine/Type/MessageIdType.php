<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Messaging\ValueObject\MessageId;

final class MessageIdType extends AbstractUuidType
{
    public function getName(): string
    {
        return 'message_id';
    }

    protected function fromString(string $value): MessageId
    {
        return MessageId::fromString($value);
    }
}
