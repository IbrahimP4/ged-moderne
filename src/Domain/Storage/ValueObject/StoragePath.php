<?php

declare(strict_types=1);

namespace App\Domain\Storage\ValueObject;

use InvalidArgumentException;

/**
 * Chemin de stockage d'un fichier dans Flysystem.
 *
 * Format : {context}/{year}/{month}/{uuid}.{ext}
 * Exemple : documents/2026/04/018e4c6a-xxxx.pdf
 *
 * Ce Value Object garantit que le chemin ne contient pas de traversal (../).
 */
final readonly class StoragePath
{
    private function __construct(
        private string $value,
    ) {
        $this->validate($value);
    }

    public static function fromString(string $path): self
    {
        return new self($path);
    }

    public static function forDocument(string $uuid, string $extension): self
    {
        $year  = date('Y');
        $month = date('m');
        $ext   = ltrim(strtolower($extension), '.');

        return new self(sprintf('documents/%s/%s/%s.%s', $year, $month, $uuid, $ext));
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDirectory(): string
    {
        return dirname($this->value);
    }

    public function getFilename(): string
    {
        return basename($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validate(string $path): void
    {
        if ($path === '') {
            throw new InvalidArgumentException('StoragePath ne peut pas être vide.');
        }

        // Prévention directory traversal
        if (str_contains($path, '..')) {
            throw new InvalidArgumentException(
                sprintf('StoragePath invalide (directory traversal détecté) : "%s"', $path),
            );
        }

        // Pas de slash en début (chemin relatif Flysystem)
        if (str_starts_with($path, '/')) {
            throw new InvalidArgumentException(
                sprintf('StoragePath doit être un chemin relatif (sans "/" initial) : "%s"', $path),
            );
        }
    }
}
