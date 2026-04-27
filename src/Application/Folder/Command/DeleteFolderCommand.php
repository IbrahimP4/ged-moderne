<?php

declare(strict_types=1);

namespace App\Application\Folder\Command;

use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\User\ValueObject\UserId;

final readonly class DeleteFolderCommand
{
    public function __construct(
        public FolderId $folderId,
        public UserId $deletedBy,
    ) {}
}
