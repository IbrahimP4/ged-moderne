<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class MoveDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function __invoke(MoveDocumentCommand $command): void
    {
        $document = $this->documentRepository->findById($command->documentId);

        if ($document === null) {
            throw new DocumentNotFoundException($command->documentId);
        }

        $targetFolder = $this->folderRepository->findById($command->targetFolderId);

        if ($targetFolder === null) {
            throw new FolderNotFoundException($command->targetFolderId);
        }

        $user = $this->userRepository->findById($command->movedBy);

        if ($user === null) {
            throw new \DomainException('Utilisateur introuvable.');
        }

        if (! $document->isOwnedBy($user) && ! $user->isAdmin()) {
            throw new \DomainException('Vous n\'êtes pas autorisé à déplacer ce document.');
        }

        $document->moveToFolder($targetFolder);

        $this->documentRepository->save($document);
    }
}
