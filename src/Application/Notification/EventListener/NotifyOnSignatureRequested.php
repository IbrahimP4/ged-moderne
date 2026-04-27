<?php

declare(strict_types=1);

namespace App\Application\Notification\EventListener;

use App\Application\Notification\Service\CreateNotificationService;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Signature\Repository\SignatureRequestRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;

/**
 * Ce service est appelé directement depuis CreateSignatureRequestHandler
 * (pas via Messenger) car il n'existe pas d'événement SignatureRequestCreated.
 *
 * Notifie le signataire désigné qu'une demande de signature l'attend.
 */
final class NotifyOnSignatureRequested
{
    public function __construct(
        private readonly CreateNotificationService $notifier,
        private readonly UserRepositoryInterface   $userRepository,
    ) {}

    public function notifySigner(
        UserId $signerId,
        string $requesterUsername,
        string $documentTitle,
        string $documentId,
        string $signatureRequestId,
    ): void {
        $signer = $this->userRepository->findById($signerId);
        if ($signer === null) {
            return;
        }

        $this->notifier->notify(
            recipient: $signer,
            type:      'signature_requested',
            title:     'Demande de signature',
            body:      sprintf(
                '%s vous demande de signer "%s".',
                $requesterUsername,
                $documentTitle,
            ),
            link:      '/my-signatures',
            payload:   [
                'signatureRequestId' => $signatureRequestId,
                'documentId'         => $documentId,
            ],
        );
    }
}
