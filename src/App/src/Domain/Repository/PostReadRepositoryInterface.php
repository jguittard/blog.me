<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\ReadModel\PostListItem;
use App\Domain\ReadModel\PostView;

/**
 * Read side: denormalised views assembled with JOINs, no N+1.
 */
interface PostReadRepositoryInterface
{
    /** @return list<PostListItem> */
    public function listPublished(int $limit = 20, int $offset = 0): array;

    /** @return list<PostListItem> */
    public function listByCategory(string $categorySlug, int $limit = 20, int $offset = 0): array;

    public function viewBySlug(string $slug): ?PostView;
}
