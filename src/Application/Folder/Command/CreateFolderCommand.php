<?php

declare(strict_types=1);

namespace App\Application\Folder\Command;

use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\User\ValueObject\UserId;

final readonly class CreateFolderCommand
{
    public function __construct(
        public string $name,
        public UserId $createdBy,
        public ?FolderId $parentFolderId = null,
        public ?string $comment = null,
    ) {}
}
