<?php

declare(strict_types=1);

namespace App\Domain\Folder\Exception;

use App\Domain\Folder\ValueObject\FolderId;

final class FolderNotFoundException extends \DomainException
{
    public function __construct(FolderId $id)
    {
        parent::__construct(sprintf('Dossier introuvable : "%s".', $id->getValue()));
    }
}
