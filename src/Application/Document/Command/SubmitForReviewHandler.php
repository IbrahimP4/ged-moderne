<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Port\EventBusInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class SubmitForReviewHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly EventBusInterface $eventBus,
    ) {}

    public function __invoke(SubmitForReviewCommand $command): void
    {
        $document = $this->documentRepository->findById($command->documentId);
        if ($document === null) {
            throw new DocumentNotFoundException($command->documentId);
        }

        $submittedBy = $this->userRepository->findById($command->submittedBy);
        if ($submittedBy === null) {
            throw new \DomainException('Utilisateur introuvable.');
        }

        $document->submitForReview($submittedBy);
        $this->documentRepository->save($document);

        $events = $document->releaseEvents();
        if ($events !== []) {
            $this->eventBus->dispatch(...$events);
        }
    }
}
