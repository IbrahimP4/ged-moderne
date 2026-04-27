<?php

declare(strict_types=1);

namespace App\Application\Document\Query;

final readonly class SearchDocumentsQuery
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $q,
        public ?string $folderId = null,
        public ?string $status = null,
        public array $tags = [],
        public int $limit = 50,
    ) {}
}
