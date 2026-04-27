<?php

declare(strict_types=1);

namespace App\Application\Signature\Command;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\User\ValueObject\UserId;

final readonly class CreateSignatureRequestCommand
{
    public function __construct(
        public DocumentId $documentId,
        public UserId     $requesterId,
        public UserId     $signerId,
        public ?string    $message,
    ) {}
}
