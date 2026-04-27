<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Document;

use App\Application\Document\Query\SearchDocumentsHandler;
use App\Application\Document\Query\SearchDocumentsQuery;
use App\Application\Document\Query\SearchResultDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/search', name: 'api_search_')]
#[IsGranted('ROLE_USER')]
final class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchDocumentsHandler $searchDocumentsHandler,
    ) {}

    #[Route('', name: 'documents', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q        = trim((string) $request->query->get('q', ''));
        $folderId = $request->query->get('folder_id');
        $status   = $request->query->get('status');
        $tags     = $request->query->all('tags');
        $limit    = min(100, max(1, (int) $request->query->get('limit', '50')));

        if (strlen($q) < 2) {
            return $this->json(['results' => [], 'total' => 0, 'query' => $q]);
        }

        /** @var list<SearchResultDTO> $results */
        $results = ($this->searchDocumentsHandler)(new SearchDocumentsQuery(
            q: $q,
            folderId: is_string($folderId) && $folderId !== '' ? $folderId : null,
            status: is_string($status) && $status !== '' ? $status : null,
            tags: array_values(array_filter($tags, 'is_string')),
            limit: $limit,
        ));

        return $this->json([
            'results' => array_map(static fn (SearchResultDTO $r) => [
                'id'               => $r->id,
                'title'            => $r->title,
                'status'           => $r->status,
                'statusLabel'      => $r->statusLabel,
                'folderId'         => $r->folderId,
                'folderName'       => $r->folderName,
                'ownerUsername'    => $r->ownerUsername,
                'versionCount'     => $r->versionCount,
                'mimeType'         => $r->mimeType,
                'fileSizeBytes'    => $r->fileSizeBytes,
                'createdAt'        => $r->createdAt,
                'updatedAt'        => $r->updatedAt,
                'tags'             => $r->tags,
                'snippet'          => $r->snippet,
                'matchedInContent' => $r->matchedInContent,
            ], $results),
            'total' => count($results),
            'query' => $q,
        ]);
    }
}
