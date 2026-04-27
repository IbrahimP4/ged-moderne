<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\ValueObject\DocumentId;

/**
 * Message Messenger déclenché après un upload réussi.
 *
 * On passe le storagePath (chemin permanent dans Flysystem) et NON le
 * fichier temporaire PHP — qui est supprimé dès la fin de la requête HTTP.
 *
 * Le handler lit le fichier via DocumentStorageInterface::read() afin d'être
 * compatible avec tous les backends de stockage (local, S3, Azure...).
 */
final readonly class IndexDocumentContentCommand
{
    public function __construct(
        public readonly DocumentId $documentId,
        public readonly string     $storagePath,  // ex: "documents/abc-uuid.pdf"
        public readonly string     $mimeType,
    ) {}
}
