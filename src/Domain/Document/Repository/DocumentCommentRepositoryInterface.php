<?php

declare(strict_types=1);

namespace App\Domain\Document\Repository;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Entity\DocumentComment;

interface DocumentCommentRepositoryInterface
{
    /** @return list<DocumentComment> */
    public function findByDocument(Document $document): array;

    public function findById(string $id): ?DocumentComment;

    public function save(DocumentComment $comment): void;

    public function remove(DocumentComment $comment): void;
}
