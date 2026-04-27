<?php

declare(strict_types=1);

namespace App\Domain\Document\Event;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\User\ValueObject\UserId;

/**
 * Émis à chaque transition de statut d'un document.
 * Couvre : submitForReview, approve, reject, archive.
 */
final readonly class DocumentStatusChanged
{
    public function __construct(
        public DocumentId $documentId,
        public DocumentStatus $previousStatus,
        public DocumentStatus $newStatus,
        public UserId $changedBy,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
