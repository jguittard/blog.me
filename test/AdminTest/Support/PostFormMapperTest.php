<?php

declare(strict_types=1);

namespace AdminTest\Support;

use Admin\Support\PostFormMapper;
use App\Domain\Entity\Post;
use App\Domain\Value\Cuid;
use App\Domain\Value\PostStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PostFormMapperTest extends TestCase
{
    private PostFormMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PostFormMapper();
    }

    public function testCreateSetsAuthorDerivesSlugAndPublishes(): void
    {
        $author = Cuid::generate();

        $post = $this->mapper->create([
            'title'       => 'My First Post',
            'body'        => 'Body.',
            'excerpt'     => null,
            'categoryId'  => null,
            'status'      => 'published',
            'publishedAt' => '2026-02-01T09:00',
            'imageUrl'    => 'https://cdn.example/c.svg',
            'imageAlt'    => 'Cover',
        ], $author);

        self::assertSame($author, $post->authorId);
        self::assertSame('my-first-post', $post->slug->value);
        self::assertSame(PostStatus::Published, $post->status);
        self::assertSame('2026-02-01 09:00:00', $post->publishedAt?->format('Y-m-d H:i:s'));
        self::assertSame('https://cdn.example/c.svg', $post->imageUrl);
    }

    public function testApplyRenamesWhenTitleChangesAndKeepsIdAndPublishDate(): void
    {
        $post = Post::draft(Cuid::generate(), 'Original', 'Body', now: new DateTimeImmutable('2026-01-01'))
            ->publish(new DateTimeImmutable('2026-01-05 08:00:00'));

        $updated = $this->mapper->apply($post, [
            'title'       => 'Renamed Title',
            'body'        => 'New body.',
            'excerpt'     => null,
            'categoryId'  => null,
            'status'      => 'published',
            'publishedAt' => '',
            'imageUrl'    => null,
            'imageAlt'    => null,
        ]);

        self::assertSame($post->id, $updated->id);
        self::assertSame('renamed-title', $updated->slug->value);
        self::assertSame('New body.', $updated->body);
        self::assertEquals($post->publishedAt, $updated->publishedAt); // kept, form left it blank
    }

    public function testApplyStatusTransitions(): void
    {
        $published = Post::draft(Cuid::generate(), 'X', 'Body')->publish(new DateTimeImmutable('2026-01-01'));

        $base = [
            'title' => 'X',
            'body' => 'Body',
            'excerpt' => null,
            'categoryId' => null,
            'publishedAt' => '',
            'imageUrl' => null,
            'imageAlt' => null,
        ];

        $toDraft    = $this->mapper->apply($published, ['status' => 'draft'] + $base);
        $toArchived = $this->mapper->apply($published, ['status' => 'archived'] + $base);

        self::assertSame(PostStatus::Draft, $toDraft->status);
        self::assertNull($toDraft->publishedAt);
        self::assertSame(PostStatus::Archived, $toArchived->status);
    }

    public function testSplitTags(): void
    {
        self::assertSame(['a', 'b', 'c'], $this->mapper->splitTags(' a, b ,, c '));
        self::assertSame([], $this->mapper->splitTags(''));
        self::assertSame([], $this->mapper->splitTags(null));
    }

    public function testToArrayRoundTripsThroughTheForm(): void
    {
        $post = Post::draft(Cuid::generate(), 'Round Trip', 'Body', now: new DateTimeImmutable('2026-03-01'))
            ->publish(new DateTimeImmutable('2026-03-02 10:30:00'));

        $array = $this->mapper->toArray($post, ['x', 'y']);

        self::assertSame('Round Trip', $array['title']);
        self::assertSame('published', $array['status']);
        self::assertSame('2026-03-02T10:30', $array['publishedAt']);
        self::assertSame('x, y', $array['tags']);
    }
}
