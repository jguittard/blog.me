<?php

declare(strict_types=1);

namespace App\Domain\ReadModel;

use App\Domain\Value\PostStatus;
use DateTimeImmutable;

use function mb_strlen;
use function mb_substr;
use function rtrim;

/**
 * Flat read model for post listings, built from a single JOIN query
 * (author + category + aggregated tags).
 *
 * Not `readonly` because it carries property hooks (PHP forbids hooks on
 * readonly props); immutability comes from `private(set)` instead, and the
 * derived fields are virtual `get` hooks.
 *
 * @see \App\Infrastructure\Persistence\PhpDb\PhpDbPostReadRepository
 */
final class PostListItem
{
    public function __construct(
        public private(set) string $id,
        public private(set) string $slug,
        public private(set) string $title,
        public private(set) ?string $excerpt,
        public private(set) string $bodyPreview,
        public private(set) PostStatus $status,
        public private(set) ?DateTimeImmutable $publishedAt,
        public private(set) string $authorName,
        public private(set) ?string $categoryName,
        public private(set) ?string $categorySlug,
        /** @var list<string> */
        public private(set) array $tags,
    ) {
    }

    public string $href {
        get => '/posts/' . $this->slug;
    }

    public bool $isNew {
        get => $this->publishedAt !== null
            && $this->publishedAt >= new DateTimeImmutable('-7 days');
    }

    public string $summary {
        get {
            if ($this->excerpt !== null && $this->excerpt !== '') {
                return $this->excerpt;
            }

            if (mb_strlen($this->bodyPreview) <= 160) {
                return $this->bodyPreview;
            }

            return rtrim(mb_substr($this->bodyPreview, 0, 160)) . '…';
        }
    }
}
