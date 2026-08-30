<?php

declare(strict_types=1);

namespace Admin\Support;

use App\Domain\Entity\Post;
use App\Domain\Entity\Tag;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\TagRepositoryInterface;

use function array_map;

/**
 * The write path for admin post forms: turns filtered form data into a {@see Post}
 * (via {@see PostFormMapper}), resolves the comma-separated tags and persists.
 */
final class PostWriter
{
    public function __construct(
        private readonly PostRepositoryInterface $posts,
        private readonly TagRepositoryInterface $tags,
        private readonly PostFormMapper $mapper,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $authorId): void
    {
        $this->persist($this->mapper->create($data, $authorId), $data);
    }

    /** @param array<string, mixed> $data */
    public function update(Post $post, array $data): void
    {
        $this->persist($this->mapper->apply($post, $data), $data);
    }

    /**
     * Filtered form array for populating the edit form.
     *
     * @return array<string, string>
     */
    public function formData(Post $post): array
    {
        $names = array_map(static fn (Tag $tag): string => $tag->name, $this->tags->forPost($post->id));

        return $this->mapper->toArray($post, $names);
    }

    /** @param array<string, mixed> $data */
    private function persist(Post $post, array $data): void
    {
        $tagIds = array_map(
            static fn (Tag $tag): string => $tag->id,
            $this->tags->findOrCreateByNames($this->mapper->splitTags($data['tags'] ?? '')),
        );

        $this->posts->save($post, $tagIds);
    }
}
