<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\Exception\DocumentAccessDeniedException;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class DeleteDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly UserRepositoryInterface     $userRepository,
    ) {}

    public function __invoke(DeleteDocumentCommand $command): void
    {
        $document = $this->documentRepository->findById($command->documentId);
        if ($document === null) {
            throw new DocumentNotFoundException($command->documentId);
        }

        $deletedBy = $this->userRepository->findById($command->deletedBy);
        if ($deletedBy === null) {
            throw new \DomainException('Utilisateur introuvable.');
        }

        if (!$document->isOwnedBy($deletedBy) && !$deletedBy->isAdmin()) {
            throw new DocumentAccessDeniedException($command->deletedBy, $command->documentId);
        }

        // Soft delete — les fichiers restent en place pour une restauration éventuelle
        $document->softDelete();
        $this->documentRepository->save($document);
    }
}
