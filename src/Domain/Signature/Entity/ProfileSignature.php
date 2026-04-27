<?php

declare(strict_types=1);

namespace App\Domain\Signature\Entity;

use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Signature personnelle d'un utilisateur.
 *
 * Stockée sous forme de Data URL (PNG base64).
 * Un seul enregistrement par utilisateur (relation 1-1).
 */
#[ORM\Entity]
#[ORM\Table(name: 'profile_signatures')]
class ProfileSignature
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** PNG base64 Data URL : "data:image/png;base64,..." */
    #[ORM\Column(name: 'data_url', type: 'text')]
    private string $dataUrl;

    /** 'drawn' | 'uploaded' */
    #[ORM\Column(name: 'type', length: 20)]
    private string $type;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(User $user, string $dataUrl, string $type)
    {
        $this->user      = $user;
        $this->dataUrl   = $dataUrl;
        $this->type      = $type;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(User $user, string $dataUrl, string $type): self
    {
        return new self($user, $dataUrl, $type);
    }

    public function update(string $dataUrl, string $type): void
    {
        $this->dataUrl   = $dataUrl;
        $this->type      = $type;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUser(): User           { return $this->user; }
    public function getDataUrl(): string       { return $this->dataUrl; }
    public function getType(): string          { return $this->type; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
