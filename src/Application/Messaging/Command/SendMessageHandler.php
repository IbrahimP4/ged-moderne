<?php

declare(strict_types=1);

namespace App\Application\Messaging\Command;

use App\Application\Notification\Service\CreateNotificationService;
use App\Domain\Messaging\Entity\Message;
use App\Domain\Messaging\Repository\MessageRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class SendMessageHandler
{
    public function __construct(
        private readonly MessageRepositoryInterface  $messageRepository,
        private readonly UserRepositoryInterface     $userRepository,
        private readonly CreateNotificationService   $notifier,
    ) {}

    public function __invoke(SendMessageCommand $command): Message
    {
        $sender = $this->userRepository->findById($command->senderId);
        if ($sender === null) {
            throw new \DomainException('Expéditeur introuvable.');
        }

        $recipient = $this->userRepository->findById($command->recipientId);
        if ($recipient === null) {
            throw new \DomainException('Destinataire introuvable.');
        }

        if ($command->senderId->getValue() === $command->recipientId->getValue()) {
            throw new \DomainException('Vous ne pouvez pas vous envoyer un message à vous-même.');
        }

        $content = trim($command->content);
        if ($content === '') {
            throw new \DomainException('Le message ne peut pas être vide.');
        }

        if (strlen($content) > 4000) {
            throw new \DomainException('Le message ne peut pas dépasser 4 000 caractères.');
        }

        $message = Message::send(
            sender:        $sender,
            recipient:     $recipient,
            content:       $content,
            documentId:    $command->documentId,
            documentTitle: $command->documentTitle,
        );

        $this->messageRepository->save($message);

        // Crée une notification temps réel pour le destinataire
        $body = strlen($content) > 80
            ? substr($content, 0, 77) . '…'
            : $content;

        $this->notifier->notify(
            recipient: $recipient,
            type:      'message_received',
            title:     sprintf('Message de %s', $sender->getUsername()),
            body:      $body,
            link:      '/messages?with=' . $sender->getId()->getValue(),
            payload:   [
                'senderId'       => $sender->getId()->getValue(),
                'senderUsername' => $sender->getUsername(),
                'messageId'      => $message->getId()->getValue(),
            ],
        );

        return $message;
    }
}
