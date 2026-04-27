<?php

declare(strict_types=1);

namespace App\Application\Document\Query;

use App\Domain\Document\Entity\Document;

/**
 * DTO enrichi pour les résultats de recherche.
 * Étend les informations de DocumentDTO avec le snippet de contexte.
 */
final readonly class SearchResultDTO
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string  $id,
        public string  $title,
        public string  $status,
        public string  $statusLabel,
        public string  $folderId,
        public string  $folderName,
        public string  $ownerUsername,
        public int     $versionCount,
        public ?string $mimeType,
        public int     $fileSizeBytes,
        public string  $createdAt,
        public string  $updatedAt,
        public array   $tags,
        // Enrichissements full-text
        public ?string $snippet,          // Extrait du contenu autour du terme trouvé
        public bool    $matchedInContent, // true = le match vient du contenu, pas du titre
    ) {}

    /**
     * @param array{document: Document, snippet: string|null, matchedInContent: bool} $result
     */
    public static function fromSearchResult(array $result): self
    {
        $doc     = $result['document'];
        $latest  = $doc->getLatestVersion();

        return new self(
            id:               $doc->getId()->getValue(),
            title:            $doc->getTitle(),
            status:           $doc->getStatus()->value,
            statusLabel:      $doc->getStatus()->label(),
            folderId:         $doc->getFolder()->getId()->getValue(),
            folderName:       $doc->getFolder()->getName(),
            ownerUsername:    $doc->getOwner()->getUsername(),
            versionCount:     $doc->getVersions()->count(),
            mimeType:         $latest?->getMimeType()->getValue(),
            fileSizeBytes:    $latest?->getFileSize()->getBytes() ?? 0,
            createdAt:        $doc->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt:        $doc->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            tags:             $doc->getTags(),
            snippet:          $result['snippet'],
            matchedInContent: $result['matchedInContent'],
        );
    }
}
