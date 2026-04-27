<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Query;

use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class GetDashboardStatsHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function __invoke(): DashboardStatsDTO
    {
        return new DashboardStatsDTO(
            totalDocuments: $this->documentRepository->count(),
            totalFolders:   $this->folderRepository->count(),
            totalUsers:     $this->userRepository->count(),
            pendingReview:  $this->documentRepository->countByStatus('pending_review'),
            approved:       $this->documentRepository->countByStatus('approved'),
            rejected:       $this->documentRepository->countByStatus('rejected'),
            draft:          $this->documentRepository->countByStatus('draft'),
        );
    }
}
