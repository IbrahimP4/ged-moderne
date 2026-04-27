<?php

declare(strict_types=1);

namespace App\Application\Document\EventListener;

use App\Domain\AuditLog\Entity\AuditLog;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use App\Domain\Document\Event\DocumentStatusChanged;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DocumentStatusChangedListener
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogRepository,
    ) {}

    public function __invoke(DocumentStatusChanged $event): void
    {
        $this->auditLogRepository->append(
            AuditLog::record(
                eventName: 'document.status_changed',
                aggregateType: 'Document',
                aggregateId: $event->documentId->getValue(),
                actorId: $event->changedBy->getValue(),
                payload: [
                    'previousStatus' => $event->previousStatus->value,
                    'newStatus'      => $event->newStatus->value,
                ],
                occurredAt: $event->occurredAt,
            ),
        );
    }
}
