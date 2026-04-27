<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Folder\Entity\Folder;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineDocumentRepository implements DocumentRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function findById(DocumentId $id): ?Document
    {
        return $this->entityManager->find(Document::class, $id);
    }

    /** @return list<Document> */
    public function findByFolder(Folder $folder, int $limit = 50, int $offset = 0): array
    {
        /** @var list<Document> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('d')
            ->from(Document::class, 'd')
            ->where('d.folder = :folder')
            ->andWhere('d.deletedAt IS NULL')
            ->orderBy('d.updatedAt', 'DESC')
            ->setParameter('folder', $folder)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Document> */
    public function findByOwner(User $owner): array
    {
        /** @var list<Document> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('d')
            ->from(Document::class, 'd')
            ->where('d.owner = :owner')
            ->andWhere('d.deletedAt IS NULL')
            ->orderBy('d.createdAt', 'DESC')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Document> */
    public function findDeleted(): array
    {
        /** @var list<Document> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('d')
            ->from(Document::class, 'd')
            ->where('d.deletedAt IS NOT NULL')
            ->orderBy('d.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Document> */
    public function findFavorites(User $user): array
    {
        /** @var list<Document> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('d')
            ->from(Document::class, 'd')
            ->join('App\Domain\Document\Entity\DocumentFavorite', 'f', 'WITH', 'f.document = d AND f.user = :user')
            ->where('d.deletedAt IS NULL')
            ->orderBy('f.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function isFavorite(Document $document, User $user): bool
    {
        $count = (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from('App\Domain\Document\Entity\DocumentFavorite', 'f')
            ->where('f.document = :document')
            ->andWhere('f.user = :user')
            ->setParameter('document', $document)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function addFavorite(Document $document, User $user): void
    {
        if ($this->isFavorite($document, $user)) {
            return;
        }

        $favorite = new \App\Domain\Document\Entity\DocumentFavorite($document, $user);
        $this->entityManager->persist($favorite);
        $this->entityManager->flush();
    }

    public function removeFavorite(Document $document, User $user): void
    {
        $favorite = $this->entityManager
            ->createQueryBuilder()
            ->select('f')
            ->from('App\Domain\Document\Entity\DocumentFavorite', 'f')
            ->where('f.document = :document')
            ->andWhere('f.user = :user')
            ->setParameter('document', $document)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        if ($favorite !== null) {
            $this->entityManager->remove($favorite);
            $this->entityManager->flush();
        }
    }

    public function save(Document $document): void
    {
        $this->entityManager->persist($document);
        $this->entityManager->flush();
    }

    public function delete(Document $document): void
    {
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }

    public function count(): int
    {
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->where('d.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByFolder(Folder $folder): int
    {
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->where('d.folder = :folder')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('folder', $folder)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->where('d.status = :status')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function countUploadsByDay(int $days = 30): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$days} days")->format('Y-m-d');

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT DATE(created_at) AS date, COUNT(*) AS cnt
             FROM documents
             WHERE created_at >= :since AND deleted_at IS NULL
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            ['since' => $since],
        );

        return array_map(
            static fn ($r) => ['date' => $r['date'], 'count' => (int) $r['cnt']],
            $rows,
        );
    }

    /**
     * Recherche full-text dans les titres ET le contenu des documents.
     *
     * Stratégie multi-mots : chaque mot du query doit apparaître quelque part
     * (dans le titre OU dans le contenu). Retourne aussi des snippets de contexte.
     *
     * @param list<string> $tags
     * @return list<array{document: Document, snippet: string|null, matchedInContent: bool}>
     */
    public function search(
        string  $query,
        ?Folder $folder = null,
        int     $limit = 50,
        ?string $status = null,
        array   $tags = [],
    ): array {
        // Découper la requête en mots significatifs (>= 2 caractères)
        $words = array_filter(
            array_map('trim', preg_split('/\s+/', trim($query)) ?: []),
            fn (string $w) => mb_strlen($w) >= 2,
        );

        if (empty($words) && $query !== '') {
            $words = [trim($query)];
        }

        $qb = $this->entityManager
            ->createQueryBuilder()
            ->select('DISTINCT d')
            ->from(Document::class, 'd')
            ->leftJoin('d.versions', 'v')
            ->where('d.deletedAt IS NULL')
            ->orderBy('d.updatedAt', 'DESC')
            ->setMaxResults($limit);

        // Chaque mot doit matcher dans le titre OU le contenu
        foreach ($words as $i => $word) {
            $pattern = '%' . addslashes($word) . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('d.title', ":word{$i}"),
                    $qb->expr()->like('v.contentText', ":word{$i}"),
                ),
            );
            $qb->setParameter("word{$i}", $pattern);
        }

        if ($folder !== null) {
            $qb->andWhere('d.folder = :folder')->setParameter('folder', $folder);
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('d.status = :status')->setParameter('status', $status);
        }

        foreach ($tags as $i => $tag) {
            $qb->andWhere("d.tags LIKE :tagPattern{$i}")
               ->setParameter("tagPattern{$i}", '%"' . addslashes($tag) . '"%');
        }

        /** @var list<Document> $documents */
        $documents = $qb->getQuery()->getResult();

        // Enrichissement : extraction du snippet de contexte
        return array_map(function (Document $doc) use ($words): array {
            $snippet          = null;
            $matchedInContent = false;

            $lowerTitle = mb_strtolower($doc->getTitle());
            $titleMatch = !empty(array_filter($words, fn ($w) => str_contains($lowerTitle, mb_strtolower($w))));

            // Si le titre ne matche pas, chercher dans le contenu de la dernière version
            $versions = $doc->getVersions()->toArray();
            if (!empty($versions)) {
                usort($versions, fn ($a, $b) => $b->getVersionNumber()->getValue() <=> $a->getVersionNumber()->getValue());
                $latestVersion = $versions[0];
                $contentText   = $latestVersion->getContentText();

                if ($contentText !== null) {
                    $lowerContent = mb_strtolower($contentText);
                    $hasContentMatch = !empty(array_filter($words, fn ($w) => str_contains($lowerContent, mb_strtolower($w))));

                    if ($hasContentMatch) {
                        $matchedInContent = !$titleMatch;
                        $snippet          = $this->extractSnippet($contentText, $words);
                    }
                }
            }

            return [
                'document'         => $doc,
                'snippet'          => $snippet,
                'matchedInContent' => $matchedInContent,
            ];
        }, $documents);
    }

    /**
     * Extrait un extrait de ~200 caractères autour du premier terme trouvé.
     *
     * @param list<string> $words
     */
    private function extractSnippet(string $content, array $words): string
    {
        $contextChars = 120;
        $lowerContent = mb_strtolower($content);

        // Trouver la position du premier mot dans le contenu
        $pos = false;
        foreach ($words as $word) {
            $found = mb_strpos($lowerContent, mb_strtolower($word));
            if ($found !== false) {
                $pos = $found;
                break;
            }
        }

        if ($pos === false) {
            return mb_substr($content, 0, $contextChars * 2) . '…';
        }

        // Extraire le contexte : $contextChars avant et après le terme
        $start  = max(0, $pos - $contextChars);
        $end    = min(mb_strlen($content), $pos + $contextChars);
        $snippet = mb_substr($content, $start, $end - $start);

        // Ajouter des ellipses si on n'est pas au début/fin
        if ($start > 0) {
            $snippet = '…' . ltrim($snippet);
        }
        if ($end < mb_strlen($content)) {
            $snippet = rtrim($snippet) . '…';
        }

        return $snippet;
    }
}

