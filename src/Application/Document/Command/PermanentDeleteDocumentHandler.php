<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class PermanentDeleteDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly UserRepositoryInterface     $userRepository,
        private readonly DocumentStorageInterface    $storage,
    ) {}

    public function __invoke(PermanentDeleteDocumentCommand $command): void
    {
        $document = $this->documentRepository->findById($command->documentId);
        if ($document === null) {
            throw new DocumentNotFoundException($command->documentId);
        }

        $user = $this->userRepository->findById($command->deletedBy);
        if ($user === null || !$user->isAdmin()) {
            throw new \DomainException('Seul un administrateur peut supprimer définitivement un document.');
        }

        foreach ($document->getVersions() as $version) {
            try {
                $this->storage->delete($version->getStoragePath());
            } catch (\RuntimeException) {
                // Fichier déjà absent — on continue
            }
        }

        $this->documentRepository->delete($document);
    }
}
