<?php

declare(strict_types=1);

namespace App\Domain\Document\Event;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\VersionNumber;
use App\Domain\User\ValueObject\UserId;

final readonly class DocumentVersionAdded
{
    public function __construct(
        public DocumentId $documentId,
        public VersionNumber $versionNumber,
        public UserId $uploadedBy,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
