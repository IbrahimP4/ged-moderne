<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Domain\User\Repository\UserRepositoryInterface;

final class ListUsersHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /** @return list<UserDTO> */
    public function __invoke(): array
    {
        return array_map(
            static fn ($user) => UserDTO::fromEntity($user),
            $this->userRepository->findAll(),
        );
    }
}
