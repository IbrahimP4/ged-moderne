<?php

declare(strict_types=1);

namespace App\Application\Document\Query;

use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Storage\Port\DocumentStorageInterface;

final class DownloadDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentStorageInterface $storage,
    ) {}

    public function __invoke(DownloadDocumentQuery $query): DownloadDocumentDTO
    {
        $document = $this->documentRepository->findById($query->documentId);
        if ($document === null) {
            throw new DocumentNotFoundException($query->documentId);
        }

        if ($query->versionNumber !== null) {
            $version = null;
            foreach ($document->getVersions() as $v) {
                if ($v->getVersionNumber()->getValue() === $query->versionNumber) {
                    $version = $v;
                    break;
                }
            }
            if ($version === null) {
                throw new \DomainException(sprintf(
                    'Version %d introuvable pour ce document.',
                    $query->versionNumber,
                ));
            }
        } else {
            $version = $document->getLatestVersion();
            if ($version === null) {
                throw new \DomainException('Ce document ne possède aucune version.');
            }
        }

        $stream = $this->storage->read($version->getStoragePath());

        return new DownloadDocumentDTO(
            stream: $stream,
            mimeType: $version->getMimeType()->getValue(),
            originalFilename: $version->getOriginalFilename(),
            versionNumber: $version->getVersionNumber()->getValue(),
        );
    }
}
