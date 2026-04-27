<?php

declare(strict_types=1);

namespace App\Application\Folder\Command;

use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderPermissionRepositoryInterface;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class RemoveFolderPermissionHandler
{
    public function __construct(
        private readonly FolderRepositoryInterface           $folderRepository,
        private readonly FolderPermissionRepositoryInterface $permissionRepository,
        private readonly UserRepositoryInterface             $userRepository,
    ) {}

    public function __invoke(RemoveFolderPermissionCommand $command): void
    {
        $folder = $this->folderRepository->findById($command->folderId);
        if ($folder === null) {
            throw new FolderNotFoundException($command->folderId);
        }

        $targetUser = $this->userRepository->findById($command->targetUserId);
        if ($targetUser === null) {
            throw new \DomainException('Utilisateur cible introuvable.');
        }

        $permission = $this->permissionRepository->findByFolderAndUser($folder, $targetUser);
        if ($permission === null) {
            return; // Idempotent : pas de permission à supprimer
        }

        $this->permissionRepository->remove($permission);
    }
}
