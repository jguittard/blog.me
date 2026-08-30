<?php

declare(strict_types=1);

namespace AppTest\Domain\Entity;

use App\Domain\Entity\Post;
use App\Domain\Value\PostStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class PostTest extends TestCase
{
    private function draft(): Post
    {
        return Post::draft(
            authorId: 1,
            title: 'Property Hooks in PHP 8.5',
            body: str_repeat('word ', 400),
            categoryId: 7,
            excerpt: 'A short look.',
            now: new DateTimeImmutable('2026-01-01 12:00:00'),
        );
    }

    public function testDraftFactory(): void
    {
        $post = $this->draft();

        self::assertNull($post->id);
        self::assertSame(PostStatus::Draft, $post->status);
        self::assertNull($post->publishedAt);
        self::assertSame('property-hooks-in-php-8-5', $post->slug->value);
        self::assertEquals($post->createdAt, $post->updatedAt);
    }

    public function testPublishReturnsNewInstanceAndLeavesOriginalUntouched(): void
    {
        $draft = $this->draft();
        $at    = new DateTimeImmutable('2026-02-01 09:00:00');

        $published = $draft->publish($at);

        // new instance
        self::assertNotSame($draft, $published);
        self::assertSame(PostStatus::Published, $published->status);
        self::assertEquals($at, $published->publishedAt);
        self::assertGreaterThanOrEqual($published->createdAt, $published->updatedAt);

        // original is immutable
        self::assertSame(PostStatus::Draft, $draft->status);
        self::assertNull($draft->publishedAt);
    }

    public function testRenameReslugsAndBumpsUpdatedAt(): void
    {
        $post    = $this->draft();
        $renamed = $post->rename('A Brand New Title');

        self::assertSame('A Brand New Title', $renamed->title);
        self::assertSame('a-brand-new-title', $renamed->slug->value);
        self::assertSame('Property Hooks in PHP 8.5', $post->title);
    }

    public function testIsPublishedHonoursScheduledDate(): void
    {
        $future = $this->draft()->publish(new DateTimeImmutable('2999-01-01'));

        self::assertFalse($future->isPublished(new DateTimeImmutable('2026-06-01')));
        self::assertTrue($future->isPublished(new DateTimeImmutable('2999-06-01')));
    }

    public function testReadingTimeMinutes(): void
    {
        $post = Post::draft(1, 'x', str_repeat('word ', 401), now: new DateTimeImmutable());

        // 401 words / 200 wpm -> 3 minutes
        self::assertSame(3, $post->readingTimeMinutes());
        self::assertSame(1, Post::draft(1, 'y', 'tiny body', now: new DateTimeImmutable())->readingTimeMinutes());
    }

    public function testWithIdDoesNotBumpUpdatedAt(): void
    {
        $post  = $this->draft();
        $saved = $post->withId(42);

        self::assertSame(42, $saved->id);
        self::assertEquals($post->updatedAt, $saved->updatedAt);
    }
}
