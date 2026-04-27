<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\User\ValueObject\UserId;

final readonly class UpdateDocumentTagsCommand
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public DocumentId $documentId,
        public UserId $updatedBy,
        public array $tags,
    ) {}
}
