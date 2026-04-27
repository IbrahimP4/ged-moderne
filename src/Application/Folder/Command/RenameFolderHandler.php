<?php

declare(strict_types=1);

namespace App\Application\Folder\Command;

use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderRepositoryInterface;

final class RenameFolderHandler
{
    public function __construct(
        private readonly FolderRepositoryInterface $folderRepository,
    ) {}

    public function __invoke(RenameFolderCommand $command): void
    {
        $folder = $this->folderRepository->findById($command->folderId);

        if ($folder === null) {
            throw new FolderNotFoundException($command->folderId);
        }

        $newName = trim($command->newName);

        if ($newName === '') {
            throw new \DomainException('Le nom du dossier ne peut pas être vide.');
        }

        if (strlen($newName) > 255) {
            throw new \DomainException('Le nom du dossier ne peut pas dépasser 255 caractères.');
        }

        $folder->rename($newName);

        $this->folderRepository->save($folder);
    }
}
