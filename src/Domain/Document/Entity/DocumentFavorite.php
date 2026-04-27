<?php

declare(strict_types=1);

namespace App\Domain\Document\Entity;

use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'document_favorites')]
#[ORM\UniqueConstraint(name: 'uniq_doc_user_fav', columns: ['document_id', 'user_id'])]
class DocumentFavorite
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Document $document, User $user)
    {
        $this->document  = $document;
        $this->user      = $user;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getDocument(): Document         { return $this->document; }
    public function getUser(): User                 { return $this->user; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
