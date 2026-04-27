<?php

declare(strict_types=1);

namespace App\Application\Folder\Command;

use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderRepositoryInterface;

final class DeleteFolderHandler
{
    public function __construct(
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function __invoke(DeleteFolderCommand $command): void
    {
        $folder = $this->folderRepository->findById($command->folderId);

        if ($folder === null) {
            throw new FolderNotFoundException($command->folderId);
        }

        if ($folder->isRoot()) {
            throw new \DomainException('Impossible de supprimer le dossier racine.');
        }

        // Vérifie que le dossier est vide (pas d'enfants ni de documents)
        $children  = $this->folderRepository->findChildren($folder);
        $documents = $this->documentRepository->findByFolder($folder, 1, 0);

        if (count($children) > 0 || count($documents) > 0) {
            throw new \DomainException('Impossible de supprimer un dossier non vide. Supprimez d\'abord son contenu.');
        }

        $this->folderRepository->delete($folder);
    }
}
