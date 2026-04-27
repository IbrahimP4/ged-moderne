<?php

declare(strict_types=1);

use App\Application\Document\Command\UploadDocumentCommand;
use App\Application\Document\Command\UploadDocumentHandler;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\Port\EventBusInterface;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeTempFile(string $content = 'fake pdf content'): string
{
    $path = tempnam(sys_get_temp_dir(), 'ged_test_');
    file_put_contents($path, $content);

    return $path;
}

function makeCommand(
    FolderId $folderId,
    UserId $userId,
    string $tempPath,
): UploadDocumentCommand {
    return new UploadDocumentCommand(
        folderId: $folderId,
        uploadedBy: $userId,
        title: 'Contrat de maintenance 2026',
        originalFilename: 'contrat.pdf',
        mimeType: 'application/pdf',
        fileSizeBytes: 102400,
        tempFilePath: $tempPath,
        comment: 'Contrat annuel',
    );
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('UploadDocumentHandler', function (): void {

    beforeEach(function (): void {
        $this->documentRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $this->folderRepo   = Mockery::mock(FolderRepositoryInterface::class);
        $this->userRepo     = Mockery::mock(UserRepositoryInterface::class);
        $this->storage      = Mockery::mock(DocumentStorageInterface::class);
        $this->eventBus     = Mockery::mock(EventBusInterface::class);
        $this->eventBus->shouldReceive('dispatch')->zeroOrMoreTimes();
        $this->messageBus   = Mockery::mock(\Symfony\Component\Messenger\MessageBusInterface::class);
        $this->messageBus->shouldReceive('dispatch')->zeroOrMoreTimes()->andReturn(new \Symfony\Component\Messenger\Envelope(new stdClass()));

        $this->handler = new UploadDocumentHandler(
            documentRepository: $this->documentRepo,
            folderRepository: $this->folderRepo,
            userRepository: $this->userRepo,
            storage: $this->storage,
            eventBus: $this->eventBus,
            messageBus: $this->messageBus,
        );

        $this->owner = User::create('alice', 'alice@ged.test', '$2y$10$fakehash');
        $this->folder = Folder::createRoot('DRH', $this->owner);
        $this->tempFile = makeTempFile();
    });

    afterEach(function (): void {
        Mockery::close();
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    });

    it('retourne un DocumentId après un upload réussi', function (): void {
        $folderId = $this->folder->getId();
        $userId   = $this->owner->getId();

        $this->folderRepo
            ->shouldReceive('findById')
            ->once()
            ->with(Mockery::on(fn ($id) => $id->equals($folderId)))
            ->andReturn($this->folder);

        $this->userRepo
            ->shouldReceive('findById')
            ->once()
            ->andReturn($this->owner);

        $this->storage
            ->shouldReceive('store')
            ->once()
            ->andReturn(StoragePath::fromString('documents/2026/04/uuid.pdf'));

        $this->documentRepo
            ->shouldReceive('save')
            ->once();

        $command = makeCommand($folderId, $userId, $this->tempFile);
        $result  = ($this->handler)($command);

        expect($result)->toBeInstanceOf(DocumentId::class);
    });

    it('lève FolderNotFoundException si le dossier n\'existe pas', function (): void {
        $folderId = FolderId::generate();
        $userId   = $this->owner->getId();

        $this->folderRepo
            ->shouldReceive('findById')
            ->once()
            ->andReturn(null);

        $command = makeCommand($folderId, $userId, $this->tempFile);
        ($this->handler)($command);
    })->throws(FolderNotFoundException::class);

    it('lève une DomainException si l\'utilisateur n\'existe pas', function (): void {
        $folderId = $this->folder->getId();
        $userId   = UserId::generate();

        $this->folderRepo
            ->shouldReceive('findById')
            ->once()
            ->andReturn($this->folder);

        $this->userRepo
            ->shouldReceive('findById')
            ->once()
            ->andReturn(null);

        $command = makeCommand($folderId, $userId, $this->tempFile);
        ($this->handler)($command);
    })->throws(\DomainException::class, 'Utilisateur introuvable');

    it('lève InvalidArgumentException pour un MIME type non autorisé', function (): void {
        $folderId = $this->folder->getId();
        $userId   = $this->owner->getId();

        $this->folderRepo->shouldReceive('findById')->andReturn($this->folder);
        $this->userRepo->shouldReceive('findById')->andReturn($this->owner);

        $command = new UploadDocumentCommand(
            folderId: $folderId,
            uploadedBy: $userId,
            title: 'Script malveillant',
            originalFilename: 'hack.php',
            mimeType: 'application/x-php',
            fileSizeBytes: 1024,
            tempFilePath: $this->tempFile,
        );

        ($this->handler)($command);
    })->throws(\InvalidArgumentException::class, 'Type MIME non autorisé');

    it('ne sauvegarde pas le document si le stockage échoue', function (): void {
        $folderId = $this->folder->getId();
        $userId   = $this->owner->getId();

        $this->folderRepo->shouldReceive('findById')->andReturn($this->folder);
        $this->userRepo->shouldReceive('findById')->andReturn($this->owner);

        $this->storage
            ->shouldReceive('store')
            ->andThrow(new \RuntimeException('S3 unavailable'));

        // save() ne doit JAMAIS être appelé si le stockage échoue
        $this->documentRepo->shouldNotReceive('save');

        $command = makeCommand($folderId, $userId, $this->tempFile);
        ($this->handler)($command);
    })->throws(\RuntimeException::class, 'S3 unavailable');
});
