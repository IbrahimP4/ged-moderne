<?php

declare(strict_types=1);

namespace App\Domain\Signature\ValueObject;

enum SignatureRequestStatus: string
{
    case PENDING  = 'pending';
    case SIGNED   = 'signed';
    case DECLINED = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::PENDING  => 'En attente',
            self::SIGNED   => 'Signé',
            self::DECLINED => 'Refusé',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isResolved(): bool
    {
        return $this !== self::PENDING;
    }
}
