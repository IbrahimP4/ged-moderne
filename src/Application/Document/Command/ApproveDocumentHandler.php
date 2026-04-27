<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Port\EventBusInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class ApproveDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly EventBusInterface $eventBus,
    ) {}

    public function __invoke(ApproveDocumentCommand $command): void
    {
        $document = $this->documentRepository->findById($command->documentId);
        if ($document === null) {
            throw new DocumentNotFoundException($command->documentId);
        }

        $approver = $this->userRepository->findById($command->approvedBy);
        if ($approver === null) {
            throw new \DomainException('Utilisateur introuvable.');
        }

        $document->approve($approver);
        $this->documentRepository->save($document);

        $events = $document->releaseEvents();
        if ($events !== []) {
            $this->eventBus->dispatch(...$events);
        }
    }
}
