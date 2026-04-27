<?php

declare(strict_types=1);

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Migration\LegacySeedDmsReader;
use App\UI\Console\Migration\MigrateUsersCommand;
use Symfony\Component\Console\Tester\CommandTester;

describe('MigrateUsersCommand', function (): void {

    beforeEach(function (): void {
        $this->reader   = Mockery::mock(LegacySeedDmsReader::class);
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->command  = new MigrateUsersCommand($this->reader, $this->userRepo);
        $this->tester   = new CommandTester($this->command);
    });

    afterEach(fn () => Mockery::close());

    it('migre des utilisateurs inexistants', function (): void {
        $this->reader->shouldReceive('countUsers')->andReturn(2);
        $this->reader->shouldReceive('fetchUsers')->andReturn([
            ['id' => 1, 'login' => 'alice', 'fullName' => 'Alice Test', 'email' => 'alice@test.com', 'pwd' => '$2y$10$hash', 'role' => 0, 'disabled' => 0],
            ['id' => 2, 'login' => 'admin', 'fullName' => 'Admin User', 'email' => 'admin@test.com', 'pwd' => '$2y$10$hash', 'role' => 1, 'disabled' => 0],
        ]);

        $this->userRepo->shouldReceive('findByEmail')->andReturn(null);
        $this->userRepo->shouldReceive('findByUsername')->andReturn(null);
        $this->userRepo->shouldReceive('save')->twice();

        $exitCode = $this->tester->execute([]);

        expect($exitCode)->toBe(0);
        expect($this->tester->getDisplay())->toContain('Migrés : 2');
    });

    it('ignore un utilisateur déjà présent par email', function (): void {
        $existingUser = User::create('alice', 'alice@test.com', '$2y$10$hash');

        $this->reader->shouldReceive('countUsers')->andReturn(1);
        $this->reader->shouldReceive('fetchUsers')->andReturn([
            ['id' => 1, 'login' => 'alice', 'fullName' => 'Alice', 'email' => 'alice@test.com', 'pwd' => '$2y$10$hash', 'role' => 0, 'disabled' => 0],
        ]);

        $this->userRepo->shouldReceive('findByEmail')->andReturn($existingUser);
        $this->userRepo->shouldNotReceive('save');

        $exitCode = $this->tester->execute([]);

        expect($exitCode)->toBe(0);
        expect($this->tester->getDisplay())->toContain('Ignorés (déjà présents) : 1');
    });

    it('ne sauvegarde rien en mode dry-run', function (): void {
        $this->reader->shouldReceive('countUsers')->andReturn(1);
        $this->reader->shouldReceive('fetchUsers')->andReturn([
            ['id' => 1, 'login' => 'alice', 'fullName' => 'Alice', 'email' => 'alice@test.com', 'pwd' => '$2y$10$hash', 'role' => 0, 'disabled' => 0],
        ]);

        $this->userRepo->shouldReceive('findByEmail')->andReturn(null);
        $this->userRepo->shouldReceive('findByUsername')->andReturn(null);
        $this->userRepo->shouldNotReceive('save');

        $exitCode = $this->tester->execute(['--dry-run' => true]);

        expect($exitCode)->toBe(0);
        expect($this->tester->getDisplay())->toContain('dry-run');
        expect($this->tester->getDisplay())->toContain('Migrés : 1');
    });

    it('retourne FAILURE si la base legacy est inaccessible', function (): void {
        $this->reader->shouldReceive('countUsers')->andThrow(new \Exception('Connection refused'));

        $exitCode = $this->tester->execute([]);

        expect($exitCode)->toBe(1);
        expect($this->tester->getDisplay())->toContain('Connection refused');
    });

    it('assigne le rôle admin pour role=1', function (): void {
        $this->reader->shouldReceive('countUsers')->andReturn(1);
        $this->reader->shouldReceive('fetchUsers')->andReturn([
            ['id' => 1, 'login' => 'admin', 'fullName' => 'Admin', 'email' => 'admin@test.com', 'pwd' => '$2y$10$hash', 'role' => 1, 'disabled' => 0],
        ]);

        $this->userRepo->shouldReceive('findByEmail')->andReturn(null);
        $this->userRepo->shouldReceive('findByUsername')->andReturn(null);
        $this->userRepo->shouldReceive('save')->once()->with(Mockery::on(
            fn (User $u) => $u->isAdmin() === true,
        ));

        $exitCode = $this->tester->execute([]);

        expect($exitCode)->toBe(0);
    });
});
