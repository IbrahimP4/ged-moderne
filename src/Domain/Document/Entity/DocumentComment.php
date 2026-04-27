<?php

declare(strict_types=1);

namespace App\Domain\Document\Entity;

use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'document_comments')]
class DocumentComment
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(name: 'content', type: 'text')]
    private string $content;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Document $document, User $author, string $content)
    {
        $this->id        = (string) Uuid::v7();
        $this->document  = $document;
        $this->author    = $author;
        $this->content   = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string                 { return $this->id; }
    public function getDocument(): Document         { return $this->document; }
    public function getAuthor(): User               { return $this->author; }
    public function getContent(): string            { return $this->content; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'authorId'  => $this->author->getId()->getValue(),
            'username'  => $this->author->getUsername(),
            'content'   => $this->content,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
