<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Category;
use App\Domain\Value\Slug;

interface CategoryRepositoryInterface
{
    public function find(string $id): ?Category;

    public function findBySlug(Slug $slug): ?Category;

    /** @return list<Category> */
    public function all(): array;

    public function save(Category $category): Category;

    public function delete(string $id): void;
}
