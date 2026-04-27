<?php

declare(strict_types=1);

namespace App\Application\Notification\Service;

use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\User\Entity\User;

/**
 * Service applicatif léger pour créer et persister une notification.
 * Injecté dans les event listeners et les command handlers.
 */
final class CreateNotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository,
    ) {}

    public function notify(
        User $recipient,
        string $type,
        string $title,
        string $body,
        ?string $link = null,
        ?array $payload = null,
    ): void {
        $notification = Notification::create(
            recipient: $recipient,
            type:      $type,
            title:     $title,
            body:      $body,
            link:      $link,
            payload:   $payload,
        );

        $this->notificationRepository->save($notification);
    }

    /**
     * Notifie plusieurs destinataires en une seule passe.
     *
     * @param list<User> $recipients
     */
    public function notifyMany(
        array $recipients,
        string $type,
        string $title,
        string $body,
        ?string $link = null,
        ?array $payload = null,
    ): void {
        foreach ($recipients as $recipient) {
            $this->notify($recipient, $type, $title, $body, $link, $payload);
        }
    }
}
