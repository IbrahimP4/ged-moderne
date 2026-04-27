<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Application\Document\Command\IndexDocumentContentCommand;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Port\EventBusInterface;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler du Use Case "Upload d'un document".
 *
 * Responsabilités :
 *   1. Résoudre les entités (Folder, User) depuis leurs IDs
 *   2. Valider les Value Objects (MimeType, FileSize)
 *   3. Stocker le fichier via le port de stockage
 *   4. Créer et persister l'entité Document
 *   5. Dispatcher les domain events
 *
 * Aucune dépendance sur Symfony ou Doctrine ici — uniquement des interfaces du Domain.
 */
final class UploadDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly DocumentStorageInterface $storage,
        private readonly EventBusInterface $eventBus,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(UploadDocumentCommand $command): DocumentId
    {
        // ── 1. Résolution des entités ─────────────────────────────────────────
        $folder = $this->folderRepository->findById($command->folderId);
        if ($folder === null) {
            throw new FolderNotFoundException($command->folderId);
        }

        $owner = $this->userRepository->findById($command->uploadedBy);
        if ($owner === null) {
            throw new \DomainException(sprintf(
                'Utilisateur introuvable : "%s".',
                $command->uploadedBy->getValue(),
            ));
        }

        // ── 2. Validation des Value Objects ───────────────────────────────────
        $mimeType = MimeType::fromString($command->mimeType);
        $fileSize = FileSize::fromBytes($command->fileSizeBytes);

        // ── 3. Lecture + stockage du fichier ──────────────────────────────────
        $fileStream  = fopen($command->tempFilePath, 'r');
        if ($fileStream === false) {
            throw new \RuntimeException(sprintf(
                'Impossible de lire le fichier temporaire : "%s".',
                $command->tempFilePath,
            ));
        }

        try {
            $storagePath = $this->storage->store($fileStream, $mimeType, $command->originalFilename);
        } finally {
            fclose($fileStream);
        }

        // ── 4. Création de l'Aggregate ────────────────────────────────────────
        $document = Document::upload(
            title: $command->title,
            folder: $folder,
            owner: $owner,
            mimeType: $mimeType,
            fileSize: $fileSize,
            originalFilename: $command->originalFilename,
            storagePath: $storagePath,
            comment: $command->comment,
        );

        // Un admin n'a pas besoin de circuit de validation :
        // le document est automatiquement approuvé à l'upload.
        // Un utilisateur normal soumet automatiquement pour révision.
        if ($owner->isAdmin()) {
            $document->approve($owner);
        } else {
            $document->submitForReview($owner);
        }

        // ── 5. Persistance ────────────────────────────────────────────────────
        $this->documentRepository->save($document);

        // ── 6. Dispatch des domain events ─────────────────────────────────────
        // Les events sont relâchés après le save pour garantir
        // qu'ils ne sont dispatché que si la transaction a réussi.
        $events = $document->releaseEvents();
        if ($events !== []) {
            $this->eventBus->dispatch(...$events);
        }

        // ── 7. Indexation du contenu (asynchrone) ──────────────────────────────
        // On passe storagePath (chemin permanent Flysystem), PAS le fichier temp
        // qui est supprimé dès la fin de la requête HTTP.
        $latestVersion = $document->getLatestVersion();
        if ($latestVersion !== null) {
            $this->messageBus->dispatch(new IndexDocumentContentCommand(
                documentId:  $document->getId(),
                storagePath: $latestVersion->getStoragePath()->getValue(),
                mimeType:    $command->mimeType,
            ));
        }

        return $document->getId();
    }
}
