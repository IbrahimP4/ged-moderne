<?php

declare(strict_types=1);

namespace App\Domain\Signature\Exception;

use App\Domain\Signature\ValueObject\SignatureRequestId;

final class SignatureRequestNotFoundException extends \DomainException
{
    public function __construct(SignatureRequestId $id)
    {
        parent::__construct(sprintf('Demande de signature introuvable : "%s".', $id->getValue()));
    }
}
