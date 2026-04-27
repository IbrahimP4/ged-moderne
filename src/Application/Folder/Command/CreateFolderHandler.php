<?php

declare(strict_types=1);

namespace App\Application\Folder\Command;

use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\User\Repository\UserRepositoryInterface;

final class CreateFolderHandler
{
    public function __construct(
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function __invoke(CreateFolderCommand $command): FolderId
    {
        $owner = $this->userRepository->findById($command->createdBy);
        if ($owner === null) {
            throw new \DomainException(sprintf(
                'Utilisateur introuvable : "%s".',
                $command->createdBy->getValue(),
            ));
        }

        $parent = null;
        if ($command->parentFolderId !== null) {
            $parent = $this->folderRepository->findById($command->parentFolderId);
            if ($parent === null) {
                throw new FolderNotFoundException($command->parentFolderId);
            }
        }

        $folder = Folder::create(
            name: $command->name,
            owner: $owner,
            parent: $parent,
            comment: $command->comment,
        );

        $this->folderRepository->save($folder);

        return $folder->getId();
    }
}
