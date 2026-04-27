<?php

declare(strict_types=1);

namespace App\Application\Document\Command;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Infrastructure\TextExtraction\TextExtractorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

/**
 * Handler asynchrone d'indexation du contenu textuel d'un document.
 *
 * Stratégie :
 *   1. Lire le fichier depuis DocumentStorageInterface (Flysystem) → stream
 *   2. Écrire dans un fichier temporaire PHP (sys_get_temp_dir())
 *   3. Extraire le texte depuis ce fichier temporaire
 *   4. Supprimer le fichier temporaire
 *   5. Stocker le texte dans DocumentVersion.contentText
 *
 * Cette approche est compatible avec tous les transports Messenger
 * (synchrone ET asynchrone), car on lit depuis le stockage permanent
 * et non depuis le fichier temporaire HTTP qui a déjà été supprimé.
 */
#[AsMessageHandler]
final class IndexDocumentContentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentStorageInterface    $storage,
        private readonly TextExtractorService        $textExtractor,
        private readonly EntityManagerInterface      $entityManager,
        private readonly LoggerInterface             $logger,
    ) {}

    public function __invoke(IndexDocumentContentCommand $command): void
    {
        $document = $this->documentRepository->findById($command->documentId);
        if ($document === null) {
            $this->logger->warning('IndexDocumentContent: document introuvable.', [
                'documentId' => $command->documentId->getValue(),
            ]);
            return;
        }

        $latestVersion = $this->getLatestVersion($document);
        if ($latestVersion === null) {
            return;
        }

        // ── 1. Vérifier que le fichier existe dans le storage ─────────────────
        $storagePath = StoragePath::fromString($command->storagePath);

        if (!$this->storage->exists($storagePath)) {
            $this->logger->warning('IndexDocumentContent: fichier introuvable dans le storage.', [
                'storagePath' => $command->storagePath,
            ]);
            return;
        }

        // ── 2. Écrire dans un fichier temporaire pour l'extraction ────────────
        $tempFile = tempnam(sys_get_temp_dir(), 'ged_idx_');
        if ($tempFile === false) {
            $this->logger->error('IndexDocumentContent: impossible de créer un fichier temporaire.');
            return;
        }

        // Ajouter l'extension pour que les parsers puissent la détecter
        $ext = pathinfo($command->storagePath, PATHINFO_EXTENSION);
        if ($ext !== '') {
            rename($tempFile, $tempFile . '.' . $ext);
            $tempFile = $tempFile . '.' . $ext;
        }

        try {
            // ── 3. Lire depuis Flysystem → écrire dans le temp file ───────────
            $stream = $this->storage->read($storagePath);
            $fp = fopen($tempFile, 'wb');

            if ($fp === false) {
                throw new \RuntimeException("Impossible d'ouvrir le fichier temporaire en écriture.");
            }

            try {
                stream_copy_to_stream($stream, $fp);
            } finally {
                fclose($fp);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            // ── 4. Extraire le texte ──────────────────────────────────────────
            $contentText = $this->textExtractor->extract($tempFile, $command->mimeType);

            // ── 5. Persister ──────────────────────────────────────────────────
            $latestVersion->setContentText($contentText);
            $this->entityManager->flush();

            $this->logger->info('IndexDocumentContent: texte extrait avec succès.', [
                'documentId' => $command->documentId->getValue(),
                'charCount'  => $contentText !== null ? mb_strlen($contentText) : 0,
                'supported'  => $contentText !== null,
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('IndexDocumentContent: erreur lors de l\'extraction.', [
                'documentId' => $command->documentId->getValue(),
                'error'      => $e->getMessage(),
            ]);
        } finally {
            // ── 6. Toujours supprimer le fichier temporaire ───────────────────
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function getLatestVersion(Document $document): ?\App\Domain\Document\Entity\DocumentVersion
    {
        $versions = $document->getVersions()->toArray();
        if (empty($versions)) {
            return null;
        }

        usort($versions, fn ($a, $b) => $b->getVersionNumber()->getValue() <=> $a->getVersionNumber()->getValue());

        return $versions[0];
    }
}
