<?php

declare(strict_types=1);

namespace App\Domain\Folder\Repository;

use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\ValueObject\FolderId;

interface FolderRepositoryInterface
{
    public function findById(FolderId $id): ?Folder;

    public function findRoot(): ?Folder;

    /** @return list<Folder> */
    public function findChildren(Folder $parent): array;

    public function save(Folder $folder): void;

    public function delete(Folder $folder): void;

    public function count(): int;
}
