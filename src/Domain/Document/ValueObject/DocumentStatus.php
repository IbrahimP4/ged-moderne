<?php

declare(strict_types=1);

namespace App\Domain\Document\ValueObject;

/**
 * Statut d'un document dans son cycle de vie — PHP 8.1 backed enum.
 *
 * Remplace les constantes entières éparpillées dans SeedDMS legacy
 * (S_DRAFT = -2, S_RELEASED = 1, etc.) qui n'avaient aucune lisibilité.
 *
 * Ordre du workflow : DRAFT → PENDING_REVIEW → APPROVED → ARCHIVED
 *                              └──────────────→ REJECTED
 */
enum DocumentStatus: string
{
    case DRAFT          = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case APPROVED       = 'approved';
    case REJECTED       = 'rejected';
    case ARCHIVED       = 'archived';
    case OBSOLETE       = 'obsolete';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT          => 'Brouillon',
            self::PENDING_REVIEW => 'En attente de validation',
            self::APPROVED       => 'Approuvé',
            self::REJECTED       => 'Rejeté',
            self::ARCHIVED       => 'Archivé',
            self::OBSOLETE       => 'Obsolète',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::DRAFT || $this === self::REJECTED;
    }

    public function isVisible(): bool
    {
        return $this !== self::DRAFT && $this !== self::OBSOLETE;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT          => in_array($target, [self::PENDING_REVIEW, self::APPROVED], true),
            self::PENDING_REVIEW => in_array($target, [self::APPROVED, self::REJECTED], true),
            self::APPROVED       => in_array($target, [self::ARCHIVED, self::OBSOLETE], true),
            self::REJECTED       => $target === self::DRAFT,
            self::ARCHIVED,
            self::OBSOLETE       => false,
        };
    }

    /**
     * Migration depuis les constantes legacy SeedDMS.
     */
    public static function fromLegacyInt(int $legacyStatus): self
    {
        return match ($legacyStatus) {
            -2      => self::DRAFT,
            -1      => self::PENDING_REVIEW,
            0       => self::REJECTED,
            1       => self::APPROVED,
            2       => self::ARCHIVED,
            default => self::DRAFT,
        };
    }
}
