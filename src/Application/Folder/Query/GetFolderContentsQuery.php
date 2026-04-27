<?php

declare(strict_types=1);

namespace App\Application\Folder\Query;

use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\User\ValueObject\UserId;

final readonly class GetFolderContentsQuery
{
    public function __construct(
        public FolderId  $folderId,
        public int       $page = 1,
        public int       $pageSize = 25,
        public ?UserId   $userId = null,
        public bool      $isAdmin = false,
    ) {}
}
