<?php

declare(strict_types=1);

namespace App\Domain\Document\Entity;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Document\ValueObject\VersionNumber;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'document_versions')]
#[ORM\UniqueConstraint(name: 'uq_document_version', columns: ['document_id', 'version_number'])]
class DocumentVersion
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'document_id', length: 36)]
    private DocumentId $id;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\Column(name: 'version_number', type: 'smallint', options: ['unsigned' => true])]
    private int $versionNumber;

    #[ORM\Column(name: 'mime_type', length: 100)]
    private string $mimeType;

    #[ORM\Column(name: 'file_size_bytes', type: 'integer', options: ['unsigned' => true])]
    private int $fileSizeBytes;

    #[ORM\Column(name: 'original_filename', length: 255)]
    private string $originalFilename;

    #[ORM\Column(name: 'storage_path', length: 500)]
    private string $storagePath;

    #[ORM\Column(name: 'comment', type: 'text', nullable: true)]
    private ?string $comment;

    /**
     * Texte extrait du contenu du fichier, utilisé pour la recherche full-text.
     * Null si l'extraction n'est pas supportée pour ce type de fichier.
     */
    #[ORM\Column(name: 'content_text', type: 'text', nullable: true)]
    private ?string $contentText = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'uploaded_by', referencedColumnName: 'id', nullable: false)]
    private User $uploadedBy;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        DocumentId $id,
        Document $document,
        VersionNumber $versionNumber,
        MimeType $mimeType,
        FileSize $fileSize,
        string $originalFilename,
        StoragePath $storagePath,
        User $uploadedBy,
        ?string $comment,
    ) {
        $this->id               = $id;
        $this->document         = $document;
        $this->versionNumber    = $versionNumber->getValue();
        $this->mimeType         = $mimeType->getValue();
        $this->fileSizeBytes    = $fileSize->getBytes();
        $this->originalFilename = $originalFilename;
        $this->storagePath      = $storagePath->getValue();
        $this->uploadedBy       = $uploadedBy;
        $this->comment          = $comment;
        $this->createdAt        = new \DateTimeImmutable();
    }

    public static function create(
        Document $document,
        VersionNumber $versionNumber,
        MimeType $mimeType,
        FileSize $fileSize,
        string $originalFilename,
        StoragePath $storagePath,
        User $uploadedBy,
        ?string $comment = null,
    ): self {
        return new self(
            DocumentId::generate(),
            $document,
            $versionNumber,
            $mimeType,
            $fileSize,
            $originalFilename,
            $storagePath,
            $uploadedBy,
            $comment,
        );
    }

    public function getId(): DocumentId
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getVersionNumber(): VersionNumber
    {
        return VersionNumber::fromInt($this->versionNumber);
    }

    public function getMimeType(): MimeType
    {
        return MimeType::fromString($this->mimeType);
    }

    public function getFileSize(): FileSize
    {
        return FileSize::fromBytes($this->fileSizeBytes);
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getStoragePath(): StoragePath
    {
        return StoragePath::fromString($this->storagePath);
    }

    public function getUploadedBy(): User
    {
        return $this->uploadedBy;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getContentText(): ?string
    {
        return $this->contentText;
    }

    /**
     * Appelé après l'extraction asynchrone du texte.
     * Doit être suivi d'un flush() Doctrine.
     */
    public function setContentText(?string $text): void
    {
        $this->contentText = $text;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
