<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Document;

use App\Application\Document\Command\AddVersionCommand;
use App\Application\Document\Command\AddVersionHandler;
use App\Application\Document\Query\DownloadDocumentHandler;
use App\Application\Document\Query\DownloadDocumentQuery;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\ValueObject\DocumentId;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/documents', name: 'api_document_versions_')]
#[IsGranted('ROLE_USER')]
final class DocumentVersionController extends AbstractController
{
    public function __construct(
        private readonly AddVersionHandler $addVersionHandler,
        private readonly DownloadDocumentHandler $downloadDocumentHandler,
    ) {}

    #[Route('/{id}/versions', name: 'add', methods: ['POST'])]
    public function addVersion(string $id, Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json(['error' => 'Le fichier est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $comment = $request->request->get('comment');

        try {
            ($this->addVersionHandler)(new AddVersionCommand(
                documentId: $documentId,
                uploadedBy: $user->getDomainUser()->getId(),
                tempFilePath: $uploadedFile->getPathname(),
                originalFilename: $uploadedFile->getClientOriginalName(),
                mimeType: $uploadedFile->getMimeType() ?? 'application/octet-stream',
                fileSizeBytes: $uploadedFile->getSize() !== false ? $uploadedFile->getSize() : 0,
                comment: \is_string($comment) ? $comment : null,
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_CREATED);
    }

    #[Route('/{id}/download', name: 'download', methods: ['GET'])]
    public function download(string $id, Request $request): StreamedResponse|JsonResponse
    {
        try {
            $documentId = DocumentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $versionParam  = $request->query->get('version');
        $versionNumber = $versionParam !== null ? (int) $versionParam : null;

        try {
            $dto = ($this->downloadDocumentHandler)(new DownloadDocumentQuery($documentId, $versionNumber));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        $stream   = $dto->stream;
        $filename = $dto->originalFilename;
        $mime     = $dto->mimeType;

        return new StreamedResponse(
            function () use ($stream): void {
                if (\is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            Response::HTTP_OK,
            [
                'Content-Type'        => $mime,
                'Content-Disposition' => sprintf(
                    'attachment; filename="%s"',
                    addslashes($filename),
                ),
            ],
        );
    }
}
