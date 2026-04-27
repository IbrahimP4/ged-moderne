<?php

declare(strict_types=1);

namespace App\Application\Folder\Query;

use App\Application\Document\Query\DocumentDTO;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderPermissionRepositoryInterface;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Folder\ValueObject\PermissionLevel;
use App\Domain\User\Repository\UserRepositoryInterface;

final class GetFolderContentsHandler
{
    public function __construct(
        private readonly FolderRepositoryInterface           $folderRepository,
        private readonly DocumentRepositoryInterface         $documentRepository,
        private readonly FolderPermissionRepositoryInterface $permissionRepository,
        private readonly UserRepositoryInterface             $userRepository,
    ) {}

    public function __invoke(GetFolderContentsQuery $query): FolderContentsDTO
    {
        $folder = $this->folderRepository->findById($query->folderId);

        if ($folder === null) {
            throw new FolderNotFoundException($query->folderId);
        }

        // Résoudre l'utilisateur courant pour le filtrage des sous-dossiers
        $currentUser = null;
        if ($query->userId !== null) {
            $currentUser = $this->userRepository->findById($query->userId);
        }

        $offset     = ($query->page - 1) * $query->pageSize;
        $allSubFolders = $this->folderRepository->findChildren($folder);

        // Filtrer les sous-dossiers selon les permissions
        $visibleSubFolders = array_filter(
            $allSubFolders,
            function ($sub) use ($currentUser, $query): bool {
                if ($query->isAdmin) {
                    return true; // Les admins voient tout
                }
                if (!$sub->isRestricted()) {
                    return true; // Dossier public → visible
                }
                if ($currentUser === null) {
                    return false;
                }
                return $this->permissionRepository->hasAccess($sub, $currentUser, PermissionLevel::READ);
            },
        );

        $documents = $this->documentRepository->findByFolder($folder, $query->pageSize, $offset);
        $total     = $this->documentRepository->countByFolder($folder);

        return new FolderContentsDTO(
            folder: FolderDTO::fromEntity($folder),
            subFolders: array_map(
                static fn ($f) => FolderDTO::fromEntity($f),
                array_values($visibleSubFolders),
            ),
            documents: array_map(
                static fn ($d) => DocumentDTO::fromEntity($d),
                $documents,
            ),
            totalDocuments: $total,
            currentPage: $query->page,
            pageSize: $query->pageSize,
        );
    }
}
