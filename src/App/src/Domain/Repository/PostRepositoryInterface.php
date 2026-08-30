<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Post;
use App\Domain\Value\Slug;

interface PostRepositoryInterface
{
    public function find(int $id): ?Post;

    public function findBySlug(Slug $slug): ?Post;

    /**
     * Persist a new or existing post and synchronise its tag links.
     *
     * @param  list<int> $tagIds
     * @return Post  the post with its id populated
     */
    public function save(Post $post, array $tagIds = []): Post;

    public function delete(int $id): void;
}
