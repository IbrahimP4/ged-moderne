<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Notification\ValueObject\NotificationId;
use App\Domain\User\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(Notification $notification): void
    {
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function findById(NotificationId $id): ?Notification
    {
        /** @var Notification|null */
        return $this->entityManager->find(Notification::class, $id);
    }

    /** @return list<Notification> */
    public function findRecentFor(UserId $userId, int $limit = 30): array
    {
        /** @var list<Notification> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('n')
            ->from(Notification::class, 'n')
            ->join('n.recipient', 'u')
            ->where('u.id = :uid')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Notification> */
    public function findCreatedAfter(\DateTimeImmutable $since, UserId $userId): array
    {
        /** @var list<Notification> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('n')
            ->from(Notification::class, 'n')
            ->join('n.recipient', 'u')
            ->where('u.id = :uid')
            ->andWhere('n.createdAt > :since')
            ->orderBy('n.createdAt', 'ASC')
            ->setParameter('uid', $userId)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadFor(UserId $userId): int
    {
        /** @var int */
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->join('n.recipient', 'u')
            ->where('u.id = :uid')
            ->andWhere('n.read = false')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllReadFor(UserId $userId): void
    {
        $this->entityManager
            ->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.read', 'true')
            ->where('n.recipient = (SELECT u FROM App\\Domain\\User\\Entity\\User u WHERE u.id = :uid)')
            ->andWhere('n.read = false')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->execute();
    }

    public function markRead(NotificationId $id, UserId $recipientId): void
    {
        $notif = $this->findById($id);
        if ($notif === null) {
            return;
        }
        if ($notif->getRecipient()->getId()->getValue() !== $recipientId->getValue()) {
            return; // sécurité : seulement le destinataire peut marquer comme lu
        }
        $notif->markRead();
        $this->entityManager->flush();
    }
}
