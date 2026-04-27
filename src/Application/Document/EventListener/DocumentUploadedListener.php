<?php

declare(strict_types=1);

namespace App\Application\Document\EventListener;

use App\Domain\AuditLog\Entity\AuditLog;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use App\Domain\Document\Event\DocumentUploaded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DocumentUploadedListener
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogRepository,
    ) {}

    public function __invoke(DocumentUploaded $event): void
    {
        $this->auditLogRepository->append(
            AuditLog::record(
                eventName: 'document.uploaded',
                aggregateType: 'Document',
                aggregateId: $event->documentId->getValue(),
                actorId: $event->uploadedBy->getValue(),
                payload: [
                    'title'     => $event->title,
                    'mimeType'  => $event->mimeType,
                    'folderId'  => $event->folderId->getValue(),
                ],
                occurredAt: $event->occurredAt,
            ),
        );
    }
}
