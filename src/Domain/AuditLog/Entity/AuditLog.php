<?php

declare(strict_types=1);

namespace App\Domain\AuditLog\Entity;

use App\Domain\AuditLog\ValueObject\AuditLogId;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace immuable de chaque événement métier significatif.
 *
 * Principes :
 *   - Pas de setter : une entrée d'audit ne se modifie jamais.
 *   - aggregateId/aggregateType : identifient l'entité concernée (Document, Folder…).
 *   - payload : snapshot JSON des données pertinentes au moment de l'événement.
 *   - actorId : identifiant de l'utilisateur déclencheur (null = système).
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_logs')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'audit_log_id', length: 36)]
    private AuditLogId $id;

    #[ORM\Column(name: 'event_name', length: 100)]
    private string $eventName;

    #[ORM\Column(name: 'aggregate_type', length: 50)]
    private string $aggregateType;

    #[ORM\Column(name: 'aggregate_id', length: 36)]
    private string $aggregateId;

    #[ORM\Column(name: 'actor_id', length: 36, nullable: true)]
    private ?string $actorId;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'payload', type: 'json')]
    private array $payload;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        AuditLogId $id,
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        ?string $actorId,
        array $payload,
        \DateTimeImmutable $occurredAt,
    ) {
        $this->id            = $id;
        $this->eventName     = $eventName;
        $this->aggregateType = $aggregateType;
        $this->aggregateId   = $aggregateId;
        $this->actorId       = $actorId;
        $this->payload       = $payload;
        $this->occurredAt    = $occurredAt;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function record(
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        ?string $actorId,
        array $payload,
        ?\DateTimeImmutable $occurredAt = null,
    ): self {
        return new self(
            AuditLogId::generate(),
            $eventName,
            $aggregateType,
            $aggregateId,
            $actorId,
            $payload,
            $occurredAt ?? new \DateTimeImmutable(),
        );
    }

    public function getId(): AuditLogId
    {
        return $this->id;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
