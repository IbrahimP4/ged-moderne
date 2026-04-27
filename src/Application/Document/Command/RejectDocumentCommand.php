<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\User\ValueObject\UserId;

final readonly class RejectDocumentCommand
{
    public function __construct(
        public DocumentId $documentId,
        public UserId $rejectedBy,
        public string $reason,
    ) {}
}
