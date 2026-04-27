<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Signature\ValueObject\SignatureRequestId;

final class SignatureRequestIdType extends AbstractUuidType
{
    public function getName(): string
    {
        return 'signature_request_id';
    }

    protected function fromString(string $value): SignatureRequestId
    {
        return SignatureRequestId::fromString($value);
    }
}
