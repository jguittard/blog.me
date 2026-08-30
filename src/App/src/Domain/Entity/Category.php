<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Value\Slug;

final readonly class Category
{
    public function __construct(
        public ?int $id,
        public Slug $slug,
        public string $name,
        public ?string $description,
    ) {
    }

    public static function create(string $name, ?string $description = null): self
    {
        return new self(null, Slug::fromTitle($name), $name, $description);
    }

    public function withId(int $id): self
    {
        return clone($this, ['id' => $id]);
    }

    public function rename(string $name): self
    {
        return clone($this, ['name' => $name, 'slug' => Slug::fromTitle($name)]);
    }

    public function describedAs(?string $description): self
    {
        return clone($this, ['description' => $description]);
    }
}
