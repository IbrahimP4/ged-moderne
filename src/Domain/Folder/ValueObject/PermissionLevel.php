<?php

declare(strict_types=1);

namespace App\Domain\Folder\ValueObject;

enum PermissionLevel: string
{
    case READ  = 'read';
    case WRITE = 'write';

    public function canWrite(): bool
    {
        return $this === self::WRITE;
    }

    public function label(): string
    {
        return match ($this) {
            self::READ  => 'Lecture',
            self::WRITE => 'Écriture',
        };
    }
}
