<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Document;

use App\Application\Document\Command\ApproveDocumentCommand;
use App\Application\Document\Command\ApproveDocumentHandler;
use App\Application\Document\Command\DeleteDocumentCommand;
use App\Application\Document\Command\DeleteDocumentHandler;
use App\Application\Document\Command\PermanentDeleteDocumentCommand;
use App\Application\Document\Command\PermanentDeleteDocumentHandler;
use App\Application\Document\Command\RejectDocumentCommand;
use App\Application\Document\Command\RejectDocumentHandler;
use App\Application\Document\Command\RestoreDocumentCommand;
use App\Application\Document\Command\RestoreDocumentHandler;
use App\Application\Document\Command\SubmitForReviewCommand;
use App\Application\Document\Command\SubmitForReviewHandler;
use App\Application\Document\Command\MoveDocumentCommand;
use App\Application\Document\Command\MoveDocumentHandler;
use App\Application\Document\Command\RenameDocumentCommand;
use App\Application\Document\Command\RenameDocumentHandler;
use App\Application\Document\Command\UpdateDocumentTagsCommand;
use App\Application\Document\Command\UpdateDocumentTagsHandler;
use App\Domain\Document\Entity\DocumentComment;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentCommentRepositoryInterface;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\ValueObject\FolderId;
use App\Application\Document\Query\GetDocumentHandler;
use App\Application\Document\Query\GetDocumentQuery;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/documents', name: 'api_documents_')]
#[IsGranted('ROLE_USER')]
final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly GetDocumentHandler              $getDocumentHandler,
        private readonly DeleteDocumentHandler           $deleteDocumentHandler,
        private readonly RestoreDocumentHandler          $restoreDocumentHandler,
        private readonly PermanentDeleteDocumentHandler  $permanentDeleteDocumentHandler,
        private readonly SubmitForReviewHandler          $submitForReviewHandler,
        private readonly ApproveDocumentHandler          $approveDocumentHandler,
        private readonly RejectDocumentHandler           $rejectDocumentHandler,
        private readonly UpdateDocumentTagsHandler       $updateDocumentTagsHandler,
        private readonly RenameDocumentHandler           $renameDocumentHandler,
        private readonly MoveDocumentHandler             $moveDocumentHandler,
        private readonly DocumentRepositoryInterface     $documentRepository,
        private readonly DocumentCommentRepositoryInterface $commentRepository,
        private readonly UserRepositoryInterface         $userRepository,
        private readonly DocumentStorageInterface        $documentStorage,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q     = trim($request->query->getString('q', ''));
        $limit = min((int) $request->query->get('limit', 20), 50);

        $documents = $this->documentRepository->search($q, null, $limit);

        return $this->json(array_map(
            static fn ($doc) => [
                'id'       => $doc->getId()->getValue(),
                'title'    => $doc->getTitle(),
                'mimeType' => $doc->getLatestVersion()?->getMimeType()->getValue() ?? '',
                'status'   => $doc->getStatus()->value,
            ],
            $documents,
        ));
    }

    #[Route('/trash', name: 'trash', methods: ['GET'])]
    public function trash(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $domainUser = $user->getDomainUser();
        $documents  = $this->documentRepository->findDeleted();

        if (!$domainUser->isAdmin()) {
            $documents = array_filter(
                $documents,
                static fn ($doc) => $doc->isOwnedBy($domainUser),
            );
        }

        return $this->json(array_values(array_map(
            static fn ($doc) => [
                'id'        => $doc->getId()->getValue(),
                'title'     => $doc->getTitle(),
                'mimeType'  => $doc->getLatestVersion()?->getMimeType()->getValue() ?? '',
                'status'    => $doc->getStatus()->value,
                'deletedAt' => $doc->getDeletedAt()?->format(\DateTimeInterface::ATOM),
            ],
            $documents,
        )));
    }

    #[Route('/favorites', name: 'favorites', methods: ['GET'])]
    public function favorites(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $domainUser = $user->getDomainUser();
        $documents  = $this->documentRepository->findFavorites($domainUser);

        return $this->json(array_map(
            static fn ($doc) => [
                'id'       => $doc->getId()->getValue(),
                'title'    => $doc->getTitle(),
                'mimeType' => $doc->getLatestVersion()?->getMimeType()->getValue() ?? '',
                'status'   => $doc->getStatus()->value,
                'folderId' => $doc->getFolder()?->getId()->getValue(),
            ],
            $documents,
        ));
    }

    #[Route('/bulk-export', name: 'bulk_export', methods: ['POST'])]
    public function bulkExport(Request $request): Response
    {
        $body = $request->toArray();
        $ids  = is_array($body['ids'] ?? null) ? $body['ids'] : [];

        $documents = array_filter(
            array_map(function (mixed $id) {
                if (!is_string($id)) {
                    return null;
                }
                try {
                    return $this->documentRepository->findById(DocumentId::fromString($id));
                } catch (\InvalidArgumentException) {
                    return null;
                }
            }, $ids),
        );

        $date        = date('Ymd');
        $zipFilename = "export_{$date}.zip";

        $response = new StreamedResponse(function () use ($documents) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'ged_bulk_');
            $zip     = new \ZipArchive();
            $zip->open($tmpFile, \ZipArchive::OVERWRITE);

            foreach ($documents as $document) {
                if ($document === null) {
                    continue;
                }
                $version = $document->getLatestVersion();
                if ($version === null) {
                    continue;
                }
                try {
                    $stream = $this->documentStorage->read($version->getStoragePath());
                    $contents = is_resource($stream) ? stream_get_contents($stream) : (string) $stream;
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    $ext      = pathinfo($version->getStoragePath()->getValue(), PATHINFO_EXTENSION);
                    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $document->getTitle());
                    if ($ext && !str_ends_with($filename, '.' . $ext)) {
                        $filename .= '.' . $ext;
                    }
                    $zip->addFromString($filename, (string) $contents);
                } catch (\Throwable) {
                    // skip unreadable files
                }
            }

            $zip->close();
            readfile($tmpFile);
            @unlink($tmpFile);
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $zipFilename . '"');

        return $response;
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id, Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $withVersions = $request->query->getBoolean('versions', false);
            $dto          = ($this->getDocumentHandler)(new GetDocumentQuery($documentId, $withVersions));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        $document   = $this->documentRepository->findById($documentId);
        $domainUser = $user->getDomainUser();
        $isFavorite = $document !== null && $this->documentRepository->isFavorite($document, $domainUser);

        $data = is_array($dto) ? $dto : (method_exists($dto, 'toArray') ? $dto->toArray() : (array) $dto);
        $data['isFavorite'] = $isFavorite;

        return $this->json($data);
    }

    #[Route('/{id}/restore', name: 'restore', methods: ['PATCH'])]
    public function restore(string $id, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            ($this->restoreDocumentHandler)(new RestoreDocumentCommand(
                documentId: $documentId,
                restoredBy: $user->getDomainUser()->getId(),
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/permanent', name: 'permanent_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function permanentDelete(string $id, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            ($this->permanentDeleteDocumentHandler)(new PermanentDeleteDocumentCommand(
                documentId: $documentId,
                deletedBy: $user->getDomainUser()->getId(),
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/favorite', name: 'favorite_add', methods: ['POST'])]
    public function addFavorite(string $id, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $document = $this->documentRepository->findById($documentId);
        if ($document === null) {
            return $this->json(['error' => 'Document introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $domainUser = $user->getDomainUser();
        if (!$this->documentRepository->isFavorite($document, $domainUser)) {
            $this->documentRepository->addFavorite($document, $domainUser);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/favorite', name: 'favorite_remove', methods: ['DELETE'])]
    public function removeFavorite(string $id, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $document = $this->documentRepository->findById($documentId);
        if ($document === null) {
            return $this->json(['error' => 'Document introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $domainUser = $user->getDomainUser();
        if ($this->documentRepository->isFavorite($document, $domainUser)) {
            $this->documentRepository->removeFavorite($document, $domainUser);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/comments', name: 'comments_list', methods: ['GET'])]
    public function listComments(string $id): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $document = $this->documentRepository->findById($documentId);
        if ($document === null) {
            return $this->json(['error' => 'Document introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $comments = $this->commentRepository->findByDocument($document);

        return $this->json(array_map(
            static fn (DocumentComment $c) => $c->toArray(),
            $comments,
        ));
    }

    #[Route('/{id}/comments', name: 'comments_add', methods: ['POST'])]
    public function addComment(string $id, Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $document = $this->documentRepository->findById($documentId);
        if ($document === null) {
            return $this->json(['error' => 'Document introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $body    = json_decode($request->getContent(), true);
        $content = trim(\is_array($body) && \is_string($body['content'] ?? null) ? $body['content'] : '');

        if ($content === '') {
            return $this->json(['error' => 'Le champ "content" est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($content) > 2000) {
            return $this->json(['error' => '"content" ne peut pas dépasser 2000 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $domainUser = $user->getDomainUser();
        $comment    = new DocumentComment($document, $domainUser, $content);
        $this->commentRepository->save($comment);

        return $this->json($comment->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}/comments/{commentId}', name: 'comments_delete', methods: ['DELETE'])]
    public function deleteComment(string $id, string $commentId, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        $comment = $this->commentRepository->findById($commentId);
        if ($comment === null) {
            return $this->json(['error' => 'Commentaire introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $domainUser = $user->getDomainUser();
        $isAuthor   = $comment->getAuthor()->getId()->getValue() === $domainUser->getId()->getValue();

        if (!$isAuthor && !$domainUser->isAdmin()) {
            return $this->json(['error' => 'Non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $this->commentRepository->remove($comment);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            ($this->deleteDocumentHandler)(new DeleteDocumentCommand(
                documentId: $documentId,
                deletedBy: $user->getDomainUser()->getId(),
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/submit', name: 'submit', methods: ['PATCH'])]
    public function submit(string $id, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            ($this->submitForReviewHandler)(new SubmitForReviewCommand(
                documentId: $documentId,
                submittedBy: $user->getDomainUser()->getId(),
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approve(string $id, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            ($this->approveDocumentHandler)(new ApproveDocumentCommand(
                documentId: $documentId,
                approvedBy: $user->getDomainUser()->getId(),
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(string $id, #[CurrentUser] SecurityUser $user, Request $request): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $body   = $request->toArray();
        $reason = \is_string($body['reason'] ?? null) ? $body['reason'] : '';

        try {
            ($this->rejectDocumentHandler)(new RejectDocumentCommand(
                documentId: $documentId,
                rejectedBy: $user->getDomainUser()->getId(),
                reason: $reason,
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/tags', name: 'tags', methods: ['PATCH'])]
    public function tags(string $id, #[CurrentUser] SecurityUser $user, Request $request): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $body = json_decode($request->getContent(), true);

        if (!is_array($body) || !isset($body['tags']) || !is_array($body['tags'])) {
            return $this->json(['error' => 'Le champ "tags" (tableau) est requis.'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($body['tags'] as $tag) {
            if (!is_string($tag) || strlen(trim($tag)) === 0 || strlen($tag) > 50) {
                return $this->json(['error' => 'Chaque tag doit être une chaîne non vide de 50 caractères maximum.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        /** @var list<string> $tags */
        $tags = array_values($body['tags']);

        try {
            ($this->updateDocumentTagsHandler)(new UpdateDocumentTagsCommand(
                documentId: $documentId,
                updatedBy: $user->getDomainUser()->getId(),
                tags: $tags,
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/rename', name: 'rename', methods: ['PATCH'])]
    public function rename(string $id, #[CurrentUser] SecurityUser $user, Request $request): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $body  = json_decode($request->getContent(), true);
        $title = trim(\is_array($body) && \is_string($body['title'] ?? null) ? $body['title'] : '');

        if ($title === '') {
            return $this->json(['error' => 'Le champ "title" est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($title) > 255) {
            return $this->json(['error' => '"title" ne peut pas dépasser 255 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            ($this->renameDocumentHandler)(new RenameDocumentCommand(
                documentId: $documentId,
                renamedBy: $user->getDomainUser()->getId(),
                newTitle: $title,
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/move', name: 'move', methods: ['PATCH'])]
    public function move(string $id, #[CurrentUser] SecurityUser $user, Request $request): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $body        = json_decode($request->getContent(), true);
        $folderIdRaw = \is_array($body) && \is_string($body['folder_id'] ?? null) ? $body['folder_id'] : '';

        try {
            $targetFolderId = FolderId::fromString($folderIdRaw);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => '"folder_id" doit être un UUID valide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            ($this->moveDocumentHandler)(new MoveDocumentCommand(
                documentId: $documentId,
                targetFolderId: $targetFolderId,
                movedBy: $user->getDomainUser()->getId(),
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (FolderNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
