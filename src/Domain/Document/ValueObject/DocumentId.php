<?php

declare(strict_types=1);

namespace App\Domain\Document\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Identifiant immuable d'un Document.
 *
 * Remplace l'usage de int brut dans le legacy (intval($_GET["docid"])).
 * Empêche toute confusion avec FolderId, UserId, etc.
 */
final readonly class DocumentId
{
    private function __construct(
        private string $value,
    ) {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException(
                sprintf('DocumentId invalide : "%s" n\'est pas un UUID valide.', $value),
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

    /**
     * Migration depuis le legacy : convertit un int SeedDMS en UUID v5
     * (déterministe : même int → même UUID, utile pour la migration de données).
     */
    public static function fromLegacyInt(int $legacyId): self
    {
        $namespace = Uuid::fromString(Uuid::NAMESPACE_OID);

        return new self((string) Uuid::v5($namespace, 'document:' . $legacyId));
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
