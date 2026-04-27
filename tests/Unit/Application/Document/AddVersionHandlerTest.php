<?php

declare(strict_types=1);

use App\Application\Document\Command\AddVersionCommand;
use App\Application\Document\Command\AddVersionHandler;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Port\EventBusInterface;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;

describe('AddVersionHandler', function (): void {

    beforeEach(function (): void {
        $this->documentRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $this->userRepo     = Mockery::mock(UserRepositoryInterface::class);
        $this->storage      = Mockery::mock(DocumentStorageInterface::class);
        $this->eventBus     = Mockery::mock(EventBusInterface::class);
        $this->eventBus->shouldReceive('dispatch')->zeroOrMoreTimes();

        $this->handler = new AddVersionHandler(
            documentRepository: $this->documentRepo,
            userRepository: $this->userRepo,
            storage: $this->storage,
            eventBus: $this->eventBus,
        );

        $this->owner  = User::create('alice', 'alice@ged.test', '$2y$10$fakehash');
        $this->folder = Folder::createRoot('DRH', $this->owner);

        $this->document = \App\Domain\Document\Entity\Document::upload(
            title: 'Contrat',
            folder: $this->folder,
            owner: $this->owner,
            mimeType: \App\Domain\Document\ValueObject\MimeType::fromString('application/pdf'),
            fileSize: \App\Domain\Document\ValueObject\FileSize::fromBytes(1024),
            originalFilename: 'contrat.pdf',
            storagePath: StoragePath::fromString('documents/contrat.pdf'),
        );
        $this->document->releaseEvents();

        $this->tempFile = tempnam(sys_get_temp_dir(), 'ged_ver_');
        file_put_contents($this->tempFile, 'new version content');
    });

    afterEach(function (): void {
        Mockery::close();
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    });

    it('ajoute une nouvelle version à un document existant', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn($this->document);
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->owner);
        $this->storage->shouldReceive('store')->once()->andReturn(StoragePath::fromString('documents/contrat_v2.pdf'));
        $this->documentRepo->shouldReceive('save')->once();

        ($this->handler)(new AddVersionCommand(
            documentId: $this->document->getId(),
            uploadedBy: $this->owner->getId(),
            tempFilePath: $this->tempFile,
            originalFilename: 'contrat_v2.pdf',
            mimeType: 'application/pdf',
            fileSizeBytes: 2048,
            comment: 'Mise à jour annuelle',
        ));

        expect($this->document->getVersions())->toHaveCount(2);
        expect($this->document->getLatestVersion()?->getVersionNumber()->getValue())->toBe(2);
    });

    it('lève DocumentNotFoundException si le document n\'existe pas', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new AddVersionCommand(
            documentId: DocumentId::generate(),
            uploadedBy: $this->owner->getId(),
            tempFilePath: $this->tempFile,
            originalFilename: 'contrat.pdf',
            mimeType: 'application/pdf',
            fileSizeBytes: 1024,
        ));
    })->throws(DocumentNotFoundException::class);
});
