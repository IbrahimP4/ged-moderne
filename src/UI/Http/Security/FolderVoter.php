<?php

declare(strict_types=1);

namespace App\UI\Http\Security;

use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderPermissionRepositoryInterface;
use App\Domain\Folder\ValueObject\PermissionLevel;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Folder>
 */
final class FolderVoter extends Voter
{
    public const VIEW         = 'folder_view';
    public const EDIT         = 'folder_edit';
    public const DELETE       = 'folder_delete';
    public const CREATE_CHILD = 'folder_create_child';
    public const WRITE        = 'folder_write'; // upload docs / créer sous-dossiers

    public function __construct(
        private readonly FolderPermissionRepositoryInterface $permissionRepository,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::CREATE_CHILD,
            self::WRITE,
        ], true) && $subject instanceof Folder;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $securityUser = $token->getUser();
        if (!$securityUser instanceof SecurityUser) {
            return false;
        }

        /** @var Folder $folder */
        $folder     = $subject;
        $domainUser = $securityUser->getDomainUser();

        // Les admins ont toujours accès à tout
        if ($domainUser->isAdmin()) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->permissionRepository->hasAccess($folder, $domainUser, PermissionLevel::READ),

            self::WRITE,
            self::CREATE_CHILD => $this->permissionRepository->hasAccess($folder, $domainUser, PermissionLevel::WRITE),

            self::EDIT,
            self::DELETE => $folder->getOwner()->getId()->equals($domainUser->getId()),

            default => false,
        };
    }
}
