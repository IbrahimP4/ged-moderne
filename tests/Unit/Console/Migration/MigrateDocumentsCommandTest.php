<?php

declare(strict_types=1);

use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Port\EventBusInterface;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Migration\LegacySeedDmsReader;
use App\UI\Console\Migration\MigrateDocumentsCommand;
use Symfony\Component\Console\Tester\CommandTester;

describe('MigrateDocumentsCommand', function (): void {

    beforeEach(function (): void {
        $this->reader       = Mockery::mock(LegacySeedDmsReader::class);
        $this->documentRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $this->folderRepo   = Mockery::mock(FolderRepositoryInterface::class);
        $this->userRepo     = Mockery::mock(UserRepositoryInterface::class);
        $this->storage      = Mockery::mock(DocumentStorageInterface::class);
        $this->eventBus     = Mockery::mock(EventBusInterface::class);
        $this->eventBus->shouldReceive('dispatch')->zeroOrMoreTimes();

        $this->command = new MigrateDocumentsCommand(
            reader: $this->reader,
            documentRepository: $this->documentRepo,
            folderRepository: $this->folderRepo,
            userRepository: $this->userRepo,
            storage: $this->storage,
            eventBus: $this->eventBus,
            legacyStorageDir: '/tmp/legacy_storage_test',
        );
        $this->tester = new CommandTester($this->command);

        $this->owner  = User::create('admin', 'admin@test.com', '$2y$10$hash', isAdmin: true);
        $this->folder = Folder::createRoot('Racine', $this->owner);
    });

    afterEach(fn () => Mockery::close());

    it('retourne FAILURE si la base legacy est inaccessible', function (): void {
        $this->reader->shouldReceive('fetchDocuments')->andThrow(new \Exception('Connection refused'));

        $exitCode = $this->tester->execute([]);

        expect($exitCode)->toBe(1);
        expect($this->tester->getDisplay())->toContain('Connection refused');
    });

    it('ignore les documents sans version', function (): void {
        $this->reader->shouldReceive('fetchDocuments')->andReturn([
            ['id' => 1, 'name' => 'Vide', 'folder' => 1, 'owner' => 1, 'comment' => null, 'title' => null],
        ]);
        $this->reader->shouldReceive('fetchVersionsForDocument')->with(1)->andReturn([]);

        $this->folderRepo->shouldReceive('findRoot')->andReturn($this->folder);
        $this->userRepo->shouldReceive('findByEmail')->andReturn($this->owner);
        $this->documentRepo->shouldNotReceive('save');

        $exitCode = $this->tester->execute(['--fallback-email' => 'admin@test.com']);

        expect($exitCode)->toBe(0);
        expect($this->tester->getDisplay())->toContain('Ignorés : 1');
    });

    it('migre un document avec --skip-files', function (): void {
        $this->reader->shouldReceive('fetchDocuments')->andReturn([
            ['id' => 1, 'name' => 'Rapport', 'folder' => 1, 'owner' => 1, 'comment' => 'Un rapport', 'title' => 'Rapport annuel'],
        ]);
        $this->reader->shouldReceive('fetchVersionsForDocument')->with(1)->andReturn([
            ['id' => 10, 'document' => 1, 'version' => 1, 'comment' => 'v1', 'origFileName' => 'rapport.pdf', 'fileType' => 'pdf', 'mimeType' => 'application/pdf', 'fileSize' => 102400, 'createdAt' => time(), 'dir' => '1/1'],
        ]);

        $this->folderRepo->shouldReceive('findRoot')->andReturn($this->folder);
        $this->userRepo->shouldReceive('findByEmail')->andReturn($this->owner);
        $this->storage->shouldNotReceive('store');
        $this->documentRepo->shouldReceive('save')->once();

        $exitCode = $this->tester->execute([
            '--fallback-email' => 'admin@test.com',
            '--skip-files'     => true,
        ]);

        expect($exitCode)->toBe(0);
        expect($this->tester->getDisplay())->toContain('Migrés : 1');
    });

    it('ne sauvegarde rien en mode dry-run', function (): void {
        $this->reader->shouldReceive('fetchDocuments')->andReturn([
            ['id' => 1, 'name' => 'Rapport', 'folder' => 1, 'owner' => 1, 'comment' => null, 'title' => 'Rapport'],
        ]);
        $this->reader->shouldReceive('fetchVersionsForDocument')->with(1)->andReturn([
            ['id' => 10, 'document' => 1, 'version' => 1, 'comment' => null, 'origFileName' => 'rapport.pdf', 'fileType' => 'pdf', 'mimeType' => 'application/pdf', 'fileSize' => 1024, 'createdAt' => time(), 'dir' => '1/1'],
        ]);

        $this->folderRepo->shouldReceive('findRoot')->andReturn($this->folder);
        $this->userRepo->shouldReceive('findByEmail')->andReturn($this->owner);
        $this->storage->shouldNotReceive('store');
        $this->documentRepo->shouldNotReceive('save');

        $exitCode = $this->tester->execute([
            '--dry-run'        => true,
            '--fallback-email' => 'admin@test.com',
        ]);

        expect($exitCode)->toBe(0);
        expect($this->tester->getDisplay())->toContain('dry-run');
        expect($this->tester->getDisplay())->toContain('Migrés : 1');
    });

    it('enregistre une erreur si aucun dossier racine n\'existe', function (): void {
        $this->reader->shouldReceive('fetchDocuments')->andReturn([
            ['id' => 1, 'name' => 'Doc', 'folder' => 1, 'owner' => 1, 'comment' => null, 'title' => null],
        ]);
        $this->reader->shouldReceive('fetchVersionsForDocument')->with(1)->andReturn([
            ['id' => 10, 'document' => 1, 'version' => 1, 'comment' => null, 'origFileName' => 'doc.pdf', 'fileType' => 'pdf', 'mimeType' => 'application/pdf', 'fileSize' => 1024, 'createdAt' => time(), 'dir' => '1/1'],
        ]);

        $this->folderRepo->shouldReceive('findRoot')->andReturn(null);
        $this->userRepo->shouldReceive('findByEmail')->andReturn($this->owner);
        $this->documentRepo->shouldNotReceive('save');

        $exitCode = $this->tester->execute(['--fallback-email' => 'admin@test.com']);

        expect($exitCode)->toBe(1);
        expect($this->tester->getDisplay())->toContain('Erreurs : 1');
    });
});
