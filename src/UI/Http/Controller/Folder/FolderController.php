<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Folder;

use App\Application\Folder\Command\CreateFolderCommand;
use App\Application\Folder\Command\CreateFolderHandler;
use App\Application\Folder\Command\DeleteFolderCommand;
use App\Application\Folder\Command\DeleteFolderHandler;
use App\Application\Folder\Command\RenameFolderCommand;
use App\Application\Folder\Command\RenameFolderHandler;
use App\Application\Folder\Query\GetFolderContentsHandler;
use App\Application\Folder\Query\GetFolderContentsQuery;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderPermissionRepositoryInterface;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\Folder\ValueObject\PermissionLevel;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/folders', name: 'api_folders_')]
#[IsGranted('ROLE_USER')]
final class FolderController extends AbstractController
{
    public function __construct(
        private readonly GetFolderContentsHandler            $getFolderContentsHandler,
        private readonly CreateFolderHandler                 $createFolderHandler,
        private readonly RenameFolderHandler                 $renameFolderHandler,
        private readonly DeleteFolderHandler                 $deleteFolderHandler,
        private readonly FolderRepositoryInterface           $folderRepository,
        private readonly FolderPermissionRepositoryInterface $permissionRepository,
        private readonly DocumentRepositoryInterface         $documentRepository,
        private readonly DocumentStorageInterface            $documentStorage,
    ) {}

    #[Route('', name: 'root', methods: ['GET'])]
    public function root(Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        $rootFolder = $this->folderRepository->findRoot();
        if ($rootFolder === null) {
            return $this->json(['error' => 'Aucun dossier racine configuré.'], Response::HTTP_NOT_FOUND);
        }

        $domainUser = $user->getDomainUser();
        if (!$this->permissionRepository->hasAccess($rootFolder, $domainUser, PermissionLevel::READ)) {
            return $this->json(['error' => 'Accès refusé à ce dossier.'], Response::HTTP_FORBIDDEN);
        }

        $page     = max(1, $request->query->getInt('page', 1));
        $pageSize = min(100, max(10, $request->query->getInt('page_size', 25)));

        return $this->json(($this->getFolderContentsHandler)(
            new GetFolderContentsQuery(
                folderId: $rootFolder->getId(),
                page: $page,
                pageSize: $pageSize,
                userId: $domainUser->getId(),
                isAdmin: $domainUser->isAdmin(),
            ),
        ));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id, Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $folderId = FolderId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de dossier invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $folder = $this->folderRepository->findById($folderId);
        if ($folder === null) {
            return $this->json(['error' => 'Dossier introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $domainUser = $user->getDomainUser();
        if (!$this->permissionRepository->hasAccess($folder, $domainUser, PermissionLevel::READ)) {
            return $this->json(['error' => 'Accès refusé à ce dossier.'], Response::HTTP_FORBIDDEN);
        }

        $page     = max(1, $request->query->getInt('page', 1));
        $pageSize = min(100, max(10, $request->query->getInt('page_size', 25)));

        try {
            $dto = ($this->getFolderContentsHandler)(
                new GetFolderContentsQuery(
                    folderId: $folderId,
                    page: $page,
                    pageSize: $pageSize,
                    userId: $domainUser->getId(),
                    isAdmin: $domainUser->isAdmin(),
                ),
            );
        } catch (FolderNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return $this->json($dto);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return $this->json(['error' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $name = trim(\is_string($body['name'] ?? null) ? $body['name'] : '');
        if ($name === '') {
            return $this->json(['error' => 'Le champ "name" est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (strlen($name) > 255) {
            return $this->json(['error' => '"name" ne peut pas dépasser 255 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $parentIdRaw = $body['parent_id'] ?? null;
        $parentId    = null;
        if ($parentIdRaw !== null) {
            try {
                $parentId = FolderId::fromString(\is_string($parentIdRaw) ? $parentIdRaw : '');
            } catch (\InvalidArgumentException) {
                return $this->json(['error' => '"parent_id" doit être un UUID valide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $parentFolder = $this->folderRepository->findById($parentId);
            if ($parentFolder !== null) {
                $domainUser = $user->getDomainUser();
                if (!$this->permissionRepository->hasAccess($parentFolder, $domainUser, PermissionLevel::WRITE)) {
                    return $this->json(['error' => 'Droits insuffisants pour créer un sous-dossier ici.'], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $comment = isset($body['comment']) && \is_string($body['comment']) ? $body['comment'] : null;

        try {
            $folderId = ($this->createFolderHandler)(new CreateFolderCommand(
                name: $name,
                createdBy: $user->getDomainUser()->getId(),
                parentFolderId: $parentId,
                comment: $comment,
            ));
        } catch (FolderNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json(
            ['id' => $folderId->getValue(), 'message' => 'Dossier créé avec succès.'],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'rename', methods: ['PATCH'])]
    public function rename(string $id, Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $folderId = FolderId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de dossier invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $body = json_decode($request->getContent(), true);
        $name = trim(\is_array($body) && \is_string($body['name'] ?? null) ? $body['name'] : '');
        if ($name === '') {
            return $this->json(['error' => 'Le champ "name" est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            ($this->renameFolderHandler)(new RenameFolderCommand(
                folderId: $folderId,
                renamedBy: $user->getDomainUser()->getId(),
                newName: $name,
            ));
        } catch (FolderNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/export', name: 'export', methods: ['GET'])]
    public function export(string $id, #[CurrentUser] SecurityUser $user): Response
    {
        try {
            $folderId = FolderId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de dossier invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $folder = $this->folderRepository->findById($folderId);
        if ($folder === null) {
            return $this->json(['error' => 'Dossier introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $domainUser = $user->getDomainUser();
        if (!$this->permissionRepository->hasAccess($folder, $domainUser, PermissionLevel::READ)) {
            return $this->json(['error' => 'Accès refusé à ce dossier.'], Response::HTTP_FORBIDDEN);
        }

        $documents = $this->documentRepository->findByFolder($folder, 500, 0);
        $folderName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $folder->getName());
        $zipFilename = $folderName . '_' . date('Ymd') . '.zip';

        $response = new StreamedResponse(function () use ($documents) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'ged_zip_');
            $zip     = new \ZipArchive();
            $zip->open($tmpFile, \ZipArchive::OVERWRITE);

            foreach ($documents as $document) {
                $version = $document->getLatestVersion();
                if ($version === null) {
                    continue;
                }
                try {
                    $stream = $this->documentStorage->read($version->getStoragePath());
                    if (is_resource($stream)) {
                        $contents = stream_get_contents($stream);
                        fclose($stream);
                    } else {
                        $contents = (string) $stream;
                    }
                    $ext      = pathinfo($version->getStoragePath()->getValue(), PATHINFO_EXTENSION);
                    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $document->getTitle());
                    if ($ext && !str_ends_with($filename, '.' . $ext)) {
                        $filename .= '.' . $ext;
                    }
                    $zip->addFromString($filename, $contents);
                } catch (\Throwable) {
                    // skip files that cannot be read
                }
            }

            $zip->close();
            readfile($tmpFile);
            unlink($tmpFile);
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $zipFilename . '"');

        return $response;
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $folderId = FolderId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de dossier invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            ($this->deleteFolderHandler)(new DeleteFolderCommand(
                folderId: $folderId,
                deletedBy: $user->getDomainUser()->getId(),
            ));
        } catch (FolderNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
