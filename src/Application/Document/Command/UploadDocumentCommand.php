<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\User\ValueObject\UserId;

/**
 * Commande immuable : encapsule tout ce qui est nécessaire pour uploader un document.
 *
 * Le fileContent est un chemin vers un fichier temporaire (pas le contenu brut)
 * pour éviter de charger des centaines de Mo en mémoire.
 */
final readonly class UploadDocumentCommand
{
    public function __construct(
        public FolderId $folderId,
        public UserId $uploadedBy,
        public string $title,
        public string $originalFilename,
        public string $mimeType,
        public int $fileSizeBytes,
        public string $tempFilePath,
        public ?string $comment = null,
    ) {}
}
