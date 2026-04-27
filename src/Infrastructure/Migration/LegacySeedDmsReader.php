<?php

declare(strict_types=1);

namespace App\Infrastructure\Migration;

use Doctrine\DBAL\Connection;

/**
 * Lit les données brutes de la base SeedDMS legacy.
 *
 * Toutes les requêtes sont en lecture seule.
 * Ne connaît pas les entités Domain — retourne des tableaux associatifs.
 *
 * Schéma SeedDMS attendu :
 *   tblUsers           — comptes utilisateurs
 *   tblFolders         — arborescence de dossiers
 *   tblDocuments       — métadonnées documents
 *   tblDocumentContent — versions (fichiers physiques)
 */
class LegacySeedDmsReader
{
    public function __construct(
        private readonly Connection $legacyConnection,
    ) {}

    /**
     * @return array<int, array{id: int, login: string, fullName: string, email: string, pwd: string, role: int, disabled: int}>
     */
    public function fetchUsers(): array
    {
        /** @var array<int, array{id: int, login: string, fullName: string, email: string, pwd: string, role: int, disabled: int}> */
        return $this->legacyConnection->fetchAllAssociative(
            'SELECT id, login, fullName, email, pwd, role, disabled FROM tblUsers ORDER BY id ASC',
        );
    }

    /**
     * @return array<int, array{id: int, name: string, parent: int, owner: int, comment: string|null}>
     */
    public function fetchFolders(): array
    {
        /** @var array<int, array{id: int, name: string, parent: int, owner: int, comment: string|null}> */
        return $this->legacyConnection->fetchAllAssociative(
            'SELECT id, name, parent, owner, comment FROM tblFolders ORDER BY id ASC',
        );
    }

    /**
     * @return array<int, array{id: int, name: string, folder: int, owner: int, comment: string|null, title: string|null}>
     */
    public function fetchDocuments(): array
    {
        /** @var array<int, array{id: int, name: string, folder: int, owner: int, comment: string|null, title: string|null}> */
        return $this->legacyConnection->fetchAllAssociative(
            'SELECT id, name, folder, owner, comment, title FROM tblDocuments ORDER BY id ASC',
        );
    }

    /**
     * @return array<int, array{id: int, document: int, version: int, comment: string|null, origFileName: string, fileType: string, mimeType: string, fileSize: int, createdAt: int, dir: string}>
     */
    public function fetchVersionsForDocument(int $documentId): array
    {
        /** @var array<int, array{id: int, document: int, version: int, comment: string|null, origFileName: string, fileType: string, mimeType: string, fileSize: int, createdAt: int, dir: string}> */
        return $this->legacyConnection->fetchAllAssociative(
            'SELECT id, document, version, comment, origFileName, fileType, mimeType, fileSize, createdAt, dir
             FROM tblDocumentContent
             WHERE document = :docId
             ORDER BY version ASC',
            ['docId' => $documentId],
        );
    }

    public function countUsers(): int
    {
        $result = $this->legacyConnection->fetchOne('SELECT COUNT(*) FROM tblUsers');

        return \is_numeric($result) ? (int) $result : 0;
    }

    public function countFolders(): int
    {
        $result = $this->legacyConnection->fetchOne('SELECT COUNT(*) FROM tblFolders');

        return \is_numeric($result) ? (int) $result : 0;
    }

    public function countDocuments(): int
    {
        $result = $this->legacyConnection->fetchOne('SELECT COUNT(*) FROM tblDocuments');

        return \is_numeric($result) ? (int) $result : 0;
    }
}
