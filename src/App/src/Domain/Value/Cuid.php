<?php

declare(strict_types=1);

namespace App\Domain\Value;

use Visus\Cuid2\Cuid2;

/**
 * Collision-resistant identifier used for every primary key.
 *
 * A CUID2 is a 24-character, URL-safe, lowercase alphanumeric string that can be
 * generated client-side without coordinating with the database.
 */
final class Cuid
{
    public const int LENGTH = 24;

    public static function generate(): string
    {
        return (string) new Cuid2(self::LENGTH);
    }
}
