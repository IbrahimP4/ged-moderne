<?php

declare(strict_types=1);

namespace App\Application\Document\EventListener;

use App\Domain\AuditLog\Entity\AuditLog;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use App\Domain\Document\Event\DocumentVersionAdded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DocumentVersionAddedListener
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogRepository,
    ) {}

    public function __invoke(DocumentVersionAdded $event): void
    {
        $this->auditLogRepository->append(
            AuditLog::record(
                eventName:     'document.version_added',
                aggregateType: 'Document',
                aggregateId:   $event->documentId->getValue(),
                actorId:       $event->uploadedBy->getValue(),
                payload: [
                    'versionNumber' => $event->versionNumber->getValue(),
                ],
                occurredAt: $event->occurredAt,
            ),
        );
    }
}
