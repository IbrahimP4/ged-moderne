<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\AuditLog\ValueObject\AuditLogId;

final class AuditLogIdType extends AbstractUuidType
{
    public function getName(): string
    {
        return 'audit_log_id';
    }

    protected function fromString(string $value): AuditLogId
    {
        return AuditLogId::fromString($value);
    }
}
