<?php

declare(strict_types=1);

namespace App\Domain\Document\Repository;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Folder\Entity\Folder;
use App\Domain\User\Entity\User;

interface DocumentRepositoryInterface
{
    public function findById(DocumentId $id): ?Document;

    /** @return list<Document> */
    public function findByFolder(Folder $folder, int $limit = 50, int $offset = 0): array;

    /** @return list<Document> */
    public function findByOwner(User $owner): array;

    /** @return list<Document> — documents supprimés (corbeille) */
    public function findDeleted(): array;

    /** @return list<Document> — favoris de l'utilisateur */
    public function findFavorites(User $user): array;

    public function isFavorite(Document $document, User $user): bool;

    public function addFavorite(Document $document, User $user): void;

    public function removeFavorite(Document $document, User $user): void;

    public function save(Document $document): void;

    public function delete(Document $document): void;

    public function count(): int;

    public function countByFolder(Folder $folder): int;

    public function countByStatus(string $status): int;

    /**
     * @return list<array{date: string, count: int}>
     */
    public function countUploadsByDay(int $days = 30): array;

    /**
     * Recherche full-text sur le titre ET le contenu des documents.
     *
     * @param list<string> $tags
     * @return list<array{document: Document, snippet: string|null, matchedInContent: bool}>
     */
    public function search(string $query, ?Folder $folder = null, int $limit = 50, ?string $status = null, array $tags = []): array;
}
