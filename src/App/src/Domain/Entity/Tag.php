<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Value\Slug;

final readonly class Tag
{
    public function __construct(
        public ?int $id,
        public Slug $slug,
        public string $name,
    ) {
    }

    public static function named(string $name): self
    {
        return new self(null, Slug::fromTitle($name), $name);
    }

    public function withId(int $id): self
    {
        return clone($this, ['id' => $id]);
    }
}
