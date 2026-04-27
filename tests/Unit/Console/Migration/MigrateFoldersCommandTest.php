<?php

declare(strict_types=1);

use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Migration\LegacySeedDmsReader;
use App\UI\Console\Migration\MigrateFoldersCommand;
use Symfony\Component\Console\Tester\CommandTester;

describe('MigrateFoldersCommand', function (): void {

    beforeEach(function (): void {
        $this->reader     = Mockery::mock(LegacySeedDmsReader::class);
        $this->folderRepo = Mockery::mock(FolderRepositoryInterface::class);
        $this->userRepo   = Mockery::mock(UserRepositoryInterface::class);
        $this->command    = new MigrateFoldersCommand($this->reader, $this->folderRepo, $this->userRepo);
        $this->tester     = new CommandTester($this->command);

        $this->owner = User::create('admin', 'admin@test.com', '$2y$10$hash', isAdmin: true);
    });

    afterEach(fn () => Mockery::close());

    it('migre une arborescence simple (racine + enfant)', function (): void {
        $this->reader->shouldReceive('fetchFolders')->andReturn([
            ['id' => 1, 'name' => 'Racine', 'parent' => 0, 'owner' => 1, 'comment' => null],
            ['id' => 2, 'name' => 'RH', 'parent' => 1, 'owner' => 1, 'comment' => 'Ressources humaines'],
        ]);

        $this->userRepo->shouldReceive('findByEmail')->andReturn($this->owner);
        $this->folderRepo->shouldReceive('save')->twice();

        $exitCode = $this->tester->execute(['--fallback-email' => 'admin@test.com']);

        expect($exitCode)->toBe(0);
        expect($this->tester->getDisplay())->toContain('Migrés : 2');
    });

    it('ne sauvegarde rien en mode dry-run', function (): void {
        $this->reader->shouldReceive('fetchFolders')->andReturn([
            ['id' => 1, 'name' => 'Racine', 'parent' => 0, 'owner' => 1, 'comment' => null],
        ]);

        $this->userRepo->shouldReceive('findByEmail')->andReturn($this->owner);
        $this->folderRepo->shouldNotReceive('save');

        $exitCode = $this->tester->execute(['--dry-run' => true, '--fallback-email' => 'admin@test.com']);

        expect($exitCode)->toBe(0);
        expect($this->tester->getDisplay())->toContain('Migrés : 1');
    });

    it('enregistre une erreur si le propriétaire est introuvable sans fallback', function (): void {
        $this->reader->shouldReceive('fetchFolders')->andReturn([
            ['id' => 1, 'name' => 'Racine', 'parent' => 0, 'owner' => 99, 'comment' => null],
        ]);

        $this->userRepo->shouldReceive('findByEmail')->andReturn(null);
        $this->folderRepo->shouldNotReceive('save');

        $exitCode = $this->tester->execute([]);

        expect($exitCode)->toBe(1);
        expect($this->tester->getDisplay())->toContain('Erreurs : 1');
    });

    it('retourne FAILURE si la base legacy est inaccessible', function (): void {
        $this->reader->shouldReceive('fetchFolders')->andThrow(new \Exception('Connection refused'));

        $exitCode = $this->tester->execute([]);

        expect($exitCode)->toBe(1);
    });
});
