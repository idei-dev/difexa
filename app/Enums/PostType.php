<?php

namespace App\Enums;

enum PostType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';

    /**
     * Get a human-readable label for the post type.
     */
    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Texto',
            self::IMAGE => 'Imagen',
            self::VIDEO => 'Video',
        };
    }

    /**
     * Determine if this type requires media attachments.
     */
    public function isMedia(): bool
    {
        return $this === self::IMAGE || $this === self::VIDEO;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

