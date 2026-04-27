<?php

declare(strict_types=1);

namespace App\Application\Document\Query;

use App\Domain\Document\ValueObject\DocumentId;

final readonly class DownloadDocumentQuery
{
    public function __construct(
        public DocumentId $documentId,
        public ?int $versionNumber = null,
    ) {}
}
