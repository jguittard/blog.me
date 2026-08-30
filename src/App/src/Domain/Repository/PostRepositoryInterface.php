<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Post;
use App\Domain\Value\Slug;

interface PostRepositoryInterface
{
    public function find(string $id): ?Post;

    public function findBySlug(Slug $slug): ?Post;

    /**
     * Every post, drafts included, newest change first — for the admin index.
     *
     * @return list<Post>
     */
    public function all(): array;

    /**
     * Persist a new or existing post and synchronise its tag links.
     *
     * @param  list<string> $tagIds
     * @return Post  the post with its id populated
     */
    public function save(Post $post, array $tagIds = []): Post;

    public function delete(string $id): void;
}
