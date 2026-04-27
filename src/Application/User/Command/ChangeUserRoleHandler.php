<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Domain\User\Repository\UserRepositoryInterface;

final class ChangeUserRoleHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function __invoke(ChangeUserRoleCommand $command): void
    {
        $target = $this->userRepository->findById($command->targetUserId);
        if ($target === null) {
            throw new \DomainException('Utilisateur introuvable.');
        }

        $changedBy = $this->userRepository->findById($command->changedBy);
        if ($changedBy === null) {
            throw new \DomainException('Opérateur introuvable.');
        }

        $target->changeRole($command->makeAdmin, $changedBy);

        $this->userRepository->save($target);
    }
}
