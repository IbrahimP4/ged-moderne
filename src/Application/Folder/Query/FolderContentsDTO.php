<?php

declare(strict_types=1);

namespace App\Application\Folder\Query;

use App\Application\Document\Query\DocumentDTO;

final readonly class FolderContentsDTO
{
    /**
     * @param list<FolderDTO>   $subFolders
     * @param list<DocumentDTO> $documents
     */
    public function __construct(
        public FolderDTO $folder,
        public array $subFolders,
        public array $documents,
        public int $totalDocuments,
        public int $currentPage,
        public int $pageSize,
    ) {}
}
