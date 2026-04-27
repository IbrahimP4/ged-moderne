<?php

declare(strict_types=1);

namespace App\Domain\Signature\Entity;

use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tampon officiel de l'entreprise (unique, géré par les admins).
 *
 * Une seule instance en base (id = 1 fixe).
 */
#[ORM\Entity]
#[ORM\Table(name: 'company_stamp')]
class CompanyStamp
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $id = 1;

    /** PNG base64 Data URL */
    #[ORM\Column(name: 'data_url', type: 'text')]
    private string $dataUrl;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by', referencedColumnName: 'id', nullable: true)]
    private ?User $updatedBy;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(string $dataUrl, ?User $updatedBy)
    {
        $this->dataUrl   = $dataUrl;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(string $dataUrl, ?User $updatedBy = null): self
    {
        return new self($dataUrl, $updatedBy);
    }

    public function update(string $dataUrl, ?User $updatedBy): void
    {
        $this->dataUrl   = $dataUrl;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getDataUrl(): string      { return $this->dataUrl; }
    public function getUpdatedBy(): ?User      { return $this->updatedBy; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
