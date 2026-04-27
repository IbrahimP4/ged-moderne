<?php

declare(strict_types=1);

use App\Application\Document\EventListener\DocumentUploadedListener;
use App\Domain\AuditLog\Entity\AuditLog;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use App\Domain\Document\Event\DocumentUploaded;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\User\ValueObject\UserId;

describe('DocumentUploadedListener', function (): void {

    beforeEach(function (): void {
        $this->repo     = Mockery::mock(AuditLogRepositoryInterface::class);
        $this->listener = new DocumentUploadedListener($this->repo);
    });

    afterEach(fn () => Mockery::close());

    it('crée une entrée d\'audit lors d\'un upload', function (): void {
        $event = new DocumentUploaded(
            documentId: DocumentId::generate(),
            folderId: FolderId::generate(),
            uploadedBy: UserId::generate(),
            title: 'Rapport annuel',
            mimeType: 'application/pdf',
            occurredAt: new \DateTimeImmutable(),
        );

        $this->repo
            ->shouldReceive('append')
            ->once()
            ->with(Mockery::on(function (AuditLog $log) use ($event): bool {
                return $log->getEventName() === 'document.uploaded'
                    && $log->getAggregateType() === 'Document'
                    && $log->getAggregateId() === $event->documentId->getValue()
                    && $log->getActorId() === $event->uploadedBy->getValue()
                    && $log->getPayload()['title'] === 'Rapport annuel'
                    && $log->getPayload()['mimeType'] === 'application/pdf';
            }));

        ($this->listener)($event);
    });

    it('conserve la date d\'occurrence de l\'événement', function (): void {
        $occurredAt = new \DateTimeImmutable('2026-01-15 10:30:00');

        $event = new DocumentUploaded(
            documentId: DocumentId::generate(),
            folderId: FolderId::generate(),
            uploadedBy: UserId::generate(),
            title: 'Test',
            mimeType: 'application/pdf',
            occurredAt: $occurredAt,
        );

        $this->repo
            ->shouldReceive('append')
            ->once()
            ->with(Mockery::on(fn (AuditLog $log) => $log->getOccurredAt() === $occurredAt));

        ($this->listener)($event);
    });
});
