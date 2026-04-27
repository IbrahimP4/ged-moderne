<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Port\EventBusInterface;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class AddVersionHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly DocumentStorageInterface $storage,
        private readonly EventBusInterface $eventBus,
    ) {}

    public function __invoke(AddVersionCommand $command): void
    {
        $document = $this->documentRepository->findById($command->documentId);
        if ($document === null) {
            throw new DocumentNotFoundException($command->documentId);
        }

        $uploadedBy = $this->userRepository->findById($command->uploadedBy);
        if ($uploadedBy === null) {
            throw new \DomainException('Utilisateur introuvable.');
        }

        $mimeType = MimeType::fromString($command->mimeType);
        $fileSize = FileSize::fromBytes($command->fileSizeBytes);

        $fileStream = fopen($command->tempFilePath, 'r');
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

        $document->addVersion(
            uploadedBy: $uploadedBy,
            mimeType: $mimeType,
            fileSize: $fileSize,
            originalFilename: $command->originalFilename,
            storagePath: $storagePath,
            comment: $command->comment,
        );

        $this->documentRepository->save($document);

        $events = $document->releaseEvents();
        if ($events !== []) {
            $this->eventBus->dispatch(...$events);
        }
    }
}
