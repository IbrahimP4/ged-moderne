<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UserId
{
    private function __construct(
        private string $value,
    ) {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException(
                sprintf('UserId invalide : "%s" n\'est pas un UUID valide.', $value),
            );
        }
    }

    public static function generate(): self
    {
        return new self((string) Uuid::v7());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function fromLegacyInt(int $legacyId): self
    {
        $namespace = Uuid::fromString(Uuid::NAMESPACE_OID);

        return new self((string) Uuid::v5($namespace, 'user:' . $legacyId));
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
