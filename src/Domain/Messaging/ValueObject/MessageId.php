<?php

declare(strict_types=1);

namespace App\Domain\Messaging\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class MessageId
{
    private function __construct(
        private string $value,
    ) {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException(
                sprintf('MessageId invalide : "%s" n\'est pas un UUID valide.', $value),
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

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
