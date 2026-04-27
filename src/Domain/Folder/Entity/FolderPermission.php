<?php

declare(strict_types=1);

namespace App\Domain\Folder\Entity;

use App\Domain\Folder\ValueObject\FolderPermissionId;
use App\Domain\Folder\ValueObject\PermissionLevel;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'folder_permissions')]
#[ORM\UniqueConstraint(name: 'uniq_folder_user', columns: ['folder_id', 'user_id'])]
class FolderPermission
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'folder_permission_id', length: 36)]
    private FolderPermissionId $id;

    #[ORM\ManyToOne(targetEntity: Folder::class)]
    #[ORM\JoinColumn(name: 'folder_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Folder $folder;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'level', type: 'string', length: 10, enumType: PermissionLevel::class)]
    private PermissionLevel $level;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'granted_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $grantedBy;

    #[ORM\Column(name: 'granted_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $grantedAt;

    private function __construct(
        FolderPermissionId $id,
        Folder $folder,
        User $user,
        PermissionLevel $level,
        ?User $grantedBy,
    ) {
        $this->id        = $id;
        $this->folder    = $folder;
        $this->user      = $user;
        $this->level     = $level;
        $this->grantedBy = $grantedBy;
        $this->grantedAt = new \DateTimeImmutable();
    }

    public static function grant(
        Folder $folder,
        User $user,
        PermissionLevel $level,
        ?User $grantedBy = null,
    ): self {
        return new self(FolderPermissionId::generate(), $folder, $user, $level, $grantedBy);
    }

    public function updateLevel(PermissionLevel $level): void
    {
        $this->level = $level;
    }

    public function getId(): FolderPermissionId       { return $this->id; }
    public function getFolder(): Folder               { return $this->folder; }
    public function getUser(): User                   { return $this->user; }
    public function getLevel(): PermissionLevel       { return $this->level; }
    public function getGrantedBy(): ?User             { return $this->grantedBy; }
    public function getGrantedAt(): \DateTimeImmutable { return $this->grantedAt; }

    public function toArray(): array
    {
        return [
            'userId'    => $this->user->getId()->getValue(),
            'username'  => $this->user->getUsername(),
            'level'     => $this->level->value,
            'grantedAt' => $this->grantedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
