<?php

declare(strict_types=1);

namespace App\Application\Document\Query;

final readonly class DownloadDocumentDTO
{
    /**
     * @param resource $stream
     */
    public function __construct(
        public mixed $stream,
        public string $mimeType,
        public string $originalFilename,
        public int $versionNumber,
    ) {}
}
