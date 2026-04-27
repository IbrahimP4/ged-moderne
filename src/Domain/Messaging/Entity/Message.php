<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Entity;

use App\Domain\Messaging\ValueObject\MessageId;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'messages')]
#[ORM\Index(columns: ['recipient_id', 'read'], name: 'idx_msg_recipient_read')]
#[ORM\Index(columns: ['sender_id', 'recipient_id', 'sent_at'], name: 'idx_msg_conversation')]
class Message
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'message_id', length: 36)]
    private MessageId $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', nullable: false, onDelete: 'CASCADE')]
    private User $sender;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_id', nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(type: 'text')]
    private string $content;

    /** UUID du document partagé (facultatif) */
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $documentId;

    /** Titre du document partagé pour l'affichage */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentTitle;

    #[ORM\Column(options: ['default' => false])]
    private bool $read = false;

    #[ORM\Column(name: 'sent_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(name: 'read_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    private function __construct(
        MessageId $id,
        User $sender,
        User $recipient,
        string $content,
        ?string $documentId,
        ?string $documentTitle,
    ) {
        $this->id            = $id;
        $this->sender        = $sender;
        $this->recipient     = $recipient;
        $this->content       = $content;
        $this->documentId    = $documentId;
        $this->documentTitle = $documentTitle;
        $this->sentAt        = new \DateTimeImmutable();
    }

    public static function send(
        User $sender,
        User $recipient,
        string $content,
        ?string $documentId = null,
        ?string $documentTitle = null,
    ): self {
        return new self(
            MessageId::generate(),
            $sender,
            $recipient,
            $content,
            $documentId,
            $documentTitle,
        );
    }

    public function markRead(): void
    {
        if (! $this->read) {
            $this->read   = true;
            $this->readAt = new \DateTimeImmutable();
        }
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): MessageId
    {
        return $this->id;
    }

    public function getSender(): User
    {
        return $this->sender;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getDocumentId(): ?string
    {
        return $this->documentId;
    }

    public function getDocumentTitle(): ?string
    {
        return $this->documentTitle;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id->getValue(),
            'senderId'       => $this->sender->getId()->getValue(),
            'senderUsername' => $this->sender->getUsername(),
            'recipientId'    => $this->recipient->getId()->getValue(),
            'content'        => $this->content,
            'documentId'     => $this->documentId,
            'documentTitle'  => $this->documentTitle,
            'read'           => $this->read,
            'sentAt'         => $this->sentAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
