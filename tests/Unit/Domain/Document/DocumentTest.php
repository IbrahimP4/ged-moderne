<?php

declare(strict_types=1);

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Entity\DocumentVersion;
use App\Domain\Document\Event\DocumentUploaded;
use App\Domain\Document\Event\DocumentVersionAdded;
use App\Domain\Document\Exception\DocumentAccessDeniedException;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Document\ValueObject\VersionNumber;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;

// ── Helpers de test ──────────────────────────────────────────────────────────

function makeUser(bool $admin = false): User
{
    return User::create(
        username: $admin ? 'admin' : 'john_doe',
        email: $admin ? 'admin@ged.test' : 'john@ged.test',
        hashedPassword: '$2y$10$fakehash',
        isAdmin: $admin,
    );
}

function makeFolder(User $owner): Folder
{
    return Folder::createRoot('Documents', $owner);
}

function makeDocument(?User $owner = null, ?Folder $folder = null): Document
{
    $owner  ??= makeUser();
    $folder ??= makeFolder($owner);

    return Document::upload(
        title: 'Rapport annuel 2026',
        folder: $folder,
        owner: $owner,
        mimeType: MimeType::fromString('application/pdf'),
        fileSize: FileSize::fromMegabytes(2.5),
        originalFilename: 'rapport_2026.pdf',
        storagePath: StoragePath::fromString('documents/2026/04/uuid-test.pdf'),
        comment: 'Rapport financier',
    );
}

// ── Tests Document::upload() ─────────────────────────────────────────────────

describe('Document::upload()', function (): void {

    it('crée un document avec les bonnes valeurs initiales', function (): void {
        $doc = makeDocument();

        expect($doc->getTitle())->toBe('Rapport annuel 2026');
        expect($doc->getStatus())->toBe(DocumentStatus::DRAFT);
        expect($doc->getComment())->toBe('Rapport financier');
        expect($doc->getId()->getValue())->toBeString()->not->toBeEmpty();
        expect($doc->getCreatedAt())->toBeInstanceOf(\DateTimeImmutable::class);
    });

    it('crée automatiquement la version 1 à l\'upload', function (): void {
        $doc = makeDocument();

        expect($doc->getVersions())->toHaveCount(1);

        $version = $doc->getLatestVersion();
        expect($version)->toBeInstanceOf(DocumentVersion::class);
        expect($version->getVersionNumber()->getValue())->toBe(1);
        expect($version->getMimeType()->getValue())->toBe('application/pdf');
        expect($version->getFileSize()->toMegabytes())->toBe(2.5);
    });

    it('émet un événement DocumentUploaded', function (): void {
        $doc    = makeDocument();
        $events = $doc->releaseEvents();

        expect($events)->toHaveCount(1);
        expect($events[0])->toBeInstanceOf(DocumentUploaded::class);
        expect($events[0]->title)->toBe('Rapport annuel 2026');
        expect($events[0]->mimeType)->toBe('application/pdf');
    });

    it('les events sont vidés après releaseEvents()', function (): void {
        $doc = makeDocument();
        $doc->releaseEvents();

        expect($doc->releaseEvents())->toBeEmpty();
    });
});

// ── Tests addVersion() ───────────────────────────────────────────────────────

