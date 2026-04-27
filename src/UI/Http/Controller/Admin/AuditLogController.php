<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Admin;

use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/audit', name: 'api_admin_audit_')]
#[IsGranted('ROLE_ADMIN')]
final class AuditLogController extends AbstractController
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Liste paginée du journal d'audit.
     *
     * Query params :
     *   - page     (int, défaut 1)
     *   - per_page (int, défaut 50, max 200)
     *   - search   (string, filtre texte libre sur eventName/aggregateId)
     *   - category (string, filtre préfixe : document | user | signature | folder)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page    = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(200, max(1, (int) $request->query->get('per_page', '50')));
        $search  = $request->query->get('search')  ?? null;
        $category = $request->query->get('category') ?? null;

        $result  = $this->auditLogRepository->findPaginated($page, $perPage, $search ?: null, $category ?: null);
        $entries = $result['items'];
        $total   = $result['total'];

        $userMap = $this->resolveUserMap($entries);

        return $this->json([
            'data' => array_map(
                fn ($entry) => $this->serializeEntry($entry, $userMap),
                $entries,
            ),
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'pages'     => (int) ceil($total / $perPage),
            ],
        ]);
    }

    #[Route('/export-csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        // Pour l'export, on prend tout (pas de pagination) jusqu'à 5000 entrées
        $limit   = min(5000, max(1, (int) $request->query->get('limit', '1000')));
        $search  = $request->query->get('search') ?? null;
        $category = $request->query->get('category') ?? null;

        $result  = $this->auditLogRepository->findPaginated(1, $limit, $search ?: null, $category ?: null);
        $entries = $result['items'];
        $userMap = $this->resolveUserMap($entries);

        $filename = 'journal_audit_' . date('Ymd_His') . '.csv';

        $response = new StreamedResponse(function () use ($entries, $userMap): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Date',
                'Événement',
                'Type d\'objet',
                'Identifiant objet',
                'Utilisateur',
                'Détails',
            ], ';');

            foreach ($entries as $entry) {
                $actorUsername = $entry->getActorId()
                    ? ($userMap[$entry->getActorId()] ?? $entry->getActorId())
                    : 'Système';

                $payload = $entry->getPayload();
                $details = implode(' | ', array_map(
                    fn ($k, $v) => $k . ': ' . (is_string($v) ? $v : json_encode($v)),
                    array_keys($payload),
                    array_values($payload),
                ));

                fputcsv($handle, [
                    $entry->getOccurredAt()->format('d/m/Y H:i:s'),
                    $entry->getEventName(),
                    $entry->getAggregateType(),
                    $entry->getAggregateId(),
                    $actorUsername,
                    $details,
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="' . $filename . '"',
        );
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    /**
     * Construit un map userId → username en une seule requête SQL.
     *
     * @param list<\App\Domain\AuditLog\Entity\AuditLog> $entries
     * @return array<string, string>
     */
    private function resolveUserMap(array $entries): array
    {
        $actorIds = array_filter(
            array_unique(array_map(fn ($e) => $e->getActorId(), $entries)),
            fn ($id) => $id !== null,
        );

        if (empty($actorIds)) {
            return [];
        }

        /** @var list<User> $users */
        $users = $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', array_values($actorIds))
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($users as $user) {
            $map[$user->getId()->getValue()] = $user->getUsername();
        }

        return $map;
    }

    /**
     * @param array<string, string> $userMap
     * @return array<string, mixed>
     */
    private function serializeEntry(\App\Domain\AuditLog\Entity\AuditLog $entry, array $userMap): array
    {
        return [
            'id'            => $entry->getId()->getValue(),
            'eventName'     => $entry->getEventName(),
            'aggregateType' => $entry->getAggregateType(),
            'aggregateId'   => $entry->getAggregateId(),
            'actorId'       => $entry->getActorId(),
            'actorUsername' => $entry->getActorId() ? ($userMap[$entry->getActorId()] ?? null) : null,
            'payload'       => $entry->getPayload(),
            'occurredAt'    => $entry->getOccurredAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
