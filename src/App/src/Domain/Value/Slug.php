<?php

declare(strict_types=1);

namespace App\Domain\Value;

use InvalidArgumentException;

use function preg_match;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * A URL-safe identifier: lowercase, digits and single hyphens.
 */
final readonly class Slug
{
    private const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private function __construct(public string $value)
    {
    }

    /** Accept an already-valid slug (e.g. a value coming back from the database). */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("Invalid slug: \"{$value}\"");
        }

        return new self($value);
    }

    /** Derive a slug from arbitrary text (e.g. a post title). */
    public static function fromTitle(string $title): self
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            throw new InvalidArgumentException("Cannot derive a slug from \"{$title}\"");
        }

        return new self($slug);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
