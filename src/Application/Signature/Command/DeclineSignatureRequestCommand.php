<?php

declare(strict_types=1);

namespace App\Application\Signature\Command;

use App\Domain\Signature\ValueObject\SignatureRequestId;
use App\Domain\User\ValueObject\UserId;

final readonly class DeclineSignatureRequestCommand
{
    public function __construct(
        public SignatureRequestId $signatureRequestId,
        public UserId             $declinedBy,
        public ?string            $comment,
    ) {}
}
