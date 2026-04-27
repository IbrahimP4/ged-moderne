<?php

declare(strict_types=1);

namespace App\Application\Document\Query;

use App\Domain\Document\Entity\Document;

final readonly class DocumentDTO
{
    /**
     * @param list<DocumentVersionDTO> $versions
     * @param list<string>             $tags
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $status,
        public string $statusLabel,
        public string $folderId,
        public string $folderName,
        public string $ownerUsername,
        public int $versionCount,
        public ?DocumentVersionDTO $latestVersion,
        public array $versions,
        public ?string $comment,
        public string $createdAt,
        public string $updatedAt,
        public array $tags = [],
    ) {}

    public static function fromEntity(Document $document, bool $withAllVersions = false): self
    {
        $latest = $document->getLatestVersion();

        $versions = $withAllVersions
            ? array_values(array_map(
                static fn ($v) => DocumentVersionDTO::fromEntity($v),
                $document->getVersions()->toArray(),
            ))
            : [];

        return new self(
            id: $document->getId()->getValue(),
            title: $document->getTitle(),
            status: $document->getStatus()->value,
            statusLabel: $document->getStatus()->label(),
            folderId: $document->getFolder()->getId()->getValue(),
            folderName: $document->getFolder()->getName(),
            ownerUsername: $document->getOwner()->getUsername(),
            versionCount: $document->getVersions()->count(),
            latestVersion: $latest !== null ? DocumentVersionDTO::fromEntity($latest) : null,
            versions: $versions,
            comment: $document->getComment(),
            createdAt: $document->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $document->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            tags: $document->getTags(),
        );
    }
}