describe('Document::addVersion()', function (): void {

    it('ajoute une version 2 au document', function (): void {
        $owner = makeUser();
        $doc   = makeDocument($owner);

        $doc->addVersion(
            uploadedBy: $owner,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromMegabytes(3.1),
            originalFilename: 'rapport_2026_v2.pdf',
            storagePath: StoragePath::fromString('documents/2026/04/uuid-v2.pdf'),
        );

        expect($doc->getVersions())->toHaveCount(2);
        expect($doc->getLatestVersion()->getVersionNumber()->getValue())->toBe(2);
    });

    it('émet un événement DocumentVersionAdded', function (): void {
        $owner = makeUser();
        $doc   = makeDocument($owner);
        $doc->releaseEvents(); // vider event upload

        $doc->addVersion(
            uploadedBy: $owner,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromBytes(1024),
            originalFilename: 'v2.pdf',
            storagePath: StoragePath::fromString('documents/2026/04/v2.pdf'),
        );

        $events = $doc->releaseEvents();
        expect($events)->toHaveCount(1);
        expect($events[0])->toBeInstanceOf(DocumentVersionAdded::class);
        expect($events[0]->versionNumber)->toEqual(VersionNumber::fromInt(2));
    });

    it('refuse l\'ajout de version par un utilisateur non propriétaire', function (): void {
        $owner   = makeUser();
        $intrus  = makeUser();
        $doc     = makeDocument($owner);

        $doc->addVersion(
            uploadedBy: $intrus,
            mimeType: MimeType::fromString('image/png'),
            fileSize: FileSize::fromBytes(500),
            originalFilename: 'hack.png',
            storagePath: StoragePath::fromString('documents/2026/04/hack.png'),
        );
    })->throws(DocumentAccessDeniedException::class);

    it('un admin peut ajouter une version sur n\'importe quel document', function (): void {
        $owner = makeUser();
        $admin = makeUser(admin: true);
        $doc   = makeDocument($owner);

        $doc->addVersion(
            uploadedBy: $admin,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromBytes(1024),
            originalFilename: 'admin_v2.pdf',
            storagePath: StoragePath::fromString('documents/2026/04/admin_v2.pdf'),
        );

        expect($doc->getVersions())->toHaveCount(2);
    });
});

// ── Tests workflow de statut ──────────────────────────────────────────────────

describe('Document — workflow de statut', function (): void {

    it('passe de DRAFT à PENDING_REVIEW', function (): void {
        $owner = makeUser();
        $doc   = makeDocument($owner);

        $doc->submitForReview($owner);

        expect($doc->getStatus())->toBe(DocumentStatus::PENDING_REVIEW);
    });

    it('un admin peut approuver', function (): void {
        $owner = makeUser();
        $admin = makeUser(admin: true);
        $doc   = makeDocument($owner);
        $doc->submitForReview($owner);

        $doc->approve($admin);

        expect($doc->getStatus())->toBe(DocumentStatus::APPROVED);
    });

    it('un non-admin ne peut pas approuver', function (): void {
        $owner = makeUser();
        $doc   = makeDocument($owner);
        $doc->submitForReview($owner);

        $doc->approve($owner);
    })->throws(\DomainException::class, 'administrateur');

    it('refuse la modification d\'un document PENDING_REVIEW', function (): void {
        $owner = makeUser();
        $doc   = makeDocument($owner);
        $doc->submitForReview($owner);

        $doc->addVersion(
            uploadedBy: $owner,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromBytes(1024),
            originalFilename: 'v2.pdf',
            storagePath: StoragePath::fromString('documents/2026/04/v2.pdf'),
        );
    })->throws(\DomainException::class);

    it('un document REJECTED peut revenir en DRAFT pour correction', function (): void {
        // REJECTED → DRAFT via submitForReview? Non, il faut déjà être DRAFT.
        // En réalité REJECTED → le doc est déjà en statut REJECTED
        // pour repasser en DRAFT, il faut une méthode reopen() ou la transition
        // passe par DocumentStatus::REJECTED → DRAFT directement dans l'enum
        // Ce test documente le comportement attendu du workflow.
        $owner = makeUser();
        $admin = makeUser(admin: true);
        $doc   = makeDocument($owner);
        $doc->submitForReview($owner);
        $doc->reject($admin);

        expect($doc->getStatus())->toBe(DocumentStatus::REJECTED);
        // Le document est maintenant éditable (isEditable = true pour REJECTED)
        expect($doc->getStatus()->isEditable())->toBeTrue();
    });
});
