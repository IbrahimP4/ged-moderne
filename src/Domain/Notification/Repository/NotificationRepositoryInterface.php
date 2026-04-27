<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repository;

use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\ValueObject\NotificationId;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\UserId;

interface NotificationRepositoryInterface
{
    public function save(Notification $notification): void;

    public function findById(NotificationId $id): ?Notification;

    /** @return list<Notification> */
    public function findRecentFor(UserId $userId, int $limit = 30): array;

    /**
     * Retourne les notifications créées STRICTEMENT après $since pour un destinataire.
     * Utilisé par le flux SSE pour ne pousser que le delta.
     *
     * @return list<Notification>
     */
    public function findCreatedAfter(\DateTimeImmutable $since, UserId $userId): array;

    public function countUnreadFor(UserId $userId): int;

    public function markAllReadFor(UserId $userId): void;

    public function markRead(NotificationId $id, UserId $recipientId): void;
}
