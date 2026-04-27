<?php

declare(strict_types=1);

namespace App\Application\Folder\Query;

use App\Domain\Folder\Entity\Folder;

final readonly class FolderDTO
{
    public function __construct(
        public string  $id,
        public string  $name,
        public ?string $comment,
        public ?string $parentId,
        public string  $ownerUsername,
        public string  $fullPath,
        public string  $createdAt,
        public bool    $restricted,
    ) {}

    public static function fromEntity(Folder $folder): self
    {
        return new self(
            id: $folder->getId()->getValue(),
            name: $folder->getName(),
            comment: $folder->getComment(),
            parentId: $folder->getParent()?->getId()->getValue(),
            ownerUsername: $folder->getOwner()->getUsername(),
            fullPath: $folder->getFullPath(),
            createdAt: $folder->getCreatedAt()->format(\DateTimeInterface::ATOM),
            restricted: $folder->isRestricted(),
        );
    }
}
