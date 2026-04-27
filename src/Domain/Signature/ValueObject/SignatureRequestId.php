<?php

declare(strict_types=1);

namespace App\Domain\Signature\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class SignatureRequestId
{
    private function __construct(
        private string $value,
    ) {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException(
                sprintf('SignatureRequestId invalide : "%s" n\'est pas un UUID valide.', $value),
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
