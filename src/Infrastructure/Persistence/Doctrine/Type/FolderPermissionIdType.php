<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Folder\ValueObject\FolderPermissionId;

final class FolderPermissionIdType extends AbstractUuidType
{
    public function getName(): string
    {
        return 'folder_permission_id';
    }

    protected function fromString(string $value): FolderPermissionId
    {
        return FolderPermissionId::fromString($value);
    }
}
