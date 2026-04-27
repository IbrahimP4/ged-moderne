<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function __invoke(CreateUserCommand $command): UserId
    {
        $creator = $this->userRepository->findById($command->createdBy);
        if ($creator === null || !$creator->isAdmin()) {
            throw new \DomainException('Seul un administrateur peut créer un compte.');
        }

        if ($this->userRepository->findByUsername($command->username) !== null) {
            throw new \DomainException('Ce nom d\'utilisateur est déjà utilisé.');
        }

        if ($this->userRepository->findByEmail($command->email) !== null) {
            throw new \DomainException('Cette adresse email est déjà utilisée.');
        }

        $user = User::create(
            username: $command->username,
            email: $command->email,
            hashedPassword: 'pending',
            isAdmin: $command->isAdmin,
        );

        $user->changePassword(
            $this->passwordHasher->hashPassword(new SecurityUser($user), $command->plainPassword),
        );

        $this->userRepository->save($user);

        return $user->getId();
    }
}
