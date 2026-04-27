<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\User\ValueObject\UserId;

final readonly class DeleteDocumentCommand
{
    public function __construct(
        public DocumentId $documentId,
        public UserId $deletedBy,
    ) {}
}
