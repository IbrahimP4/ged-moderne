<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Folder\ValueObject\FolderId;

final class FolderIdType extends AbstractUuidType
{
    public function getName(): string
    {
        return 'folder_id';
    }

    protected function fromString(string $value): FolderId
    {
        return FolderId::fromString($value);
    }
}
