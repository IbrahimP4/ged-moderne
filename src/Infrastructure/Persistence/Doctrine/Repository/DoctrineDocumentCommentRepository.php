<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Entity\DocumentComment;
use App\Domain\Document\Repository\DocumentCommentRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineDocumentCommentRepository implements DocumentCommentRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** @return list<DocumentComment> */
    public function findByDocument(Document $document): array
    {
        /** @var list<DocumentComment> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(DocumentComment::class, 'c')
            ->where('c.document = :document')
            ->orderBy('c.createdAt', 'ASC')
            ->setParameter('document', $document)
            ->getQuery()
            ->getResult();
    }

    public function findById(string $id): ?DocumentComment
    {
        return $this->entityManager->find(DocumentComment::class, $id);
    }

    public function save(DocumentComment $comment): void
    {
        $this->entityManager->persist($comment);
        $this->entityManager->flush();
    }

    public function remove(DocumentComment $comment): void
    {
        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }
}
