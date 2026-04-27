<?php

declare(strict_types=1);

namespace App\UI\Http\Security;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter Symfony : contrôle d'accès granulaire sur les Documents.
 *
 * OWASP A01 — Broken Access Control : chaque action est explicitement
 * vérifiée ici. Aucun contrôle implicite.
 *
 * @extends Voter<string, Document>
 */
final class DocumentVoter extends Voter
{
    public const VIEW    = 'document_view';
    public const EDIT    = 'document_edit';
    public const DELETE  = 'document_delete';
    public const APPROVE = 'document_approve';
    public const DOWNLOAD = 'document_download';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::APPROVE,
            self::DOWNLOAD,
        ], true) && $subject instanceof Document;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $securityUser = $token->getUser();

        if (! $securityUser instanceof SecurityUser) {
            return false;
        }

        /** @var Document $document */
        $document   = $subject;
        $domainUser = $securityUser->getDomainUser();

        return match ($attribute) {
            self::VIEW, self::DOWNLOAD => $this->canView($document, $securityUser),
            self::EDIT                 => $this->canEdit($document, $securityUser),
            self::DELETE               => $this->canDelete($document, $securityUser),
            self::APPROVE              => $domainUser->isAdmin(),
            default                    => false,
        };
    }

    private function canView(Document $document, SecurityUser $user): bool
    {
        $domainUser = $user->getDomainUser();

        // Admin voit tout
        if ($domainUser->isAdmin()) {
            return true;
        }

        // Propriétaire voit ses propres documents (tous statuts)
        if ($document->isOwnedBy($domainUser)) {
            return true;
        }

        // Les autres utilisateurs ne voient que les documents approuvés
        return $document->getStatus() === DocumentStatus::APPROVED;
    }

    private function canEdit(Document $document, SecurityUser $user): bool
    {
        $domainUser = $user->getDomainUser();

        // Le document doit être dans un état éditable
        if (! $document->getStatus()->isEditable()) {
            return false;
        }

        return $document->isOwnedBy($domainUser) || $domainUser->isAdmin();
    }

    private function canDelete(Document $document, SecurityUser $user): bool
    {
        $domainUser = $user->getDomainUser();

        // Un document APPROVED ne peut être supprimé que par un admin
        if ($document->getStatus() === DocumentStatus::APPROVED && ! $domainUser->isAdmin()) {
            return false;
        }

        return $document->isOwnedBy($domainUser) || $domainUser->isAdmin();
    }
}
