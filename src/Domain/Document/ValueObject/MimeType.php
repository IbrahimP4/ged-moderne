<?php

declare(strict_types=1);

namespace App\Domain\Document\ValueObject;

use InvalidArgumentException;

/**
 * Type MIME d'un fichier — immuable et validé à la construction.
 *
 * Dans le legacy, le MIME type était une simple string non validée,
 * permettant des uploads avec des types arbitraires.
 */
final readonly class MimeType
{
    /**
     * Types autorisés pour une GED d'entreprise.
     * Extendable via configuration (Symfony Parameter).
     */
    private const ALLOWED_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'application/x-zip-compressed',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/svg+xml',
        'image/webp',
        'image/tiff',
    ];

    private function __construct(
        private string $value,
    ) {
        $this->validate($value);
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    public static function fromFilename(string $filename): self
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $map = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt'  => 'text/plain',
            'csv'  => 'text/csv',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'tif'  => 'image/tiff',
            'tiff' => 'image/tiff',
            'zip'  => 'application/zip',
        ];

        if (! isset($map[$extension])) {
            throw new InvalidArgumentException(
                sprintf('Extension non supportée : ".%s"', $extension),
            );
        }

        return new self($map[$extension]);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->value, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->value === 'application/pdf';
    }

    public function isOfficeDocument(): bool
    {
        return str_contains($this->value, 'officedocument')
            || in_array($this->value, ['application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint'], true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validate(string $value): void
    {
        if (preg_match('#^[a-z]+/[a-z0-9\-\+\.]+$#', $value) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Format MIME invalide : "%s"', $value),
            );
        }

        if (! in_array($value, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf('Type MIME non autorisé : "%s". Types autorisés : %s', $value, implode(', ', self::ALLOWED_TYPES)),
            );
        }
    }
}
