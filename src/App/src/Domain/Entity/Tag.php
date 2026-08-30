<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Value\Cuid;
use App\Domain\Value\Slug;

final readonly class Tag
{
    public function __construct(
        public string $id,
        public Slug $slug,
        public string $name,
    ) {
    }

    public static function named(string $name): self
    {
        return new self(Cuid::generate(), Slug::fromTitle($name), $name);
    }
}
