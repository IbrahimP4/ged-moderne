<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Admin;

use App\Application\Folder\Command\RemoveFolderPermissionCommand;
use App\Application\Folder\Command\RemoveFolderPermissionHandler;
use App\Application\Folder\Command\SetFolderPermissionCommand;
use App\Application\Folder\Command\SetFolderPermissionHandler;
use App\Application\Folder\Command\SetFolderRestrictedCommand;
use App\Application\Folder\Command\SetFolderRestrictedHandler;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderPermissionRepositoryInterface;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\Folder\ValueObject\PermissionLevel;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/folders', name: 'api_admin_folders_')]
#[IsGranted('ROLE_ADMIN')]
final class FolderPermissionController extends AbstractController
{
    public function __construct(
        private readonly FolderRepositoryInterface           $folderRepository,
        private readonly FolderPermissionRepositoryInterface $permissionRepository,
        private readonly SetFolderPermissionHandler          $setPermissionHandler,
        private readonly RemoveFolderPermissionHandler       $removePermissionHandler,
        private readonly SetFolderRestrictedHandler          $setRestrictedHandler,
    ) {}

    /**
     * GET /api/admin/folders/{id}/permissions
     * Retourne les permissions du dossier + le statut restricted.
     */
    #[Route('/{id}/permissions', name: 'permissions_list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        $folder = $this->resolveFolder($id);
        if ($folder instanceof JsonResponse) return $folder;

        $permissions = $this->permissionRepository->findByFolder($folder);

        return $this->json([
            'folderId'    => $folder->getId()->getValue(),
            'folderName'  => $folder->getName(),
            'restricted'  => $folder->isRestricted(),
            'permissions' => array_map(
                static fn ($p) => $p->toArray(),
                $permissions,
            ),
        ]);
    }

    /**
     * POST /api/admin/folders/{id}/permissions
     * Body: { "user_id": "uuid", "level": "read"|"write" }
     */
    #[Route('/{id}/permissions', name: 'permissions_set', methods: ['POST'])]
    public function set(string $id, Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        $folder = $this->resolveFolder($id);
        if ($folder instanceof JsonResponse) return $folder;

        $body = json_decode($request->getContent(), true);

        $userIdRaw = \is_array($body) && \is_string($body['user_id'] ?? null) ? $body['user_id'] : '';
        $levelRaw  = \is_array($body) && \is_string($body['level']   ?? null) ? $body['level']   : '';

        try {
            $targetUserId = UserId::fromString($userIdRaw);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => '"user_id" doit être un UUID valide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $level = PermissionLevel::tryFrom($levelRaw);
        if ($level === null) {
            return $this->json(['error' => '"level" doit être "read" ou "write".'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            ($this->setPermissionHandler)(new SetFolderPermissionCommand(
                folderId: $folder->getId(),
                targetUserId: $targetUserId,
                level: $level,
                grantedBy: $user->getDomainUser()->getId(),
            ));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['message' => 'Permission accordée.'], Response::HTTP_CREATED);
    }

    /**
     * DELETE /api/admin/folders/{id}/permissions/{userId}
     */
    #[Route('/{id}/permissions/{userId}', name: 'permissions_remove', methods: ['DELETE'])]
    public function remove(string $id, string $userId): JsonResponse
    {
        $folder = $this->resolveFolder($id);
        if ($folder instanceof JsonResponse) return $folder;

        try {
            $targetUserId = UserId::fromString($userId);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant utilisateur invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            ($this->removePermissionHandler)(new RemoveFolderPermissionCommand(
                folderId: $folder->getId(),
                targetUserId: $targetUserId,
            ));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * PATCH /api/admin/folders/{id}/restrict
     * Body: { "restricted": true|false }
     */
    #[Route('/{id}/restrict', name: 'restrict', methods: ['PATCH'])]
    public function restrict(string $id, Request $request): JsonResponse
    {
        $folder = $this->resolveFolder($id);
        if ($folder instanceof JsonResponse) return $folder;

        $body       = json_decode($request->getContent(), true);
        $restricted = \is_array($body) && isset($body['restricted']) ? (bool) $body['restricted'] : null;

        if ($restricted === null) {
            return $this->json(['error' => 'Le champ "restricted" (booléen) est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            ($this->setRestrictedHandler)(new SetFolderRestrictedCommand(
                folderId: $folder->getId(),
                restricted: $restricted,
            ));
        } catch (FolderNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['message' => $restricted ? 'Dossier restreint.' : 'Dossier ouvert à tous.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveFolder(string $id): \App\Domain\Folder\Entity\Folder|JsonResponse
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

        return $folder;
    }
}
