<?php

declare(strict_types=1);

namespace App\Domain\Document\ValueObject;

use InvalidArgumentException;

/**
 * Numéro de version d'un document — immuable.
 *
 * Dans le legacy SeedDMS, les versions étaient des entiers auto-incrémentés
 * non typés. Ce Value Object garantit l'invariant : version >= 1.
 */
final readonly class VersionNumber
{
    private function __construct(
        private int $value,
    ) {
        if ($value < 1) {
            throw new InvalidArgumentException(
                sprintf('VersionNumber doit être >= 1, %d donné.', $value),
            );
        }
    }

    public static function first(): self
    {
        return new self(1);
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function isFirst(): bool
    {
        return $this->value === 1;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
