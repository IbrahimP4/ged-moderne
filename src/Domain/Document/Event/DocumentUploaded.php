<?php

declare(strict_types=1);

namespace App\Domain\Document\Event;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\User\ValueObject\UserId;

/**
 * Événement émis quand un nouveau document est uploadé.
 *
 * Readonly : immuable par construction — un event ne se modifie jamais.
 * Les consumers (Messenger, listeners) reçoivent une copie figée de ce qui s'est passé.
 */
final readonly class DocumentUploaded
{
    public function __construct(
        public DocumentId $documentId,
        public FolderId $folderId,
        public UserId $uploadedBy,
        public string $title,
        public string $mimeType,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
