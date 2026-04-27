<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Messaging\Entity\Message;
use App\Domain\Messaging\Repository\MessageRepositoryInterface;
use App\Domain\Messaging\ValueObject\MessageId;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineMessageRepository implements MessageRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(Message $message): void
    {
        $this->entityManager->persist($message);
        $this->entityManager->flush();
    }

    public function findById(MessageId $id): ?Message
    {
        /** @var Message|null */
        return $this->entityManager->find(Message::class, $id);
    }

    /** @return list<Message> */
    public function findConversation(UserId $user1, UserId $user2, int $limit = 60): array
    {
        /** @var list<Message> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('m')
            ->from(Message::class, 'm')
            ->join('m.sender', 's')
            ->join('m.recipient', 'r')
            ->where(
                '(s.id = :u1 AND r.id = :u2) OR (s.id = :u2 AND r.id = :u1)'
            )
            ->orderBy('m.sentAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('u1', $user1)
            ->setParameter('u2', $user2)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{partner: User, lastMessage: Message, unreadCount: int}>
     */
    public function findConversationSummaries(UserId $userId): array
    {
        // Récupère tous les messages où l'utilisateur est impliqué
        /** @var list<Message> */
        $messages = $this->entityManager
            ->createQueryBuilder()
            ->select('m')
            ->from(Message::class, 'm')
            ->join('m.sender', 's')
            ->join('m.recipient', 'r')
            ->where('s.id = :uid OR r.id = :uid')
            ->orderBy('m.sentAt', 'DESC')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getResult();

        // Regrouper par interlocuteur
        $conversations = []; // partnerId → ['partner' => User, 'lastMessage' => Message, 'unreadCount' => 0]

        foreach ($messages as $message) {
            $isSender  = $message->getSender()->getId()->getValue() === $userId->getValue();
            $partner   = $isSender ? $message->getRecipient() : $message->getSender();
            $partnerId = $partner->getId()->getValue();

            if (! isset($conversations[$partnerId])) {
                $conversations[$partnerId] = [
                    'partner'      => $partner,
                    'lastMessage'  => $message, // déjà trié DESC, donc premier = dernier
                    'unreadCount'  => 0,
                ];
            }

            // Compter non-lus reçus par l'utilisateur
            if (! $isSender && ! $message->isRead()) {
                $conversations[$partnerId]['unreadCount']++;
            }
        }

        return array_values($conversations);
    }

    /** @return list<Message> */
    public function findReceivedAfter(\DateTimeImmutable $since, UserId $recipientId): array
    {
        /** @var list<Message> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('m')
            ->from(Message::class, 'm')
            ->join('m.recipient', 'r')
            ->where('r.id = :uid')
            ->andWhere('m.sentAt > :since')
            ->orderBy('m.sentAt', 'ASC')
            ->setParameter('uid', $recipientId)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadFor(UserId $userId): int
    {
        /** @var int */
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Message::class, 'm')
            ->join('m.recipient', 'r')
            ->where('r.id = :uid')
            ->andWhere('m.read = false')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markConversationRead(UserId $readerId, UserId $partnerId): void
    {
        $this->entityManager
            ->createQueryBuilder()
            ->update(Message::class, 'm')
            ->set('m.read', 'true')
            ->set('m.readAt', ':now')
            ->where(
                'm.recipient = (SELECT u FROM App\\Domain\\User\\Entity\\User u WHERE u.id = :readerId)'
            )
            ->andWhere(
                'm.sender = (SELECT p FROM App\\Domain\\User\\Entity\\User p WHERE p.id = :partnerId)'
            )
            ->andWhere('m.read = false')
            ->setParameter('readerId', $readerId)
            ->setParameter('partnerId', $partnerId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
