<?php

declare(strict_types=1);

namespace App\Application\Document\EventListener;

use App\Domain\AuditLog\Entity\AuditLog;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use App\Domain\Document\Event\DocumentDeleted;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DocumentDeletedListener
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogRepository,
    ) {}

    public function __invoke(DocumentDeleted $event): void
    {
        $this->auditLogRepository->append(
            AuditLog::record(
                eventName: 'document.deleted',
                aggregateType: 'Document',
                aggregateId: $event->documentId->getValue(),
                actorId: $event->deletedBy->getValue(),
                payload: [
                    'occurred_at' => $event->occurredAt->format(\DateTimeInterface::ATOM),
                ],
                occurredAt: $event->occurredAt,
            ),
        );
    }
}
