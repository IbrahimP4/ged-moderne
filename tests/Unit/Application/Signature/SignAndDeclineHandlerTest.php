<?php

declare(strict_types=1);

use App\Application\Signature\Command\SignDocumentCommand;
use App\Application\Signature\Command\SignDocumentHandler;
use App\Application\Signature\Command\DeclineSignatureRequestCommand;
use App\Application\Signature\Command\DeclineSignatureRequestHandler;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Signature\Entity\SignatureRequest;
use App\Domain\Signature\Exception\SignatureRequestNotFoundException;
use App\Domain\Signature\Repository\SignatureRequestRepositoryInterface;
use App\Domain\Signature\ValueObject\SignatureRequestId;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeSignUser(bool $admin = false, string $name = 'user'): User
{
    return User::create(
        username: $name,
        email: $name . '@ged.test',
        hashedPassword: '$2y$10$fakehash',
        isAdmin: $admin,
    );
}

function makeSignDocument(User $owner): Document
{
    $folder = Folder::createRoot('DRH', $owner);

    return Document::upload(
        title: 'Contrat',
        folder: $folder,
        owner: $owner,
        mimeType: MimeType::fromString('application/pdf'),
        fileSize: FileSize::fromBytes(512),
        originalFilename: 'contrat.pdf',
        storagePath: StoragePath::fromString('docs/contrat.pdf'),
    );
}

function makePendingRequest(User $requester, User $signer, Document $doc): SignatureRequest
{
    return SignatureRequest::create(
        document: $doc,
        requester: $requester,
        signer: $signer,
        message: 'Merci de signer.',
    );
}

// ── Tests SignDocumentHandler ─────────────────────────────────────────────────

describe('SignDocumentHandler', function (): void {

    beforeEach(function (): void {
        $this->sigRepo  = Mockery::mock(SignatureRequestRepositoryInterface::class);
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);

        $this->handler = new SignDocumentHandler(
            signatureRequestRepository: $this->sigRepo,
            userRepository:            $this->userRepo,
        );

        $this->requester = makeSignUser(name: 'alice');
        $this->signer    = makeSignUser(admin: true, name: 'admin');
        $this->doc       = makeSignDocument($this->requester);
        $this->request   = makePendingRequest($this->requester, $this->signer, $this->doc);
    });

    afterEach(fn () => Mockery::close());

    it('signe une demande en attente avec succès', function (): void {
        $this->sigRepo->shouldReceive('findById')->once()->andReturn($this->request);
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->signer);
        $this->sigRepo->shouldReceive('save')->once();

        ($this->handler)(new SignDocumentCommand(
            signatureRequestId: $this->request->getId(),
            signedBy:           $this->signer->getId(),
            comment:            'Approuvé.',
        ));

        expect($this->request->getStatus()->value)->toBe('signed');
    });

    it('lève SignatureRequestNotFoundException si la demande est introuvable', function (): void {
        $this->sigRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new SignDocumentCommand(
            signatureRequestId: SignatureRequestId::generate(),
            signedBy:           $this->signer->getId(),
            comment:            null,
        ));
    })->throws(SignatureRequestNotFoundException::class);

    it('lève DomainException si le signataire est introuvable', function (): void {
        $this->sigRepo->shouldReceive('findById')->once()->andReturn($this->request);
        $this->userRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new SignDocumentCommand(
            signatureRequestId: $this->request->getId(),
            signedBy:           UserId::generate(),
            comment:            null,
        ));
    })->throws(\DomainException::class, 'introuvable');

    it('ne peut pas signer une demande déjà signée', function (): void {
        $this->request->sign($this->signer, null);

        $this->sigRepo->shouldReceive('findById')->once()->andReturn($this->request);
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->signer);

        ($this->handler)(new SignDocumentCommand(
            signatureRequestId: $this->request->getId(),
            signedBy:           $this->signer->getId(),
            comment:            null,
        ));
    })->throws(\DomainException::class);

    it('ne sauvegarde pas si une exception est levée', function (): void {
        $this->sigRepo->shouldReceive('findById')->once()->andReturn($this->request);
        $this->userRepo->shouldReceive('findById')->once()->andReturn(null);
        $this->sigRepo->shouldNotReceive('save');

        try {
            ($this->handler)(new SignDocumentCommand(
                signatureRequestId: $this->request->getId(),
                signedBy:           UserId::generate(),
                comment:            null,
            ));
        } catch (\DomainException) {
        }
    });
});

// ── Tests DeclineSignatureRequestHandler ─────────────────────────────────────

describe('DeclineSignatureRequestHandler', function (): void {

    beforeEach(function (): void {
        $this->sigRepo  = Mockery::mock(SignatureRequestRepositoryInterface::class);
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);

        $this->handler = new DeclineSignatureRequestHandler(
            signatureRequestRepository: $this->sigRepo,
            userRepository:            $this->userRepo,
        );

        $this->requester = makeSignUser(name: 'bob');
        $this->signer    = makeSignUser(admin: true, name: 'admin2');
        $this->doc       = makeSignDocument($this->requester);
        $this->request   = makePendingRequest($this->requester, $this->signer, $this->doc);
    });

    afterEach(fn () => Mockery::close());

    it('refuse une demande de signature avec succès', function (): void {
        $this->sigRepo->shouldReceive('findById')->once()->andReturn($this->request);
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->signer);
        $this->sigRepo->shouldReceive('save')->once();

        ($this->handler)(new DeclineSignatureRequestCommand(
            signatureRequestId: $this->request->getId(),
            declinedBy:         $this->signer->getId(),
            comment:            'Document incomplet.',
        ));

        expect($this->request->getStatus()->value)->toBe('declined');
    });

    it('lève SignatureRequestNotFoundException si la demande est introuvable', function (): void {
        $this->sigRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new DeclineSignatureRequestCommand(
            signatureRequestId: SignatureRequestId::generate(),
            declinedBy:         $this->signer->getId(),
            comment:            null,
        ));
    })->throws(SignatureRequestNotFoundException::class);

    it('lève DomainException si l\'utilisateur est introuvable', function (): void {
        $this->sigRepo->shouldReceive('findById')->once()->andReturn($this->request);
        $this->userRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new DeclineSignatureRequestCommand(
            signatureRequestId: $this->request->getId(),
            declinedBy:         UserId::generate(),
            comment:            null,
        ));
    })->throws(\DomainException::class, 'introuvable');

    it('ne peut pas refuser une demande déjà déclinée', function (): void {
        $this->request->decline($this->signer, null);

        $this->sigRepo->shouldReceive('findById')->once()->andReturn($this->request);
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->signer);

        ($this->handler)(new DeclineSignatureRequestCommand(
            signatureRequestId: $this->request->getId(),
            declinedBy:         $this->signer->getId(),
            comment:            null,
        ));
    })->throws(\DomainException::class);
});
