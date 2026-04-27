<?php

declare(strict_types=1);

namespace App\Domain\Document\Entity;

use App\Domain\Document\Event\DocumentStatusChanged;
use App\Domain\Document\Event\DocumentUploaded;
use App\Domain\Document\Event\DocumentVersionAdded;
use App\Domain\Document\Exception\DocumentAccessDeniedException;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Document\ValueObject\VersionNumber;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Aggregate Root du bounded context Document.
 *
 * Toute modification du document passe par cette classe.
 * Les événements domain sont accumulés et relâchés par le handler après persist.
 */
#[ORM\Entity]
#[ORM\Table(name: 'documents')]
#[ORM\HasLifecycleCallbacks]
class Document
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'document_id', length: 36)]
    private DocumentId $id;

    #[ORM\Column(name: 'title', length: 255)]
    private string $title;

    #[ORM\Column(name: 'comment', type: 'text', nullable: true)]
    private ?string $comment;

    #[ORM\Column(name: 'status', type: 'string', length: 30, enumType: DocumentStatus::class)]
    private DocumentStatus $status;

    #[ORM\ManyToOne(targetEntity: Folder::class)]
    #[ORM\JoinColumn(name: 'folder_id', referencedColumnName: 'id', nullable: false)]
    private Folder $folder;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
    private User $owner;

    /** @var Collection<int, DocumentVersion> */
    #[ORM\OneToMany(targetEntity: DocumentVersion::class, mappedBy: 'document', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['versionNumber' => 'ASC'])]
    private Collection $versions;

    /** @var list<string> */
    #[ORM\Column(name: 'tags', type: 'json', options: ['default' => '[]'])]
    private array $tags = [];

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @var list<object> — non persisté, relâché après flush */
    private array $domainEvents = [];

    private function __construct(
        DocumentId $id,
        string $title,
        Folder $folder,
        User $owner,
        ?string $comment,
    ) {
        $this->id        = $id;
        $this->title     = $title;
        $this->folder    = $folder;
        $this->owner     = $owner;
        $this->comment   = $comment;
        $this->status    = DocumentStatus::DRAFT;
        $this->versions  = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Factory principale : crée un document et ajoute sa première version.
     *
     * Toute la logique de création est ici — le Handler ne touche pas
     * aux propriétés internes.
     */
    public static function upload(
        string $title,
        Folder $folder,
        User $owner,
        MimeType $mimeType,
        FileSize $fileSize,
        string $originalFilename,
        StoragePath $storagePath,
        ?string $comment = null,
    ): self {
        $document = new self(DocumentId::generate(), $title, $folder, $owner, $comment);

        $firstVersion = DocumentVersion::create(
            document: $document,
            versionNumber: VersionNumber::first(),
            mimeType: $mimeType,
            fileSize: $fileSize,
            originalFilename: $originalFilename,
            storagePath: $storagePath,
            uploadedBy: $owner,
        );

        $document->versions->add($firstVersion);

        $document->recordEvent(new DocumentUploaded(
            documentId: $document->id,
            folderId: $folder->getId(),
            uploadedBy: $owner->getId(),
            title: $title,
            mimeType: $mimeType->getValue(),
            occurredAt: $document->createdAt,
        ));

        return $document;
    }

    /**
     * Ajoute une nouvelle version au document existant.
     * Seul le propriétaire ou un admin peut versionner.
     */
    public function addVersion(
        User $uploadedBy,
        MimeType $mimeType,
        FileSize $fileSize,
        string $originalFilename,
        StoragePath $storagePath,
        ?string $comment = null,
    ): DocumentVersion {
        // Vérification des droits d'accès
        if (! $this->isOwnedBy($uploadedBy) && ! $uploadedBy->isAdmin()) {
            throw new DocumentAccessDeniedException($uploadedBy->getId(), $this->id);
        }

        // Bloque la modification si le document est en révision, approuvé ou archivé
        if (in_array($this->status, [DocumentStatus::PENDING_REVIEW, DocumentStatus::APPROVED, DocumentStatus::ARCHIVED], true)) {
            throw new \DomainException(sprintf(
                'Le document "%s" est en statut "%s" et ne peut pas recevoir de nouvelle version.',
                $this->title,
                $this->status->label(),
            ));
        }

        $nextVersionNumber = $this->getLatestVersion() !== null
            ? $this->getLatestVersion()->getVersionNumber()->next()
            : VersionNumber::first();

        $version = DocumentVersion::create(
            document: $this,
            versionNumber: $nextVersionNumber,
            mimeType: $mimeType,
            fileSize: $fileSize,
            originalFilename: $originalFilename,
            storagePath: $storagePath,
            uploadedBy: $uploadedBy,
            comment: $comment,
        );

        $this->versions->add($version);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new DocumentVersionAdded(
            documentId: $this->id,
            versionNumber: $nextVersionNumber,
            uploadedBy: $uploadedBy->getId(),
            occurredAt: $this->updatedAt,
        ));

        return $version;
    }

    public function submitForReview(User $by): void
    {
        $this->assertCanBeModifiedBy($by);
        $this->assertStatusTransitionAllowed(DocumentStatus::PENDING_REVIEW);

        $previous        = $this->status;
        $this->status    = DocumentStatus::PENDING_REVIEW;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new DocumentStatusChanged($this->id, $previous, $this->status, $by->getId(), $this->updatedAt));
    }

    public function approve(User $approver): void
    {
        if (! $approver->isAdmin()) {
            throw new \DomainException('Seul un administrateur peut approuver un document.');
        }

        $this->assertStatusTransitionAllowed(DocumentStatus::APPROVED);

        $previous        = $this->status;
        $this->status    = DocumentStatus::APPROVED;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new DocumentStatusChanged($this->id, $previous, $this->status, $approver->getId(), $this->updatedAt));
    }

    public function reject(User $approver, string $reason = ''): void
    {
        if (! $approver->isAdmin()) {
            throw new \DomainException('Seul un administrateur peut rejeter un document.');
        }

        $this->assertStatusTransitionAllowed(DocumentStatus::REJECTED);

        $previous        = $this->status;
        $this->status    = DocumentStatus::REJECTED;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new DocumentStatusChanged($this->id, $previous, $this->status, $approver->getId(), $this->updatedAt));
    }

    public function archive(User $by): void
    {
        $this->assertStatusTransitionAllowed(DocumentStatus::ARCHIVED);

        $previous        = $this->status;
        $this->status    = DocumentStatus::ARCHIVED;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new DocumentStatusChanged($this->id, $previous, $this->status, $by->getId(), $this->updatedAt));
    }

    public function rename(string $newTitle, User $by): void
    {
        if (! $this->isOwnedBy($by) && ! $by->isAdmin()) {
            throw new DocumentAccessDeniedException($by->getId(), $this->id);
        }

        $this->title     = $newTitle;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function moveToFolder(Folder $target): void
    {
        $this->folder    = $target;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Remplace la liste complète des tags du document.
     *
     * @param list<string> $tags
     */
    public function setTags(array $tags, User $by): void
    {
        if (! $this->isOwnedBy($by) && ! $by->isAdmin()) {
            throw new DocumentAccessDeniedException($by->getId(), $this->id);
        }

        // Nettoyage + déduplication
        $cleaned = array_values(array_unique(
            array_filter(
                array_map('trim', $tags),
                static fn (string $t) => $t !== '',
            ),
        ));

        $this->tags      = $cleaned;
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): DocumentId
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function getFolder(): Folder
    {
        return $this->folder;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    /** @return Collection<int, DocumentVersion> */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function getLatestVersion(): ?DocumentVersion
    {
        if ($this->versions->isEmpty()) {
            return null;
        }

        $last = $this->versions->last();

        return $last instanceof DocumentVersion ? $last : null;
    }

    /** @return list<string> */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner->getId()->equals($user->getId());
    }

    // ── Domain Events ─────────────────────────────────────────────────────────

    /** @return list<object> */
    public function releaseEvents(): array
    {
        $events            = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── Guards privés ────────────────────────────────────────────────────────

    private function assertCanBeModifiedBy(User $user): void
    {
        if (! $this->isOwnedBy($user) && ! $user->isAdmin()) {
            throw new DocumentAccessDeniedException($user->getId(), $this->id);
        }

        if (! $this->status->isEditable()) {
            throw new \DomainException(sprintf(
                'Le document "%s" est en statut "%s" et ne peut pas être modifié.',
                $this->title,
                $this->status->label(),
            ));
        }
    }

    private function assertStatusTransitionAllowed(DocumentStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new \DomainException(sprintf(
                'Transition impossible : "%s" → "%s".',
                $this->status->label(),
                $target->label(),
            ));
        }
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
