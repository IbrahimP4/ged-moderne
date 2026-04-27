<?php

declare(strict_types=1);

namespace App\Domain\Document\Exception;

use App\Domain\Document\ValueObject\DocumentId;

final class DocumentNotFoundException extends \DomainException
{
    public function __construct(DocumentId $id)
    {
        parent::__construct(sprintf('Document introuvable : "%s".', $id->getValue()));
    }
}
