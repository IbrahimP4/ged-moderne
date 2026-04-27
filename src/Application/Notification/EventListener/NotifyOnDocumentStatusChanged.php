<?php

declare(strict_types=1);

namespace App\Application\Notification\EventListener;

use App\Application\Notification\Service\CreateNotificationService;
use App\Domain\Document\Event\DocumentStatusChanged;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Crée une notification lors d'un changement de statut de document.
 *
 * - PENDING_REVIEW : notifie tous les admins (nouveau doc à valider)
 * - APPROVED       : notifie l'auteur du document (approuvé ✓)
 * - REJECTED       : notifie l'auteur du document (rejeté)
 */
#[AsMessageHandler]
final class NotifyOnDocumentStatusChanged
{
    public function __construct(
        private readonly CreateNotificationService     $notifier,
        private readonly DocumentRepositoryInterface  $documentRepository,
        private readonly UserRepositoryInterface       $userRepository,
    ) {}

    public function __invoke(DocumentStatusChanged $event): void
    {
        $document = $this->documentRepository->findById($event->documentId);
        if ($document === null) {
            return;
        }

        $docTitle = $document->getTitle();
        $docLink  = '/documents/' . $event->documentId->getValue();

        match ($event->newStatus) {
            DocumentStatus::PENDING_REVIEW => $this->notifyAdmins($docTitle, $docLink, $event),
            DocumentStatus::APPROVED       => $this->notifyAuthor($document, $docTitle, $docLink, true),
            DocumentStatus::REJECTED       => $this->notifyAuthor($document, $docTitle, $docLink, false),
            default                        => null,
        };
    }

    private function notifyAdmins(string $docTitle, string $link, DocumentStatusChanged $event): void
    {
        $allUsers = $this->userRepository->findAll();
        $admins   = array_filter(
            $allUsers,
            static fn ($u) => $u->isAdmin()
                && $u->getId()->getValue() !== $event->changedBy->getValue(),
        );

        $this->notifier->notifyMany(
            recipients: array_values($admins),
            type:       'document_pending_review',
            title:      'Document en attente de validation',
            body:       sprintf('"%s" attend votre approbation.', $docTitle),
            link:       $link,
            payload:    ['documentId' => $event->documentId->getValue()],
        );
    }

    private function notifyAuthor(
        \App\Domain\Document\Entity\Document $document,
        string $docTitle,
        string $link,
        bool $approved,
    ): void {
        $author = $document->getOwner();

        if ($approved) {
            $this->notifier->notify(
                recipient: $author,
                type:      'document_approved',
                title:     'Document approuvé ✓',
                body:      sprintf('Votre document "%s" a été approuvé.', $docTitle),
                link:      $link,
                payload:   ['documentId' => $document->getId()->getValue()],
            );
        } else {
            $this->notifier->notify(
                recipient: $author,
                type:      'document_rejected',
                title:     'Document rejeté',
                body:      sprintf('Votre document "%s" a été rejeté.', $docTitle),
                link:      $link,
                payload:   ['documentId' => $document->getId()->getValue()],
            );
        }
    }
}
