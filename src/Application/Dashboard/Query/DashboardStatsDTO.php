<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Query;

final readonly class DashboardStatsDTO
{
    public function __construct(
        public int $totalDocuments,
        public int $totalFolders,
        public int $totalUsers,
        public int $pendingReview,
        public int $approved,
        public int $rejected,
        public int $draft,
    ) {}
}
