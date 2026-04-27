<?php

declare(strict_types=1);

use App\Domain\AuditLog\Entity\AuditLog;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

uses(FunctionalTestCase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function seedAuditLogs(
    AuditLogRepositoryInterface $repo,
    User $user,
    int $count = 5,
    string $eventName = 'document.uploaded',
): void {
    for ($i = 0; $i < $count; $i++) {
        $repo->append(AuditLog::record(
            eventName:     $eventName,
            aggregateType: 'Document',
            aggregateId:   sprintf('doc-id-%03d', $i),
            actorId:       $user->getId()->getValue(),
            payload:       ['title' => "Document $i", 'index' => $i],
        ));
    }
}

describe('AuditLogController — API admin', function (): void {

    beforeEach(function (): void {
        $this->userRepo   = self::getContainer()->get(UserRepositoryInterface::class);
        $this->auditRepo  = self::getContainer()->get(AuditLogRepositoryInterface::class);

        $this->admin  = User::create('adminaudit', 'adminaudit@ged.test', '$2y$13$fakehash', true);
        $this->viewer = User::create('viewer',     'viewer@ged.test',     '$2y$13$fakehash', false);

        $this->userRepo->save($this->admin);
        $this->userRepo->save($this->viewer);
        $this->em->clear();
    });

    // ── GET /api/admin/audit ──────────────────────────────────────────────────

    it('retourne 401 sans authentification', function (): void {
        $this->client->request('GET', '/api/admin/audit');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
    });

    it('retourne 403 pour un utilisateur non-admin', function (): void {
        $this->loginAs($this->viewer);
        $this->client->request('GET', '/api/admin/audit');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    it('retourne 200 avec pagination par défaut pour un admin', function (): void {
        seedAuditLogs($this->auditRepo, $this->admin, 3);

        $this->loginAs($this->admin);
        $this->client->request('GET', '/api/admin/audit');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK);

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);

        expect($body)->toHaveKey('data')
            ->and($body)->toHaveKey('pagination')
            ->and($body['pagination'])->toHaveKey('total')
            ->and($body['pagination']['total'])->toBeGreaterThanOrEqual(3)
            ->and($body['pagination'])->toHaveKey('page')
            ->and($body['pagination']['pages'])->toBeGreaterThanOrEqual(1);
    });

    it('respecte le paramètre per_page', function (): void {
        seedAuditLogs($this->auditRepo, $this->admin, 10);

        $this->loginAs($this->admin);
        $this->client->request('GET', '/api/admin/audit?per_page=3&page=1');

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);

        expect($body['data'])->toHaveCount(3)
            ->and($body['pagination']['per_page'])->toBe(3);
    });

    it('retourne la page 2 correctement', function (): void {
        seedAuditLogs($this->auditRepo, $this->admin, 10);

        $this->loginAs($this->admin);
        $this->client->request('GET', '/api/admin/audit?per_page=4&page=2');

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);

        expect($body['pagination']['page'])->toBe(2)
            ->and(count($body['data']))->toBeGreaterThanOrEqual(1);
    });

    it('chaque entrée contient les champs attendus', function (): void {
        seedAuditLogs($this->auditRepo, $this->admin, 1);

        $this->loginAs($this->admin);
        $this->client->request('GET', '/api/admin/audit?per_page=1');

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);

        expect($body['data'])->not->toBeEmpty();

        $entry = $body['data'][0];
        foreach (['id', 'eventName', 'aggregateType', 'aggregateId', 'actorId', 'payload', 'occurredAt'] as $key) {
            expect($entry)->toHaveKey($key);
        }
        // actorUsername doit être résolu
        expect($entry['actorUsername'])->toBe('adminaudit');
    });

    it('filtre par catégorie', function (): void {
        seedAuditLogs($this->auditRepo, $this->admin, 3, 'document.uploaded');
        seedAuditLogs($this->auditRepo, $this->admin, 2, 'user.created');

        $this->loginAs($this->admin);
        $this->client->request('GET', '/api/admin/audit?category=user&per_page=50');

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);

        foreach ($body['data'] as $entry) {
            expect($entry['eventName'])->toStartWith('user.');
        }
    });

    // ── GET /api/admin/audit/export-csv ──────────────────────────────────────

    it('retourne un CSV valide pour l\'admin', function (): void {
        seedAuditLogs($this->auditRepo, $this->admin, 3);

        $this->loginAs($this->admin);
        $this->client->request('GET', '/api/admin/audit/export-csv');

        $response = $this->client->getResponse();

        // StreamedResponse — on valide le status + les headers (le contenu streamed
        // n'est pas capturé par getContent() en test Symfony)
        expect($response->getStatusCode())->toBe(Response::HTTP_OK)
            ->and($response->headers->get('Content-Type'))->toContain('text/csv')
            ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
            ->and($response->headers->get('Content-Disposition'))->toContain('journal_audit_');
    });

    it('retourne 403 pour le CSV sans être admin', function (): void {
        $this->loginAs($this->viewer);
        $this->client->request('GET', '/api/admin/audit/export-csv');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });
});
