<?php

declare(strict_types=1);

namespace App\Domain\Folder\Repository;

use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Entity\FolderPermission;
use App\Domain\Folder\ValueObject\PermissionLevel;
use App\Domain\User\Entity\User;

interface FolderPermissionRepositoryInterface
{
    /** @return list<FolderPermission> */
    public function findByFolder(Folder $folder): array;

    public function findByFolderAndUser(Folder $folder, User $user): ?FolderPermission;

    /**
     * Vérifie si un utilisateur a au moins le niveau de permission donné sur un dossier.
     * Retourne toujours true pour les admins et le propriétaire du dossier.
     * Retourne true si le dossier n'est pas restreint.
     */
    public function hasAccess(Folder $folder, User $user, PermissionLevel $required = PermissionLevel::READ): bool;

    public function save(FolderPermission $permission): void;

    public function remove(FolderPermission $permission): void;
}
