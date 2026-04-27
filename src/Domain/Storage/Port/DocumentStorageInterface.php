<?php

declare(strict_types=1);

namespace App\Domain\Storage\Port;

use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Storage\ValueObject\StoragePath;

/**
 * Port (interface) pour le stockage de fichiers.
 *
 * L'adaptateur concret (Flysystem, S3, etc.) est injecté en Infrastructure.
 * Le Domain ne connaît jamais Flysystem directement.
 */
interface DocumentStorageInterface
{
    /**
     * Stocke le contenu d'un fichier et retourne son chemin de stockage.
     *
     * @param resource|string $contents Flux ou contenu brut du fichier
     */
    public function store(mixed $contents, MimeType $mimeType, string $originalFilename): StoragePath;

    /**
     * Retourne un stream du fichier stocké.
     *
     * @return resource
     */
    public function read(StoragePath $path): mixed;

    /**
     * Supprime le fichier du stockage.
     */
    public function delete(StoragePath $path): void;

    /**
     * Vérifie si un fichier existe dans le stockage.
     */
    public function exists(StoragePath $path): bool;
}
