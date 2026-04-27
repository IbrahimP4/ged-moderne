<?php

declare(strict_types=1);

namespace App\Application\Messaging\Command;

use App\Domain\User\ValueObject\UserId;

final readonly class SendMessageCommand
{
    public function __construct(
        public UserId  $senderId,
        public UserId  $recipientId,
        public string  $content,
        public ?string $documentId    = null,
        public ?string $documentTitle = null,
    ) {}
}
