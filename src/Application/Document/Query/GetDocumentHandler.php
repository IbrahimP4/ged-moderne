<?php

declare(strict_types=1);

namespace App\Application\Document\Query;

use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;

final class GetDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function __invoke(GetDocumentQuery $query): DocumentDTO
    {
        $document = $this->documentRepository->findById($query->documentId);

        if ($document === null) {
            throw new DocumentNotFoundException($query->documentId);
        }

        return DocumentDTO::fromEntity($document, $query->withAllVersions);
    }
}
