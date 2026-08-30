<?php

declare(strict_types=1);

namespace AppTest\Domain\Entity;

use App\Domain\Entity\Post;
use App\Domain\Value\Cuid;
use App\Domain\Value\PostStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function preg_match;
use function str_repeat;

final class PostTest extends TestCase
{
    private function draft(): Post
    {
        return Post::draft(
            authorId: Cuid::generate(),
            title: 'Property Hooks in PHP 8.5',
            body: str_repeat('word ', 400),
            categoryId: Cuid::generate(),
            excerpt: 'A short look.',
            now: new DateTimeImmutable('2026-01-01 12:00:00'),
        );
    }

    public function testDraftFactory(): void
    {
        $post = $this->draft();

        self::assertSame(1, preg_match('/^[a-z0-9]{24}$/', $post->id));
        self::assertSame(PostStatus::Draft, $post->status);
        self::assertNull($post->publishedAt);
        self::assertSame('property-hooks-in-php-8-5', $post->slug->value);
        self::assertEquals($post->createdAt, $post->updatedAt);
    }

    public function testMutationsKeepTheSameId(): void
    {
        $draft = $this->draft();

        self::assertSame($draft->id, $draft->publish()->id);
        self::assertSame($draft->id, $draft->rename('Something Else')->id);
    }

    public function testWithImageIsImmutable(): void
    {
        $draft = $this->draft();

        $withImage = $draft->withImage('https://cdn.example/cover.svg', 'Cover');

        self::assertNull($draft->imageUrl);
        self::assertNotSame($draft, $withImage);
        self::assertSame('https://cdn.example/cover.svg', $withImage->imageUrl);
        self::assertSame('Cover', $withImage->imageAlt);
        self::assertSame($draft->id, $withImage->id);

        self::assertNull($withImage->withImage(null)->imageUrl);
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
        $author = Cuid::generate();
        $post   = Post::draft($author, 'x', str_repeat('word ', 401), now: new DateTimeImmutable());

        // 401 words / 200 wpm -> 3 minutes
        self::assertSame(3, $post->readingTimeMinutes());
        self::assertSame(1, Post::draft($author, 'y', 'tiny body', now: new DateTimeImmutable())->readingTimeMinutes());
    }
}
