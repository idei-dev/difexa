<?php

namespace App\Enums;

enum PostStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::PENDING => 'Pendiente de Aprobación',
            self::APPROVED => 'Aprobado',
            self::REJECTED => 'Rechazado',
            self::EXPIRED => 'Expirado',
        };
    }

    /**
     * Determine if a post with this status can be edited by its author.
     */
    public function canBeEdited(): bool
    {
        return match ($this) {
            self::DRAFT, self::REJECTED => true,
            self::PENDING, self::APPROVED, self::EXPIRED => false,
        };
    }

    /**
     * Determine if a post with this status can be submitted for review.
     */
    public function canBeSubmitted(): bool
    {
        return match ($this) {
            self::DRAFT, self::REJECTED => true,
            self::PENDING, self::APPROVED, self::EXPIRED => false,
        };
    }

    /**
     * Determine if a post with this status can be moderated (approved/rejected).
     */
    public function canBeModerated(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Returns color variant for USIM badges/status indicators.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::EXPIRED => 'muted',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

