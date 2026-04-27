<?php

declare(strict_types=1);

namespace App\Application\Document\Query;

use App\Domain\Document\Entity\DocumentVersion;

final readonly class DocumentVersionDTO
{
    public function __construct(
        public string $id,
        public int $versionNumber,
        public string $mimeType,
        public int $fileSizeBytes,
        public string $fileSizeHuman,
        public string $originalFilename,
        public string $uploadedByUsername,
        public ?string $comment,
        public string $createdAt,
    ) {}

    public static function fromEntity(DocumentVersion $version): self
    {
        return new self(
            id: $version->getId()->getValue(),
            versionNumber: $version->getVersionNumber()->getValue(),
            mimeType: $version->getMimeType()->getValue(),
            fileSizeBytes: $version->getFileSize()->getBytes(),
            fileSizeHuman: $version->getFileSize()->humanReadable(),
            originalFilename: $version->getOriginalFilename(),
            uploadedByUsername: $version->getUploadedBy()->getUsername(),
            comment: $version->getComment(),
            createdAt: $version->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
