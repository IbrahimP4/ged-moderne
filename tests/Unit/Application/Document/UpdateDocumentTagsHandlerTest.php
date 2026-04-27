<?php

declare(strict_types=1);

use App\Application\Document\Command\UpdateDocumentTagsCommand;
use App\Application\Document\Command\UpdateDocumentTagsHandler;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;

describe('UpdateDocumentTagsHandler', function (): void {

    beforeEach(function (): void {
        $this->documentRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $this->userRepo     = Mockery::mock(UserRepositoryInterface::class);

        $this->handler = new UpdateDocumentTagsHandler(
            documentRepository: $this->documentRepo,
            userRepository: $this->userRepo,
        );

        $this->owner  = User::create('alice', 'alice@ged.test', '$2y$10$fakehash');
        $this->folder = Folder::createRoot('DRH', $this->owner);

        $this->document = Document::upload(
            title: 'Contrat',
            folder: $this->folder,
            owner: $this->owner,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromBytes(1024),
            originalFilename: 'contrat.pdf',
            storagePath: StoragePath::fromString('documents/contrat.pdf'),
        );
        $this->document->releaseEvents();
    });

    afterEach(fn () => Mockery::close());

    it('met à jour les tags d\'un document', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn($this->document);
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->owner);
        $this->documentRepo->shouldReceive('save')->once();

        ($this->handler)(new UpdateDocumentTagsCommand(
            documentId: $this->document->getId(),
            updatedBy: $this->owner->getId(),
            tags: ['contrat', 'rh', 'urgent'],
        ));

        expect($this->document->getTags())->toBe(['contrat', 'rh', 'urgent']);
    });

    it('déduplique et nettoie les tags', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn($this->document);
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->owner);
        $this->documentRepo->shouldReceive('save')->once();

        ($this->handler)(new UpdateDocumentTagsCommand(
            documentId: $this->document->getId(),
            updatedBy: $this->owner->getId(),
            tags: ['  contrat ', 'contrat', '', 'rh'],
        ));

        expect($this->document->getTags())->toBe(['contrat', 'rh']);
    });

    it('lève DocumentNotFoundException si le document n\'existe pas', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new UpdateDocumentTagsCommand(
            documentId: DocumentId::generate(),
            updatedBy: $this->owner->getId(),
            tags: ['test'],
        ));
    })->throws(DocumentNotFoundException::class);
});
