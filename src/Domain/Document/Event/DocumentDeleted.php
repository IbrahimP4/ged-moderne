<?php

declare(strict_types=1);

namespace App\Domain\Document\Event;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\User\ValueObject\UserId;

final readonly class DocumentDeleted
{
    public function __construct(
        public DocumentId $documentId,
        public UserId $deletedBy,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
