<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Tag;
use App\Domain\Value\Slug;

interface TagRepositoryInterface
{
    public function find(string $id): ?Tag;

    public function findBySlug(Slug $slug): ?Tag;

    /** @return list<Tag> ordered by name */
    public function all(): array;

    /**
     * Resolve each name to an existing tag or create it; returns the saved tags.
     *
     * @param  list<string> $names
     * @return list<Tag>
     */
    public function findOrCreateByNames(array $names): array;

    /** @return list<Tag> */
    public function forPost(string $postId): array;

    public function save(Tag $tag): Tag;

    public function delete(string $id): void;
}
