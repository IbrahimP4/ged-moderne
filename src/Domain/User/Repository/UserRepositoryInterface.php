<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\UserId;

interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;

    /** @return list<User> */
    public function findAll(): array;

    public function findByEmail(string $email): ?User;

    public function findByUsername(string $username): ?User;

    public function save(User $user): void;

    public function delete(User $user): void;

    public function count(): int;
}
