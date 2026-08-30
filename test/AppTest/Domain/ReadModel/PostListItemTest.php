<?php

declare(strict_types=1);

namespace AppTest\Domain\ReadModel;

use App\Domain\ReadModel\PostListItem;
use App\Domain\Value\PostStatus;
use DateTimeImmutable;
use Error;
use PHPUnit\Framework\TestCase;

use function mb_strlen;
use function str_repeat;

final class PostListItemTest extends TestCase
{
    private function item(?string $excerpt, string $bodyPreview, ?DateTimeImmutable $publishedAt): PostListItem
    {
        return new PostListItem(
            id: 1,
            slug: 'hello-world',
            title: 'Hello World',
            excerpt: $excerpt,
            bodyPreview: $bodyPreview,
            status: PostStatus::Published,
            publishedAt: $publishedAt,
            authorName: 'Jane Doe',
            categoryName: 'PHP',
            tags: ['php', '8.5'],
        );
    }

    public function testHrefHook(): void
    {
        self::assertSame('/posts/hello-world', $this->item(null, 'body', null)->href);
    }

    public function testIsNewHook(): void
    {
        self::assertTrue($this->item(null, 'b', new DateTimeImmutable('-2 days'))->isNew);
        self::assertFalse($this->item(null, 'b', new DateTimeImmutable('-30 days'))->isNew);
        self::assertFalse($this->item(null, 'b', null)->isNew);
    }

    public function testSummaryPrefersExcerpt(): void
    {
        self::assertSame('Hand-written excerpt.', $this->item('Hand-written excerpt.', 'body text', null)->summary);
    }

    public function testSummaryFallsBackToTruncatedBody(): void
    {
        $summary = $this->item(null, str_repeat('a', 400), null)->summary;

        self::assertSame(161, mb_strlen($summary));
        self::assertStringEndsWith('…', $summary);
    }

    public function testPropertiesAreNotWritableFromOutside(): void
    {
        $item = $this->item(null, 'b', null);

        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/private\(set\)/');

        $item->title = 'hacked'; // @phpstan-ignore-line
    }
}
