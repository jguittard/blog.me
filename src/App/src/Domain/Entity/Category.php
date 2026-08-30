<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Value\Cuid;
use App\Domain\Value\Slug;

final readonly class Category
{
    public function __construct(
        public string $id,
        public Slug $slug,
        public string $name,
        public ?string $description,
    ) {
    }

    public static function create(string $name, ?string $description = null): self
    {
        return new self(Cuid::generate(), Slug::fromTitle($name), $name, $description);
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
