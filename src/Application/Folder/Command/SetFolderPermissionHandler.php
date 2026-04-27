<?php

declare(strict_types=1);

namespace App\Application\Folder\Command;

use App\Domain\Folder\Entity\FolderPermission;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderPermissionRepositoryInterface;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class SetFolderPermissionHandler
{
    public function __construct(
        private readonly FolderRepositoryInterface           $folderRepository,
        private readonly FolderPermissionRepositoryInterface $permissionRepository,
        private readonly UserRepositoryInterface             $userRepository,
    ) {}

    public function __invoke(SetFolderPermissionCommand $command): void
    {
        $folder = $this->folderRepository->findById($command->folderId);
        if ($folder === null) {
            throw new FolderNotFoundException($command->folderId);
        }

        $targetUser = $this->userRepository->findById($command->targetUserId);
        if ($targetUser === null) {
            throw new \DomainException('Utilisateur cible introuvable.');
        }

        $grantor = $this->userRepository->findById($command->grantedBy);

        // Mettre à jour si déjà existant, sinon créer
        $existing = $this->permissionRepository->findByFolderAndUser($folder, $targetUser);

        if ($existing !== null) {
            $existing->updateLevel($command->level);
            $this->permissionRepository->save($existing);
        } else {
            $permission = FolderPermission::grant($folder, $targetUser, $command->level, $grantor);
            $this->permissionRepository->save($permission);
        }
    }
}
