<?php

declare(strict_types=1);

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

uses(FunctionalTestCase::class);

function makeTestDocument(Folder $folder, User $owner): Document
{
    return Document::upload(
        title: 'Contrat test',
        folder: $folder,
        owner: $owner,
        mimeType: MimeType::fromString('application/pdf'),
        fileSize: FileSize::fromBytes(1024),
        originalFilename: 'contrat.pdf',
        storagePath: StoragePath::fromString('documents/contrat.pdf'),
    );
}

describe('Document Workflow (submit / approve / reject)', function (): void {

    beforeEach(function (): void {
        $this->userRepo     = self::getContainer()->get(UserRepositoryInterface::class);
        $this->folderRepo   = self::getContainer()->get(FolderRepositoryInterface::class);
        $this->documentRepo = self::getContainer()->get(DocumentRepositoryInterface::class);

        $this->owner  = User::create('alice', 'alice@example.com', '$2y$13$fakehash');
        $this->admin  = User::create('admin', 'admin@example.com', '$2y$13$fakehash', isAdmin: true);
        $this->folder = Folder::createRoot('DRH', $this->owner);

        $this->userRepo->save($this->owner);
        $this->userRepo->save($this->admin);
        $this->folderRepo->save($this->folder);

        $this->document = makeTestDocument($this->folder, $this->owner);
        $this->documentRepo->save($this->document);

        $this->em->clear();
    });

    // ── PATCH /api/documents/{id}/submit ─────────────────────────────────────

    it('soumet un document en révision', function (): void {
        $this->loginAs($this->owner);
        $this->client->request('PATCH', '/api/documents/' . $this->document->getId()->getValue() . '/submit');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

        $this->em->clear();
        $doc = $this->documentRepo->findById($this->document->getId());
        expect($doc?->getStatus())->toBe(DocumentStatus::PENDING_REVIEW);
    });

    it('retourne 401 si non authentifié pour submit', function (): void {
        $this->client->request('PATCH', '/api/documents/' . $this->document->getId()->getValue() . '/submit');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
    });

    it('retourne 400 si l\'id est invalide pour submit', function (): void {
        $this->loginAs($this->owner);
        $this->client->request('PATCH', '/api/documents/not-a-uuid/submit');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);
    });

    // ── PATCH /api/documents/{id}/approve ────────────────────────────────────

    it('approuve un document en attente', function (): void {
        $doc   = $this->documentRepo->findById($this->document->getId());
        $owner = $this->userRepo->findById($this->owner->getId());
        $doc->submitForReview($owner);
        $this->documentRepo->save($doc);
        $this->em->clear();

        $this->loginAs($this->admin);
        $this->client->request('PATCH', '/api/documents/' . $this->document->getId()->getValue() . '/approve');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

        $this->em->clear();
        $doc = $this->documentRepo->findById($this->document->getId());
        expect($doc?->getStatus())->toBe(DocumentStatus::APPROVED);
    });

    it('retourne 403 si un utilisateur normal tente d\'approuver', function (): void {
        $this->loginAs($this->owner);
        $this->client->request('PATCH', '/api/documents/' . $this->document->getId()->getValue() . '/approve');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    // ── PATCH /api/documents/{id}/reject ─────────────────────────────────────

    it('rejette un document en attente avec une raison', function (): void {
        $doc   = $this->documentRepo->findById($this->document->getId());
        $owner = $this->userRepo->findById($this->owner->getId());
        $doc->submitForReview($owner);
        $this->documentRepo->save($doc);
        $this->em->clear();

        $this->loginAs($this->admin);
        $this->client->request(
            'PATCH',
            '/api/documents/' . $this->document->getId()->getValue() . '/reject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['reason' => 'Signature manquante'], JSON_THROW_ON_ERROR),
        );

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

        $this->em->clear();
        $doc = $this->documentRepo->findById($this->document->getId());
        expect($doc?->getStatus())->toBe(DocumentStatus::REJECTED);
    });

    it('retourne 403 si un utilisateur normal tente de rejeter', function (): void {
        $this->loginAs($this->owner);
        $this->client->request(
            'PATCH',
            '/api/documents/' . $this->document->getId()->getValue() . '/reject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['reason' => 'Test'], JSON_THROW_ON_ERROR),
        );

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });
});
