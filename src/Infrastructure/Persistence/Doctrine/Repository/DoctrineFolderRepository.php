<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Folder\ValueObject\FolderId;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFolderRepository implements FolderRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function findById(FolderId $id): ?Folder
    {
        return $this->entityManager->find(Folder::class, $id);
    }

    public function findRoot(): ?Folder
    {
        /** @var Folder|null */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('f')
            ->from(Folder::class, 'f')
            ->where('f.parent IS NULL')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Folder> */
    public function findChildren(Folder $parent): array
    {
        /** @var list<Folder> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('f')
            ->from(Folder::class, 'f')
            ->where('f.parent = :parent')
            ->orderBy('f.name', 'ASC')
            ->setParameter('parent', $parent)
            ->getQuery()
            ->getResult();
    }

    public function save(Folder $folder): void
    {
        $this->entityManager->persist($folder);
        $this->entityManager->flush();
    }

    public function delete(Folder $folder): void
    {
        $this->entityManager->remove($folder);
        $this->entityManager->flush();
    }

    public function count(): int
    {
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(Folder::class, 'f')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
