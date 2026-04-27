<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Base abstraite pour les custom types UUID de nos Value Objects.
 *
 * Chaque type concret surcharge getName() et fromString().
 * La logique de conversion DB ↔ PHP est mutualisée ici.
 */
abstract class AbstractUuidType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = 36;
        $column['fixed']  = true;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof \Stringable && !\is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Cannot convert %s to string.', get_debug_type($value)));
        }

        return (string) $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Expected string, got %s.', get_debug_type($value)));
        }

        return $this->fromString($value);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    abstract protected function fromString(string $value): mixed;
}
