<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Domain\User\Repository\UserRepositoryInterface;

final class DeleteUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function __invoke(DeleteUserCommand $command): void
    {
        $target = $this->userRepository->findById($command->targetUserId);
        if ($target === null) {
            throw new \DomainException('Utilisateur introuvable.');
        }

        $deletedBy = $this->userRepository->findById($command->deletedBy);
        if ($deletedBy === null) {
            throw new \DomainException('Opérateur introuvable.');
        }

        if ($target->getId()->getValue() === $deletedBy->getId()->getValue()) {
            throw new \DomainException('Vous ne pouvez pas supprimer votre propre compte.');
        }

        $this->userRepository->delete($target);
    }
}
