<?php

declare(strict_types=1);

namespace App\Domain\Folder\Entity;

use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'folders')]
#[ORM\HasLifecycleCallbacks]
class Folder
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'folder_id', length: 36)]
    private FolderId $id;

    #[ORM\Column(name: 'name', length: 255)]
    private string $name;

    #[ORM\Column(name: 'comment', type: 'text', nullable: true)]
    private ?string $comment;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?self $parent;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $children;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
    private User $owner;

    /**
     * Quand restricted = true, seuls les utilisateurs avec une FolderPermission explicite
     * (+ les admins + le propriétaire) peuvent accéder à ce dossier.
     */
    #[ORM\Column(name: 'restricted', options: ['default' => false])]
    private bool $restricted = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        FolderId $id,
        string $name,
        User $owner,
        ?self $parent,
        ?string $comment,
    ) {
        $this->id         = $id;
        $this->name       = $name;
        $this->owner      = $owner;
        $this->parent     = $parent;
        $this->comment    = $comment;
        $this->restricted = false;
        $this->children   = new ArrayCollection();
        $this->createdAt  = new \DateTimeImmutable();
        $this->updatedAt  = new \DateTimeImmutable();
    }

    public static function create(
        string $name,
        User $owner,
        ?self $parent = null,
        ?string $comment = null,
    ): self {
        return new self(FolderId::generate(), $name, $owner, $parent, $comment);
    }

    public static function createRoot(string $name, User $owner): self
    {
        return new self(FolderId::generate(), $name, $owner, null, 'Dossier racine');
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): FolderId { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getComment(): ?string { return $this->comment; }
    public function getParent(): ?self { return $this->parent; }
    public function getOwner(): User { return $this->owner; }
    public function isRestricted(): bool { return $this->restricted; }

    /** @return Collection<int, self> */
    public function getChildren(): Collection { return $this->children; }

    public function isRoot(): bool { return $this->parent === null; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    // ── Mutations ─────────────────────────────────────────────────────────────

    public function rename(string $newName): void
    {
        $this->name      = $newName;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setRestricted(bool $restricted): void
    {
        $this->restricted = $restricted;
        $this->updatedAt  = new \DateTimeImmutable();
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Reconstruit le chemin complet (ex: "DRH / Contrats / 2026").
     */
    public function getFullPath(string $separator = ' / '): string
    {
        if ($this->parent === null) {
            return $this->name;
        }

        return $this->parent->getFullPath($separator) . $separator . $this->name;
    }
}
