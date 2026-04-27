<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\AuditLog\Entity\AuditLog;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function append(AuditLog $log): void
    {
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /** @return list<AuditLog> */
    public function findByAggregate(string $aggregateType, string $aggregateId): array
    {
        /** @var list<AuditLog> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->where('a.aggregateType = :type')
            ->andWhere('a.aggregateId = :id')
            ->orderBy('a.occurredAt', 'ASC')
            ->setParameter('type', $aggregateType)
            ->setParameter('id', $aggregateId)
            ->getQuery()
            ->getResult();
    }

    /** @return list<AuditLog> */
    public function findRecent(int $limit = 200): array
    {
        /** @var list<AuditLog> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Pagination côté serveur avec filtre texte libre et catégorie d'événement.
     *
     * @return array{items: list<AuditLog>, total: int}
     */
    public function findPaginated(
        int $page = 1,
        int $perPage = 50,
        ?string $search = null,
        ?string $category = null,
    ): array {
        $qb = $this->entityManager
            ->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->orderBy('a.occurredAt', 'DESC');

        // Filtre texte libre sur le nom d'événement ou l'aggregateId
        if ($search !== null && $search !== '') {
            $qb->andWhere('a.eventName LIKE :search OR a.aggregateId LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtre catégorie : on filtre par préfixe d'eventName (ex: "document.", "user.")
        if ($category !== null && $category !== '') {
            $qb->andWhere('a.eventName LIKE :cat')
               ->setParameter('cat', $category . '.%');
        }

        // Compte total pour la pagination
        $countQb = clone $qb;
        $total   = (int) $countQb
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Page courante
        $items = $qb
            ->select('a')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function countTotal(): int
    {
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(AuditLog::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
