<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class RestoreDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly UserRepositoryInterface     $userRepository,
    ) {}

    public function __invoke(RestoreDocumentCommand $command): void
    {
        $document = $this->documentRepository->findById($command->documentId);
        if ($document === null) {
            throw new DocumentNotFoundException($command->documentId);
        }

        $user = $this->userRepository->findById($command->restoredBy);
        if ($user === null) {
            throw new \DomainException('Utilisateur introuvable.');
        }

        if (!$document->isOwnedBy($user) && !$user->isAdmin()) {
            throw new \DomainException('Vous n\'êtes pas autorisé à restaurer ce document.');
        }

        $document->restore();
        $this->documentRepository->save($document);
    }
}
