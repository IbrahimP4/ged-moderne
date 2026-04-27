<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Document\ValueObject\DocumentId;

final class DocumentIdType extends AbstractUuidType
{
    public function getName(): string
    {
        return 'document_id';
    }

    protected function fromString(string $value): DocumentId
    {
        return DocumentId::fromString($value);
    }
}
