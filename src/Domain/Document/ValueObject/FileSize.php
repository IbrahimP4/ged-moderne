<?php

declare(strict_types=1);

namespace App\Domain\Document\ValueObject;

use InvalidArgumentException;

/**
 * Taille d'un fichier en octets — immuable avec helpers de formatage.
 */
final readonly class FileSize
{
    private const MAX_UPLOAD_BYTES = 134_217_728; // 128 MB

    private function __construct(
        private int $bytes,
    ) {
        if ($bytes < 0) {
            throw new InvalidArgumentException(
                sprintf('FileSize ne peut pas être négative : %d octets.', $bytes),
            );
        }

        if ($bytes > self::MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException(
                sprintf(
                    'Fichier trop volumineux : %s. Maximum autorisé : %s.',
                    self::formatBytes($bytes),
                    self::formatBytes(self::MAX_UPLOAD_BYTES),
                ),
            );
        }
    }

    public static function fromBytes(int $bytes): self
    {
        return new self($bytes);
    }

    public static function fromMegabytes(float $megabytes): self
    {
        return new self((int) ($megabytes * 1_048_576));
    }

    public function getBytes(): int
    {
        return $this->bytes;
    }

    public function toKilobytes(): float
    {
        return round($this->bytes / 1_024, 2);
    }

    public function toMegabytes(): float
    {
        return round($this->bytes / 1_048_576, 2);
    }

    public function isEmpty(): bool
    {
        return $this->bytes === 0;
    }

    public function humanReadable(): string
    {
        return self::formatBytes($this->bytes);
    }

    public function equals(self $other): bool
    {
        return $this->bytes === $other->bytes;
    }

    public function __toString(): string
    {
        return $this->humanReadable();
    }

    private static function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_073_741_824 => round($bytes / 1_073_741_824, 2) . ' GB',
            $bytes >= 1_048_576    => round($bytes / 1_048_576, 2) . ' MB',
            $bytes >= 1_024        => round($bytes / 1_024, 2) . ' KB',
            default                => $bytes . ' B',
        };
    }
}
