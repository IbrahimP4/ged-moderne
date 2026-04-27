<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Document;

use App\Application\Document\Command\UploadDocumentCommand;
use App\Application\Document\Command\UploadDocumentHandler;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\ValueObject\FolderId;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/documents', name: 'api_documents_upload_')]
#[IsGranted('ROLE_USER')]
final class DocumentUploadController extends AbstractController
{
    // OWASP : taille maximale côté applicatif (redondance avec Nginx)
    private const MAX_FILE_SIZE = 128 * 1024 * 1024; // 128 MB

    public function __construct(
        private readonly UploadDocumentHandler $uploadDocumentHandler,
    ) {}

    /**
     * POST /api/documents
     *
     * Multipart form data :
     *   - file       : fichier (required)
     *   - title      : string (required)
     *   - folder_id  : UUID (required)
     *   - comment    : string (optional)
     */
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        // ── 1. Validation du fichier uploadé ─────────────────────────────────
        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof UploadedFile) {
            return $this->json(['error' => 'Le champ "file" est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $uploadedFile->isValid()) {
            return $this->json(
                ['error' => 'Erreur d\'upload : ' . $uploadedFile->getErrorMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($uploadedFile->getSize() > self::MAX_FILE_SIZE) {
            return $this->json(
                ['error' => 'Fichier trop volumineux. Maximum : 128 MB.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // ── 2. Validation du MIME type via finfo (OWASP : ne jamais faire confiance au client) ──
        $detectedMime = $uploadedFile->getMimeType() ?? 'application/octet-stream';

        try {
            $mimeType = MimeType::fromString($detectedMime);
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => 'Type de fichier non autorisé : ' . $detectedMime],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // ── 3. Validation des champs texte ───────────────────────────────────
        $title    = trim($request->request->getString('title'));
        $folderId = trim($request->request->getString('folder_id'));
        $commentRaw = trim($request->request->getString('comment'));
        $comment    = $commentRaw !== '' ? $commentRaw : null;

        if ($title === '') {
            return $this->json(['error' => 'Le champ "title" est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($title) > 255) {
            return $this->json(['error' => '"title" ne peut pas dépasser 255 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $folderIdVO = FolderId::fromString($folderId);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => '"folder_id" doit être un UUID valide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // ── 4. Construction de la commande ───────────────────────────────────
        $command = new UploadDocumentCommand(
            folderId: $folderIdVO,
            uploadedBy: $user->getDomainUser()->getId(),
            title: $title,
            originalFilename: $uploadedFile->getClientOriginalName(),
            mimeType: $mimeType->getValue(),
            fileSizeBytes: (int) $uploadedFile->getSize(),
            tempFilePath: $uploadedFile->getPathname(),
            comment: $comment,
        );

        // ── 5. Exécution du Use Case ─────────────────────────────────────────
        try {
            $documentId = ($this->uploadDocumentHandler)($command);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\RuntimeException $e) {
            return $this->json(
                ['error' => 'Erreur serveur lors du stockage du fichier.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->json(
            ['id' => $documentId->getValue(), 'message' => 'Document uploadé avec succès.'],
            Response::HTTP_CREATED,
        );
    }
}
