<?php

declare(strict_types=1);

use App\Application\Notification\EventListener\NotifyOnSignatureRequested;
use App\Application\Notification\Service\CreateNotificationService;
use App\Application\Signature\Command\CreateSignatureRequestCommand;
use App\Application\Signature\Command\CreateSignatureRequestHandler;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Signature\Entity\SignatureRequest;
use App\Domain\Signature\Repository\SignatureRequestRepositoryInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeSignatureUser(bool $admin = false, string $suffix = ''): User
{
    return User::create(
        username: ($admin ? 'admin' : 'user') . $suffix,
        email: ($admin ? 'admin' : 'user') . $suffix . '@ged.test',
        hashedPassword: '$2y$10$fakehash',
        isAdmin: $admin,
    );
}

function makeSignatureDocument(User $owner): Document
{
    $folder = Folder::createRoot('DRH', $owner);

    return Document::upload(
        title: 'Contrat de maintenance',
        folder: $folder,
        owner: $owner,
        mimeType: MimeType::fromString('application/pdf'),
        fileSize: FileSize::fromBytes(102400),
        originalFilename: 'contrat.pdf',
        storagePath: StoragePath::fromString('documents/2026/contrat.pdf'),
    );
}

// Stub concret car NotifyOnSignatureRequested est `final`
function makeNotifier(): NotifyOnSignatureRequested
{
    $notifRepo  = Mockery::mock(NotificationRepositoryInterface::class);
    $notifRepo->shouldReceive('save')->zeroOrMoreTimes();

    $userRepo = Mockery::mock(UserRepositoryInterface::class);
    $userRepo->shouldReceive('findById')->zeroOrMoreTimes()->andReturn(null);

    $createService = new CreateNotificationService($notifRepo);
    return new NotifyOnSignatureRequested($createService, $userRepo);
}

// ── Tests CreateSignatureRequestHandler ──────────────────────────────────────

describe('CreateSignatureRequestHandler', function (): void {

    beforeEach(function (): void {
        $this->documentRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $this->userRepo     = Mockery::mock(UserRepositoryInterface::class);
        $this->sigRepo      = Mockery::mock(SignatureRequestRepositoryInterface::class);
        $this->notifier     = makeNotifier();

        $this->handler = new CreateSignatureRequestHandler(
            documentRepository:         $this->documentRepo,
            userRepository:             $this->userRepo,
            signatureRequestRepository: $this->sigRepo,
            notifyOnSignatureRequested: $this->notifier,
        );

        $this->requester = makeSignatureUser(suffix: '_requester');
        $this->signer    = makeSignatureUser(admin: true, suffix: '_signer');
        $this->document  = makeSignatureDocument($this->requester);
    });

    afterEach(fn () => Mockery::close());

    it('crée une demande de signature avec succès', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn($this->document);
        $this->userRepo->shouldReceive('findById')
            ->twice()
            ->andReturn($this->requester, $this->signer);
        $this->sigRepo->shouldReceive('save')->once();

        $result = ($this->handler)(new CreateSignatureRequestCommand(
            documentId:  $this->document->getId(),
            requesterId: $this->requester->getId(),
            signerId:    $this->signer->getId(),
            message:     'Merci de signer ce contrat.',
        ));

        expect($result)->toBeInstanceOf(SignatureRequest::class);
        expect($result->getStatus()->value)->toBe('pending');
    });

    it('lève DocumentNotFoundException si le document est introuvable', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new CreateSignatureRequestCommand(
            documentId:  DocumentId::generate(),
            requesterId: $this->requester->getId(),
            signerId:    $this->signer->getId(),
            message:     null,
        ));
    })->throws(DocumentNotFoundException::class);

    it('lève DomainException si le demandeur est introuvable', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn($this->document);
        $this->userRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new CreateSignatureRequestCommand(
            documentId:  $this->document->getId(),
            requesterId: UserId::generate(),
            signerId:    $this->signer->getId(),
            message:     null,
        ));
    })->throws(\DomainException::class, 'demandeur');

    it('lève DomainException si le signataire est introuvable', function (): void {
        $this->documentRepo->shouldReceive('findById')->once()->andReturn($this->document);
        $this->userRepo->shouldReceive('findById')
            ->twice()
            ->andReturn($this->requester, null);

        ($this->handler)(new CreateSignatureRequestCommand(
            documentId:  $this->document->getId(),
            requesterId: $this->requester->getId(),
            signerId:    UserId::generate(),
            message:     null,
        ));
    })->throws(\DomainException::class, 'Signataire');

    it('lève DomainException si le demandeur et le signataire sont le même utilisateur', function (): void {
        $self = makeSignatureUser(admin: true, suffix: '_self');

        $this->documentRepo->shouldReceive('findById')->once()->andReturn($this->document);
        $this->userRepo->shouldReceive('findById')
            ->twice()
            ->andReturn($self, $self);
        // Pas de save — la DomainException est levée dans SignatureRequest::create()

        ($this->handler)(new CreateSignatureRequestCommand(
            documentId:  $this->document->getId(),
            requesterId: $self->getId(),
            signerId:    $self->getId(),
            message:     null,
        ));
    })->throws(\DomainException::class, 'vous-même');
});
