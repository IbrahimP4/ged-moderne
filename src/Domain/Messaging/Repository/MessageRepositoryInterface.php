<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Repository;

use App\Domain\Messaging\Entity\Message;
use App\Domain\Messaging\ValueObject\MessageId;
use App\Domain\User\ValueObject\UserId;

interface MessageRepositoryInterface
{
    public function save(Message $message): void;

    public function findById(MessageId $id): ?Message;

    /**
     * Messages échangés entre deux utilisateurs, du plus récent au plus ancien.
     *
     * @return list<Message>
     */
    public function findConversation(UserId $user1, UserId $user2, int $limit = 60): array;

    /**
     * Résumé des conversations d'un utilisateur :
     * liste des interlocuteurs + dernier message + nb non-lus.
     * Retourne un tableau de shape :
     *   [ 'partner' => User, 'lastMessage' => Message, 'unreadCount' => int ]
     *
     * @return list<array{partner: \App\Domain\User\Entity\User, lastMessage: Message, unreadCount: int}>
     */
    public function findConversationSummaries(UserId $userId): array;

    /**
     * Messages reçus par $recipientId créés STRICTEMENT après $since.
     * Utilisé par le flux SSE.
     *
     * @return list<Message>
     */
    public function findReceivedAfter(\DateTimeImmutable $since, UserId $recipientId): array;

    public function countUnreadFor(UserId $userId): int;

    public function markConversationRead(UserId $readerId, UserId $partnerId): void;
}
