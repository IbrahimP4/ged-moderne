<?php

declare(strict_types=1);

namespace App\Domain\AuditLog\Repository;

use App\Domain\AuditLog\Entity\AuditLog;

interface AuditLogRepositoryInterface
{
    public function append(AuditLog $log): void;

    /** @return list<AuditLog> */
    public function findByAggregate(string $aggregateType, string $aggregateId): array;

    /** @return list<AuditLog> */
    public function findRecent(int $limit = 200): array;

    /**
     * Retourne une page de logs avec le total pour la pagination.
     *
     * @return array{items: list<AuditLog>, total: int}
     */
    public function findPaginated(
        int $page = 1,
        int $perPage = 50,
        ?string $search = null,
        ?string $category = null,
    ): array;

    public function countTotal(): int;
}

