<?php

declare(strict_types=1);

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\AppUserProvider;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Tests\IntegrationTestCase;

uses(IntegrationTestCase::class);

describe('JWT authentication', function (): void {

    beforeEach(function (): void {
        $this->userRepo = self::getContainer()->get(UserRepositoryInterface::class);
        $this->hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->userProvider = self::getContainer()->get(AppUserProvider::class);

        $this->admin = User::create('admin', 'admin@example.com', 'pending', isAdmin: true);
        $this->admin->changePassword(
            $this->hasher->hashPassword(new SecurityUser($this->admin), 'admin1234'),
        );

        $this->userRepo->save($this->admin);
    });

    it('charge un utilisateur par username', function (): void {
        $user = $this->userProvider->loadUserByIdentifier('admin');

        expect($user)->toBeInstanceOf(SecurityUser::class)
            ->and($user->getUserIdentifier())->toBe('admin')
            ->and($user->getRoles())->toContain('ROLE_ADMIN');
    });
});
