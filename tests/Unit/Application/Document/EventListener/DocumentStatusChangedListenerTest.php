<?php

declare(strict_types=1);

use App\Application\Document\EventListener\DocumentStatusChangedListener;
use App\Domain\AuditLog\Entity\AuditLog;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use App\Domain\Document\Event\DocumentStatusChanged;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\User\ValueObject\UserId;

describe('DocumentStatusChangedListener', function (): void {

    beforeEach(function (): void {
        $this->repo     = Mockery::mock(AuditLogRepositoryInterface::class);
        $this->listener = new DocumentStatusChangedListener($this->repo);
    });

    afterEach(fn () => Mockery::close());

    it('enregistre la transition de statut dans l\'audit', function (): void {
        $event = new DocumentStatusChanged(
            documentId: DocumentId::generate(),
            previousStatus: DocumentStatus::DRAFT,
            newStatus: DocumentStatus::PENDING_REVIEW,
            changedBy: UserId::generate(),
            occurredAt: new \DateTimeImmutable(),
        );

        $this->repo
            ->shouldReceive('append')
            ->once()
            ->with(Mockery::on(function (AuditLog $log) use ($event): bool {
                return $log->getEventName() === 'document.status_changed'
                    && $log->getAggregateId() === $event->documentId->getValue()
                    && $log->getPayload()['previousStatus'] === 'draft'
                    && $log->getPayload()['newStatus'] === 'pending_review';
            }));

        ($this->listener)($event);
    });

    it('enregistre une approbation', function (): void {
        $event = new DocumentStatusChanged(
            documentId: DocumentId::generate(),
            previousStatus: DocumentStatus::PENDING_REVIEW,
            newStatus: DocumentStatus::APPROVED,
            changedBy: UserId::generate(),
            occurredAt: new \DateTimeImmutable(),
        );

        $this->repo
            ->shouldReceive('append')
            ->once()
            ->with(Mockery::on(fn (AuditLog $log) => $log->getPayload()['newStatus'] === 'approved'));

        ($this->listener)($event);
    });
});
