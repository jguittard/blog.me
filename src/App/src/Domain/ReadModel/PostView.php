<?php

declare(strict_types=1);

namespace App\Domain\ReadModel;

use App\Domain\Value\PostStatus;
use DateTimeImmutable;

use function ceil;
use function max;
use function str_word_count;
use function strip_tags;

/**
 * Full single-post read model (JOIN query). `private(set)` + `get` hooks
 * for the same reason as {@see PostListItem}.
 *
 * @see \App\Infrastructure\Persistence\PhpDb\PhpDbPostReadRepository
 */
final class PostView
{
    private const WORDS_PER_MINUTE = 200;

    public function __construct(
        public private(set) string $id,
        public private(set) string $slug,
        public private(set) string $title,
        public private(set) ?string $excerpt,
        public private(set) string $body,
        public private(set) PostStatus $status,
        public private(set) ?DateTimeImmutable $publishedAt,
        public private(set) DateTimeImmutable $updatedAt,
        public private(set) string $authorName,
        public private(set) string $authorEmail,
        public private(set) ?string $categoryName,
        public private(set) ?string $categorySlug,
        /** @var list<string> */
        public private(set) array $tags,
    ) {
    }

    public string $href {
        get => '/posts/' . $this->slug;
    }

    public bool $isPublished {
        get => $this->status->isPublic() && $this->publishedAt !== null;
    }

    public int $readingTimeMinutes {
        get => max(1, (int) ceil(str_word_count(strip_tags($this->body)) / self::WORDS_PER_MINUTE));
    }
}
