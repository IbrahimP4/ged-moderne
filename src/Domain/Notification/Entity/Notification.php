<?php

declare(strict_types=1);

namespace App\Domain\Notification\Entity;

use App\Domain\Notification\ValueObject\NotificationId;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(columns: ['recipient_id', 'read', 'created_at'], name: 'idx_notif_recipient_read')]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'notification_id', length: 36)]
    private NotificationId $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_id', nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    /**
     * Type de notification : document_approved, document_rejected,
     * document_pending_review, signature_requested, message_received.
     */
    #[ORM\Column(length: 60)]
    private string $type;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $body;

    /** Route frontend, ex. /documents/uuid */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload;

    #[ORM\Column(options: ['default' => false])]
    private bool $read = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        NotificationId $id,
        User $recipient,
        string $type,
        string $title,
        string $body,
        ?string $link,
        ?array $payload,
    ) {
        $this->id        = $id;
        $this->recipient = $recipient;
        $this->type      = $type;
        $this->title     = $title;
        $this->body      = $body;
        $this->link      = $link;
        $this->payload   = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(
        User $recipient,
        string $type,
        string $title,
        string $body,
        ?string $link = null,
        ?array $payload = null,
    ): self {
        return new self(
            NotificationId::generate(),
            $recipient,
            $type,
            $title,
            $body,
            $link,
            $payload,
        );
    }

    public function markRead(): void
    {
        $this->read = true;
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): NotificationId
    {
        return $this->id;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id->getValue(),
            'type'      => $this->type,
            'title'     => $this->title,
            'body'      => $this->body,
            'link'      => $this->link,
            'payload'   => $this->payload,
            'read'      => $this->read,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
