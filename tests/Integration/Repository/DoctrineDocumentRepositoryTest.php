<?php

declare(strict_types=1);

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use Tests\IntegrationTestCase;

uses(IntegrationTestCase::class);

function makeIntegrationFixtures(UserRepositoryInterface $userRepo, FolderRepositoryInterface $folderRepo): array
{
    $owner  = User::create('alice', 'alice@example.com', '$2y$13$fakehash');
    $admin  = User::create('admin', 'admin@example.com', '$2y$13$fakehash', isAdmin: true);
    $folder = \App\Domain\Folder\Entity\Folder::createRoot('DRH', $owner);

    $userRepo->save($owner);
    $userRepo->save($admin);
    $folderRepo->save($folder);

    return compact('owner', 'admin', 'folder');
}

describe('DoctrineDocumentRepository', function (): void {

    beforeEach(function (): void {
        $this->documentRepo = self::getContainer()->get(DocumentRepositoryInterface::class);
        $this->folderRepo   = self::getContainer()->get(FolderRepositoryInterface::class);
        $this->userRepo     = self::getContainer()->get(UserRepositoryInterface::class);

        $fixtures      = makeIntegrationFixtures($this->userRepo, $this->folderRepo);
        $this->owner   = $fixtures['owner'];
        $this->admin   = $fixtures['admin'];
        $this->folder  = $fixtures['folder'];
    });

    it('persiste et retrouve un document par ID', function (): void {
        $doc = Document::upload(
            title: 'Contrat 2026',
            folder: $this->folder,
            owner: $this->owner,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromBytes(1024),
            originalFilename: 'contrat.pdf',
            storagePath: StoragePath::fromString('documents/2026/test.pdf'),
        );

        $this->documentRepo->save($doc);
        $this->em->clear();

        $found = $this->documentRepo->findById($doc->getId());

        expect($found)->not->toBeNull()
            ->and($found->getTitle())->toBe('Contrat 2026')
            ->and($found->getStatus()->value)->toBe('draft');
    });

    it('retourne null pour un ID inexistant', function (): void {
        $id = \App\Domain\Document\ValueObject\DocumentId::generate();

        expect($this->documentRepo->findById($id))->toBeNull();
    });

    it('retrouve les documents d\'un dossier', function (): void {
        foreach (['Doc A', 'Doc B', 'Doc C'] as $title) {
            $doc = Document::upload(
                title: $title,
                folder: $this->folder,
                owner: $this->owner,
                mimeType: MimeType::fromString('application/pdf'),
                fileSize: FileSize::fromBytes(512),
                originalFilename: strtolower(str_replace(' ', '_', $title)) . '.pdf',
                storagePath: StoragePath::fromString('documents/' . $title . '.pdf'),
            );
            $this->documentRepo->save($doc);
        }

        $this->em->clear();

        $docs = $this->documentRepo->findByFolder($this->folder);

        expect($docs)->toHaveCount(3);
    });

    it('compte correctement les documents', function (): void {
        expect($this->documentRepo->count())->toBe(0);

        $doc = Document::upload(
            title: 'Test',
            folder: $this->folder,
            owner: $this->owner,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromBytes(100),
            originalFilename: 'test.pdf',
            storagePath: StoragePath::fromString('documents/test.pdf'),
        );

        $this->documentRepo->save($doc);

        expect($this->documentRepo->count())->toBe(1);
    });

    it('supprime un document', function (): void {
        $doc = Document::upload(
            title: 'A supprimer',
            folder: $this->folder,
            owner: $this->owner,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromBytes(100),
            originalFilename: 'delete_me.pdf',
            storagePath: StoragePath::fromString('documents/delete_me.pdf'),
        );

        $this->documentRepo->save($doc);
        $id = $doc->getId();

        $this->documentRepo->delete($doc);
        $this->em->clear();

        expect($this->documentRepo->findById($id))->toBeNull();
    });

    it('persiste le changement de statut', function (): void {
        $doc = Document::upload(
            title: 'Statut Test',
            folder: $this->folder,
            owner: $this->owner,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromBytes(100),
            originalFilename: 'status.pdf',
            storagePath: StoragePath::fromString('documents/status.pdf'),
        );

        $this->documentRepo->save($doc);
        $id = $doc->getId();

        $doc->submitForReview($this->owner);
        $this->documentRepo->save($doc);
        $this->em->clear();

        $found = $this->documentRepo->findById($id);
        expect($found?->getStatus()->value)->toBe('pending_review');
    });
});
