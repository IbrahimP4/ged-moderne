<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Dashboard\Query\GetDashboardStatsHandler;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard', name: 'api_dashboard_')]
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly GetDashboardStatsHandler    $getDashboardStatsHandler,
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $dto = ($this->getDashboardStatsHandler)();

        return $this->json([
            'totalDocuments' => $dto->totalDocuments,
            'totalFolders'   => $dto->totalFolders,
            'totalUsers'     => $dto->totalUsers,
            'byStatus'       => [
                'draft'          => $dto->draft,
                'pending_review' => $dto->pendingReview,
                'approved'       => $dto->approved,
                'rejected'       => $dto->rejected,
            ],
        ]);
    }

    #[Route('/uploads-by-day', name: 'uploads_by_day', methods: ['GET'])]
    public function uploadsByDay(): JsonResponse
    {
        return $this->json($this->documentRepository->countUploadsByDay(30));
    }
}

