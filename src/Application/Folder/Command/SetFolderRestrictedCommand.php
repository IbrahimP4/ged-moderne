<?php

declare(strict_types=1);

namespace App\Application\Folder\Command;

use App\Domain\Folder\ValueObject\FolderId;

final readonly class SetFolderRestrictedCommand
{
    public function __construct(
        public FolderId $folderId,
        public bool     $restricted,
    ) {}
}
