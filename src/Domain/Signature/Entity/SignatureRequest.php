<?php

declare(strict_types=1);

namespace App\Domain\Signature\Entity;

use App\Domain\Document\Entity\Document;
use App\Domain\Signature\ValueObject\SignatureRequestId;
use App\Domain\Signature\ValueObject\SignatureRequestStatus;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande de signature numérique sur un document.
 *
 * Un utilisateur demande à un signataire (souvent l'admin)
 * de valider/signer un document. Le signataire peut signer ou refuser.
 */
#[ORM\Entity]
#[ORM\Table(name: 'signature_requests')]
class SignatureRequest
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'signature_request_id', length: 36)]
    private SignatureRequestId $id;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'requester_id', referencedColumnName: 'id', nullable: false)]
    private User $requester;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'signer_id', referencedColumnName: 'id', nullable: false)]
    private User $signer;

    #[ORM\Column(name: 'status', type: 'string', length: 20, enumType: SignatureRequestStatus::class)]
    private SignatureRequestStatus $status;

    /** Message libre du demandeur (contexte, urgence, instructions…) */
    #[ORM\Column(name: 'message', type: 'text', nullable: true)]
    private ?string $message;

    /** Commentaire du signataire lors de la signature ou du refus */
    #[ORM\Column(name: 'comment', type: 'text', nullable: true)]
    private ?string $comment;

    #[ORM\Column(name: 'requested_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt;

    private function __construct(
        Document $document,
        User $requester,
        User $signer,
        ?string $message,
    ) {
        if ($requester->getId()->equals($signer->getId())) {
            throw new \DomainException('Vous ne pouvez pas vous envoyer une demande de signature à vous-même.');
        }

        $this->id = SignatureRequestId::generate();
        $this->document    = $document;
        $this->requester   = $requester;
        $this->signer      = $signer;
        $this->message = $message;
        $this->status      = SignatureRequestStatus::PENDING;
        $this->requestedAt = new \DateTimeImmutable();
        $this->resolvedAt  = null;
        $this->comment     = null;
    }

    public static function create(
        Document $document,
        User $requester,
        User $signer,
        ?string $message
    ): self {
        return new self($document, $requester, $signer, $message);
    }

    public function sign(User $by, ?string $comment = null): void
    {
        if (! $this->signer->getId()->equals($by->getId()) && ! $by->isAdmin()) {
            throw new \DomainException('Seul le signataire désigné ou un administrateur peut signer cette demande.');
        }

        if (! $this->status->isPending()) {
            throw new \DomainException(sprintf(
                'Cette demande est déjà "%s" et ne peut plus être signée.',
                $this->status->label(),
            ));
        }

        $this->status     = SignatureRequestStatus::SIGNED;
        $this->comment    = $comment !== null ? trim($comment) : null;
        $this->resolvedAt = new \DateTimeImmutable();
    }

    public function decline(User $by, ?string $comment = null): void
    {
        if (! $this->signer->getId()->equals($by->getId()) && ! $by->isAdmin()) {
            throw new \DomainException('Seul le signataire désigné ou un administrateur peut refuser cette demande.');
        }

        if (! $this->status->isPending()) {
            throw new \DomainException(sprintf(
                'Cette demande est déjà "%s" et ne peut plus être refusée.',
                $this->status->label(),
            ));
        }

        $this->status     = SignatureRequestStatus::DECLINED;
        $this->comment    = $comment !== null ? trim($comment) : null;
        $this->resolvedAt = new \DateTimeImmutable();
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): SignatureRequestId
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getRequester(): User
    {
        return $this->requester;
    }

    public function getSigner(): User
    {
        return $this->signer;
    }

    public function getStatus(): SignatureRequestStatus
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
