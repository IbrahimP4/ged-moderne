<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Entity\FolderPermission;
use App\Domain\Folder\Repository\FolderPermissionRepositoryInterface;
use App\Domain\Folder\ValueObject\PermissionLevel;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFolderPermissionRepository implements FolderPermissionRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** @return list<FolderPermission> */
    public function findByFolder(Folder $folder): array
    {
        /** @var list<FolderPermission> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('fp')
            ->from(FolderPermission::class, 'fp')
            ->join('fp.user', 'u')
            ->where('fp.folder = :folder')
            ->orderBy('u.username', 'ASC')
            ->setParameter('folder', $folder)
            ->getQuery()
            ->getResult();
    }

    public function findByFolderAndUser(Folder $folder, User $user): ?FolderPermission
    {
        /** @var FolderPermission|null */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('fp')
            ->from(FolderPermission::class, 'fp')
            ->where('fp.folder = :folder')
            ->andWhere('fp.user = :user')
            ->setParameter('folder', $folder)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasAccess(Folder $folder, User $user, PermissionLevel $required = PermissionLevel::READ): bool
    {
        // Admins et propriétaires ont toujours accès
        if ($user->isAdmin() || $folder->getOwner()->getId()->equals($user->getId())) {
            return true;
        }

        // Dossier non restreint → tout le monde peut lire
        if (!$folder->isRestricted()) {
            return true;
        }

        // Vérifier la permission explicite
        $permission = $this->findByFolderAndUser($folder, $user);

        if ($permission === null) {
            return false;
        }

        // READ est suffisant pour lire ; WRITE requis pour écrire
        if ($required === PermissionLevel::WRITE) {
            return $permission->getLevel()->canWrite();
        }

        return true; // READ: toute permission suffit
    }

    public function save(FolderPermission $permission): void
    {
        $this->entityManager->persist($permission);
        $this->entityManager->flush();
    }

    public function remove(FolderPermission $permission): void
    {
        $this->entityManager->remove($permission);
        $this->entityManager->flush();
    }
}
