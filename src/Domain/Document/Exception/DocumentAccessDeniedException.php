<?php

declare(strict_types=1);

namespace App\Domain\Document\Exception;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\User\ValueObject\UserId;

final class DocumentAccessDeniedException extends \DomainException
{
    public function __construct(UserId $userId, DocumentId $documentId)
    {
        parent::__construct(sprintf(
            'Accès refusé : l\'utilisateur "%s" ne peut pas accéder au document "%s".',
            $userId->getValue(),
            $documentId->getValue(),
        ));
    }
}
