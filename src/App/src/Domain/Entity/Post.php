<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Value\Cuid;
use App\Domain\Value\PostStatus;
use App\Domain\Value\Slug;
use DateTimeImmutable;

use function ceil;
use function max;
use function str_word_count;
use function strip_tags;

/**
 * A blog post. Immutable: every change returns a new instance via `clone with`
 * (PHP 8.5) and stamps a fresh `updatedAt`.
 */
final readonly class Post
{
    private const WORDS_PER_MINUTE = 200;

    public function __construct(
        public string $id,
        public string $authorId,
        public ?string $categoryId,
        public Slug $slug,
        public string $title,
        public ?string $excerpt,
        public string $body,
        public PostStatus $status,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public static function draft(
        string $authorId,
        string $title,
        string $body,
        ?string $categoryId = null,
        ?string $excerpt = null,
        ?DateTimeImmutable $now = null,
    ): self {
        // Second precision only: MySQL DATETIME has no sub-second component,
        // so entities must round-trip faithfully.
        $now = ($now ?? new DateTimeImmutable())->setMicrosecond(0);

        return new self(
            id: Cuid::generate(),
            authorId: $authorId,
            categoryId: $categoryId,
            slug: Slug::fromTitle($title),
            title: $title,
            excerpt: $excerpt,
            body: $body,
            status: PostStatus::Draft,
            publishedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function rename(string $title): self
    {
        return $this->with(['title' => $title, 'slug' => Slug::fromTitle($title)]);
    }

    public function editBody(string $body, ?string $excerpt = null): self
    {
        return $this->with(['body' => $body, 'excerpt' => $excerpt]);
    }

    public function reclassify(?string $categoryId): self
    {
        return $this->with(['categoryId' => $categoryId]);
    }

    public function publish(?DateTimeImmutable $at = null): self
    {
        $at = ($at ?? new DateTimeImmutable())->setMicrosecond(0);

        return $this->with(['status' => PostStatus::Published, 'publishedAt' => $at]);
    }

    public function unpublish(): self
    {
        return $this->with(['status' => PostStatus::Draft, 'publishedAt' => null]);
    }

    public function archive(): self
    {
        return $this->with(['status' => PostStatus::Archived]);
    }

    public function isPublished(?DateTimeImmutable $now = null): bool
    {
        return $this->status->isPublic()
            && $this->publishedAt !== null
            && $this->publishedAt <= ($now ?? new DateTimeImmutable());
    }

    public function readingTimeMinutes(): int
    {
        $words = str_word_count(strip_tags($this->body));

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    /** @param array<string, mixed> $changes */
    private function with(array $changes): self
    {
        return clone($this, [...$changes, 'updatedAt' => (new DateTimeImmutable())->setMicrosecond(0)]);
    }
}
