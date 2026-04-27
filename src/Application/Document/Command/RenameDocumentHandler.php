<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class RenameDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function __invoke(RenameDocumentCommand $command): void
    {
        $document = $this->documentRepository->findById($command->documentId);

        if ($document === null) {
            throw new DocumentNotFoundException($command->documentId);
        }

        $user = $this->userRepository->findById($command->renamedBy);

        if ($user === null) {
            throw new \DomainException('Utilisateur introuvable.');
        }

        $document->rename($command->newTitle, $user);

        $this->documentRepository->save($document);
    }
}
