<?php

declare(strict_types=1);

use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

uses(FunctionalTestCase::class);

function makeTempPdf(string $content = '%PDF-1.4 fake pdf content'): string
{
    $path = tempnam(sys_get_temp_dir(), 'test_pdf_') . '.pdf';
    file_put_contents($path, $content);

    return $path;
}

describe('DocumentUploadController', function (): void {

    beforeEach(function (): void {
        $this->userRepo   = self::getContainer()->get(UserRepositoryInterface::class);
        $this->folderRepo = self::getContainer()->get(FolderRepositoryInterface::class);

        $this->owner  = User::create('alice', 'alice@example.com', '$2y$13$fakehash');
        $this->folder = Folder::createRoot('DRH', $this->owner);

        $this->userRepo->save($this->owner);
        $this->folderRepo->save($this->folder);
        $this->em->clear();

        $this->folderId = $this->folder->getId()->getValue();
    });

    afterEach(function (): void {
        // Nettoyage des fichiers temporaires créés pendant les tests
        $testStorageDir = self::$kernel->getProjectDir() . '/var/storage/test';
        if (is_dir($testStorageDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($testStorageDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }
        }
    });

    // ── POST /api/documents ───────────────────────────────────────────────────

    it('upload un document valide et retourne 201', function (): void {
        $tmpFile = makeTempPdf();

        $this->loginAs($this->owner);
        $this->client->request(
            'POST',
            '/api/documents',
            ['title' => 'Contrat 2026', 'folder_id' => $this->folderId],
            ['file' => new UploadedFile($tmpFile, 'contrat.pdf', 'application/pdf', null, true)],
        );

        @unlink($tmpFile);

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_CREATED);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        expect($data)->toHaveKey('id')
            ->and($data['message'])->toBe('Document uploadé avec succès.');
    });

    it('retourne 422 si le champ "file" est absent', function (): void {
        $this->loginAs($this->owner);
        $this->client->request(
            'POST',
            '/api/documents',
            ['title' => 'Test', 'folder_id' => $this->folderId],
        );

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    });

    it('retourne 422 si "title" est absent', function (): void {
        $tmpFile = makeTempPdf();

        $this->loginAs($this->owner);
        $this->client->request(
            'POST',
            '/api/documents',
            ['folder_id' => $this->folderId],
            ['file' => new UploadedFile($tmpFile, 'contrat.pdf', 'application/pdf', null, true)],
        );

        @unlink($tmpFile);

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    });

    it('retourne 422 si "folder_id" n\'est pas un UUID valide', function (): void {
        $tmpFile = makeTempPdf();

        $this->loginAs($this->owner);
        $this->client->request(
            'POST',
            '/api/documents',
            ['title' => 'Test', 'folder_id' => 'not-a-uuid'],
            ['file' => new UploadedFile($tmpFile, 'contrat.pdf', 'application/pdf', null, true)],
        );

        @unlink($tmpFile);

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    });

    it('retourne 422 si "title" dépasse 255 caractères', function (): void {
        $tmpFile = makeTempPdf();

        $this->loginAs($this->owner);
        $this->client->request(
            'POST',
            '/api/documents',
            ['title' => str_repeat('x', 256), 'folder_id' => $this->folderId],
            ['file' => new UploadedFile($tmpFile, 'contrat.pdf', 'application/pdf', null, true)],
        );

        @unlink($tmpFile);

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    });

    it('retourne 401 sans authentification', function (): void {
        $tmpFile = makeTempPdf();

        $this->client->request(
            'POST',
            '/api/documents',
            ['title' => 'Test', 'folder_id' => $this->folderId],
            ['file' => new UploadedFile($tmpFile, 'contrat.pdf', 'application/pdf', null, true)],
        );

        @unlink($tmpFile);

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
    });
});
