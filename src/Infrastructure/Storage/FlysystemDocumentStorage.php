<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Uid\Uuid;

/**
 * Adaptateur Flysystem pour le stockage de documents.
 *
 * Implémente le port Domain DocumentStorageInterface.
 * Flysystem abstrait le backend réel (local, S3, Azure, etc.)
 * — le Domain n'en sait rien.
 *
 * OWASP : le nom de fichier d'origine n'est JAMAIS utilisé comme chemin de stockage.
 * On génère un UUID + extension validée pour éviter les injections de chemin.
 */
final class FlysystemDocumentStorage implements DocumentStorageInterface
{
    public function __construct(
        private readonly FilesystemOperator $documentStorage,
    ) {}

    public function store(mixed $contents, MimeType $mimeType, string $originalFilename): StoragePath
    {
        $extension   = $this->extractSafeExtension($originalFilename, $mimeType);
        $storagePath = StoragePath::forDocument((string) Uuid::v7(), $extension);

        try {
            if (is_resource($contents)) {
                $this->documentStorage->writeStream($storagePath->getValue(), $contents);
            } else {
                $this->documentStorage->write($storagePath->getValue(), (string) $contents);
            }
        } catch (FilesystemException $e) {
            throw new \RuntimeException(
                sprintf('Échec du stockage du fichier : %s', $e->getMessage()),
                previous: $e,
            );
        }

        return $storagePath;
    }

    public function read(StoragePath $path): mixed
    {
        try {
            return $this->documentStorage->readStream($path->getValue());
        } catch (FilesystemException $e) {
            throw new \RuntimeException(
                sprintf('Fichier introuvable en stockage : "%s"', $path->getValue()),
                previous: $e,
            );
        }
    }

    public function delete(StoragePath $path): void
    {
        try {
            $this->documentStorage->delete($path->getValue());
        } catch (FilesystemException $e) {
            // Idempotent : si le fichier n'existe déjà plus, ce n'est pas une erreur critique
            throw new \RuntimeException(
                sprintf('Échec de la suppression du fichier : "%s"', $path->getValue()),
                previous: $e,
            );
        }
    }

    public function exists(StoragePath $path): bool
    {
        try {
            return $this->documentStorage->fileExists($path->getValue());
        } catch (FilesystemException) {
            return false;
        }
    }

    /**
     * Extrait l'extension depuis le nom original et la valide contre le MimeType.
     * Si l'extension ne correspond pas, on utilise l'extension canonique du MimeType.
     *
     * Défense OWASP : empêche l'upload d'un fichier .php renommé en .pdf.
     */
    private function extractSafeExtension(string $originalFilename, MimeType $mimeType): string
    {
        $originalExt = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        $canonicalExtensions = [
            'application/pdf'  => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/msword' => 'doc',
            'application/vnd.ms-excel' => 'xls',
            'text/plain'  => 'txt',
            'text/csv'    => 'csv',
            'image/jpeg'  => 'jpg',
            'image/png'   => 'png',
            'image/gif'   => 'gif',
            'image/svg+xml' => 'svg',
            'image/webp'  => 'webp',
            'image/tiff'  => 'tiff',
            'application/zip' => 'zip',
        ];

        $canonical = $canonicalExtensions[$mimeType->getValue()] ?? 'bin';

        // Si l'extension déclarée est cohérente avec le MIME type, on la garde
        // sinon on force l'extension canonique
        $validExtensionsForMime = match ($mimeType->getValue()) {
            'image/jpeg'  => ['jpg', 'jpeg'],
            'image/tiff'  => ['tif', 'tiff'],
            'application/zip' => ['zip'],
            default       => [$canonical],
        };

        return in_array($originalExt, $validExtensionsForMime, true) ? $originalExt : $canonical;
    }
}
