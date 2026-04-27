<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\User\ValueObject\UserId;

final readonly class MoveDocumentCommand
{
    public function __construct(
        public DocumentId $documentId,
        public FolderId $targetFolderId,
        public UserId $movedBy,
    ) {}
}
