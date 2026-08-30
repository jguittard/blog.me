<?php

declare(strict_types=1);

namespace App\Domain\Value;

enum PostStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Archived  = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Draft',
            self::Published => 'Published',
            self::Archived  => 'Archived',
        };
    }

    /** Whether a post in this status is visible to the public. */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
