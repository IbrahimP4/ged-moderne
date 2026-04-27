<?php

declare(strict_types=1);

use App\Application\Document\Command\RejectDocumentCommand;
use App\Application\Document\Command\RejectDocumentHandler;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Port\EventBusInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;

describe('RejectDocumentHandler', function (): void {

    beforeEach(function (): void {
        $this->documentRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $this->userRepo     = Mockery::mock(UserRepositoryInterface::class);
        $this->eventBus     = Mockery::mock(EventBusInterface::class);
        $this->eventBus->shouldReceive('dispatch')->zeroOrMoreTimes();

        $this->handler = new RejectDocumentHandler(
            documentRepository: $this->documentRepo,
            userRepository: $this->userRepo,
            eventBus: $this->eventBus,
        );

        $this->owner = User::create('alice', 'alice@ged.test', '$2y$10$fakehash');
        $this->admin = User::create('admin', 'admin@ged.test', '$2y$10$fakehash', isAdmin: true);
        $this->folder = Folder::createRoot('DRH', $this->owner);

        $this->document = Document::upload(
            title: 'Contrat',
            folder: $this->folder,
            owner: $this->owner,
            mimeType: \App\Domain\Document\ValueObject\MimeType::fromString('application/pdf'),
            fileSize: \App\Domain\Document\ValueObject\FileSize::fromBytes(1024),
            originalFilename: 'contrat.pdf',
            storagePath: StoragePath::fromString('documents/contrat.pdf'),
        );
        $this->document->submitForReview($this->owner);
        $this->document->releaseEvents();
    });

    afterEach(fn () => Mockery::close());

    it('rejette un document en attente avec succès', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn($this->document);
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->admin);
        $this->documentRepo->shouldReceive('save')->once();

        ($this->handler)(new RejectDocumentCommand(
            documentId: $this->document->getId(),
            rejectedBy: $this->admin->getId(),
            reason: 'Signature manquante',
        ));

        expect($this->document->getStatus())->toBe(DocumentStatus::REJECTED);
    });

    it('lève DocumentNotFoundException si le document n\'existe pas', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new RejectDocumentCommand(
            documentId: DocumentId::generate(),
            rejectedBy: $this->admin->getId(),
            reason: 'Non conforme',
        ));
    })->throws(DocumentNotFoundException::class);

    it('lève DomainException si l\'utilisateur n\'est pas admin', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn($this->document);
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->owner);

        ($this->handler)(new RejectDocumentCommand(
            documentId: $this->document->getId(),
            rejectedBy: $this->owner->getId(),
            reason: 'Test',
        ));
    })->throws(\DomainException::class, 'Seul un administrateur');
});
