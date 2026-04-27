<?php

declare(strict_types=1);

use App\Application\User\Command\CreateUserCommand;
use App\Application\User\Command\CreateUserHandler;
use App\Application\User\Command\ChangeUserRoleCommand;
use App\Application\User\Command\ChangeUserRoleHandler;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeUserEntity(string $name, bool $admin = false): User
{
    return User::create(
        username: $name,
        email: $name . '@ged.test',
        hashedPassword: '$2y$10$fakehash',
        isAdmin: $admin,
    );
}

// ── Tests CreateUserHandler ───────────────────────────────────────────────────

describe('CreateUserHandler', function (): void {

    beforeEach(function (): void {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->hasher   = Mockery::mock(UserPasswordHasherInterface::class);
        $this->hasher->shouldReceive('hashPassword')->andReturn('$2y$10$hashedpassword');

        $this->handler = new CreateUserHandler($this->userRepo, $this->hasher);
        $this->admin   = makeUserEntity('superadmin', admin: true);
    });

    afterEach(fn () => Mockery::close());

    it('crée un utilisateur et retourne son UserId', function (): void {
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->admin);
        $this->userRepo->shouldReceive('findByUsername')->once()->andReturn(null);
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->userRepo->shouldReceive('save')->once();

        $result = ($this->handler)(new CreateUserCommand(
            username:      'nouvel.user',
            email:         'nouvel.user@ged.test',
            plainPassword: 'motdepasse123',
            isAdmin:       false,
            createdBy:     $this->admin->getId(),
        ));

        expect($result)->toBeInstanceOf(UserId::class);
    });

    it('lève DomainException si le créateur n\'est pas admin', function (): void {
        $nonAdmin = makeUserEntity('simple.user');
        $this->userRepo->shouldReceive('findById')->once()->andReturn($nonAdmin);

        ($this->handler)(new CreateUserCommand(
            username:      'test',
            email:         'test@ged.test',
            plainPassword: 'pass',
            isAdmin:       false,
            createdBy:     $nonAdmin->getId(),
        ));
    })->throws(\DomainException::class, 'administrateur');

    it('lève DomainException si le créateur est introuvable', function (): void {
        $this->userRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new CreateUserCommand(
            username:      'test',
            email:         'test@ged.test',
            plainPassword: 'pass',
            isAdmin:       false,
            createdBy:     UserId::generate(),
        ));
    })->throws(\DomainException::class, 'administrateur');

    it('lève DomainException si le nom d\'utilisateur est déjà pris', function (): void {
        $existing = makeUserEntity('alice');
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->admin);
        $this->userRepo->shouldReceive('findByUsername')->once()->andReturn($existing);

        ($this->handler)(new CreateUserCommand(
            username:      'alice',
            email:         'newalice@ged.test',
            plainPassword: 'pass',
            isAdmin:       false,
            createdBy:     $this->admin->getId(),
        ));
    })->throws(\DomainException::class, 'utilisateur');

    it('lève DomainException si l\'email est déjà utilisé', function (): void {
        $existing = makeUserEntity('bob');
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->admin);
        $this->userRepo->shouldReceive('findByUsername')->once()->andReturn(null);
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($existing);

        ($this->handler)(new CreateUserCommand(
            username:      'newbob',
            email:         'bob@ged.test',
            plainPassword: 'pass',
            isAdmin:       false,
            createdBy:     $this->admin->getId(),
        ));
    })->throws(\DomainException::class, 'email');

    it('peut créer un compte administrateur', function (): void {
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->admin);
        $this->userRepo->shouldReceive('findByUsername')->once()->andReturn(null);
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->userRepo->shouldReceive('save')->once();

        $result = ($this->handler)(new CreateUserCommand(
            username:      'second.admin',
            email:         'second.admin@ged.test',
            plainPassword: 'securepass',
            isAdmin:       true,
            createdBy:     $this->admin->getId(),
        ));

        expect($result)->toBeInstanceOf(UserId::class);
    });
});

// ── Tests ChangeUserRoleHandler ───────────────────────────────────────────────

describe('ChangeUserRoleHandler', function (): void {

    beforeEach(function (): void {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->handler  = new ChangeUserRoleHandler($this->userRepo);
        $this->admin    = makeUserEntity('admin_op', admin: true);
        $this->target   = makeUserEntity('frank');
    });

    afterEach(fn () => Mockery::close());

    it('promeut un utilisateur en administrateur', function (): void {
        $this->userRepo->shouldReceive('findById')
            ->twice()
            ->andReturn($this->target, $this->admin);
        $this->userRepo->shouldReceive('save')->once();

        ($this->handler)(new ChangeUserRoleCommand(
            targetUserId: $this->target->getId(),
            changedBy:    $this->admin->getId(),
            makeAdmin:    true,
        ));

        expect($this->target->isAdmin())->toBeTrue();
    });

    it('rétrograde un admin en utilisateur simple', function (): void {
        $adminTarget = makeUserEntity('former_admin', admin: true);

        $this->userRepo->shouldReceive('findById')
            ->twice()
            ->andReturn($adminTarget, $this->admin);
        $this->userRepo->shouldReceive('save')->once();

        ($this->handler)(new ChangeUserRoleCommand(
            targetUserId: $adminTarget->getId(),
            changedBy:    $this->admin->getId(),
            makeAdmin:    false,
        ));

        expect($adminTarget->isAdmin())->toBeFalse();
    });

    it('lève DomainException si la cible est introuvable', function (): void {
        $this->userRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new ChangeUserRoleCommand(
            targetUserId: UserId::generate(),
            changedBy:    $this->admin->getId(),
            makeAdmin:    true,
        ));
    })->throws(\DomainException::class, 'introuvable');

    it('lève DomainException si l\'opérateur est introuvable', function (): void {
        $this->userRepo->shouldReceive('findById')
            ->twice()
            ->andReturn($this->target, null);

        ($this->handler)(new ChangeUserRoleCommand(
            targetUserId: $this->target->getId(),
            changedBy:    UserId::generate(),
            makeAdmin:    true,
        ));
    })->throws(\DomainException::class, 'Opérateur');

    it('lève DomainException si un admin tente de modifier son propre rôle', function (): void {
        // L'admin essaie de se modifier lui-même → DomainException dans User::changeRole()
        $this->userRepo->shouldReceive('findById')
            ->twice()
            ->andReturn($this->admin, $this->admin);

        ($this->handler)(new ChangeUserRoleCommand(
            targetUserId: $this->admin->getId(),
            changedBy:    $this->admin->getId(),
            makeAdmin:    false,
        ));
    })->throws(\DomainException::class);
});
