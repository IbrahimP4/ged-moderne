<?php

declare(strict_types=1);

namespace App\Application\Document\Query;

use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Folder\ValueObject\FolderId;

final class SearchDocumentsHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly FolderRepositoryInterface $folderRepository,
    ) {}

    /**
     * @return list<SearchResultDTO>
     */
    public function __invoke(SearchDocumentsQuery $query): array
    {
        $q = trim($query->q);

        if ($q === '') {
            return [];
        }

        $folder = null;

        if ($query->folderId !== null) {
            try {
                $folderId = FolderId::fromString($query->folderId);
            } catch (\InvalidArgumentException) {
                return [];
            }

            $folder = $this->folderRepository->findById($folderId);
        }

        // search() retourne maintenant des tableau enrichis {document, snippet, matchedInContent}
        $results = $this->documentRepository->search($q, $folder, $query->limit, $query->status, $query->tags);

        return array_map(
            static fn (array $result) => SearchResultDTO::fromSearchResult($result),
            $results,
        );
    }
}
