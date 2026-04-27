<?php

declare(strict_types=1);

namespace App\Application\Signature\Command;

use App\Domain\Signature\ValueObject\SignatureRequestId;
use App\Domain\User\ValueObject\UserId;

final readonly class SignDocumentCommand
{
    public function __construct(
        public SignatureRequestId $signatureRequestId,
        public UserId             $signedBy,
        public ?string            $comment,
    ) {}
}
