<?php

declare(strict_types=1);

namespace App\Application\Folder\Command;

use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderRepositoryInterface;

final class SetFolderRestrictedHandler
{
    public function __construct(
        private readonly FolderRepositoryInterface $folderRepository,
    ) {}

    public function __invoke(SetFolderRestrictedCommand $command): void
    {
        $folder = $this->folderRepository->findById($command->folderId);
        if ($folder === null) {
            throw new FolderNotFoundException($command->folderId);
        }

        $folder->setRestricted($command->restricted);
        $this->folderRepository->save($folder);
    }
}
