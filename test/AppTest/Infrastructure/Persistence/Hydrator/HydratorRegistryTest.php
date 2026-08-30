<?php

declare(strict_types=1);

namespace AppTest\Infrastructure\Persistence\Hydrator;

use App\Domain\Entity\Category;
use App\Domain\Entity\Post;
use App\Domain\Entity\Tag;
use App\Domain\Entity\User;
use App\Domain\ReadModel\PostListItem;
use App\Domain\ReadModel\PostView;
use App\Domain\Value\Email;
use App\Domain\Value\PostStatus;
use App\Infrastructure\Persistence\Hydrator\HydratorRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class HydratorRegistryTest extends TestCase
{
    private HydratorRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new HydratorRegistry();
    }

    public function testUserRoundTrip(): void
    {
        $user = User::register(
            Email::fromString('jane@example.com'),
            'Jane Doe',
            'hash',
            new DateTimeImmutable('2026-01-02 03:04:05'),
        )->withId(9);

        $row = $this->registry->user()->extract($user);

        self::assertSame(
            [
                'id'            => 9,
                'email'         => 'jane@example.com',
                'display_name'  => 'Jane Doe',
                'password_hash' => 'hash',
                'created_at'    => '2026-01-02 03:04:05',
            ],
            $row,
        );

        $back = $this->registry->user()->hydrate($row, $this->proto(User::class));

        self::assertEquals($user, $back);
    }

    public function testCategoryAndTagRoundTrip(): void
    {
        $category = Category::create('PHP Internals', 'Deep dives')->withId(3);
        $catRow   = $this->registry->category()->extract($category);
        self::assertSame('php-internals', $catRow['slug']);
        self::assertEquals($category, $this->registry->category()->hydrate($catRow, $this->proto(Category::class)));

        $tag    = Tag::named('PHP 8.5')->withId(4);
        $tagRow = $this->registry->tag()->extract($tag);
        self::assertSame('php-8-5', $tagRow['slug']);
        self::assertEquals($tag, $this->registry->tag()->hydrate($tagRow, $this->proto(Tag::class)));
    }

    public function testPostRoundTripWithEnumAndNullableDate(): void
    {
        $post = Post::draft(1, 'Hello World', 'Body copy here', 7, 'Teaser', new DateTimeImmutable('2026-01-01'))
            ->publish(new DateTimeImmutable('2026-02-01 09:00:00'))
            ->withId(11);

        $row = $this->registry->post()->extract($post);

        self::assertSame('published', $row['status']);
        self::assertSame('2026-02-01 09:00:00', $row['published_at']);
        self::assertSame('hello-world', $row['slug']);

        $back = $this->registry->post()->hydrate($row, $this->proto(Post::class));
        self::assertInstanceOf(Post::class, $back);

        // DB DATETIME has no sub-second precision, so compare the canonical
        // (extracted) form rather than the live objects.
        self::assertSame($row, $this->registry->post()->extract($back));
        self::assertSame(PostStatus::Published, $back->status);
        self::assertSame('hello-world', $back->slug->value);
        self::assertEquals($post->publishedAt, $back->publishedAt);
    }

    public function testPostDraftHasNullPublishedAt(): void
    {
        $post = Post::draft(1, 'Draft One', 'x', null, null, new DateTimeImmutable('2026-01-01 00:00:00'));
        $row  = $this->registry->post()->extract($post);

        self::assertNull($row['published_at']);
        self::assertNull($row['category_id']);

        $back = $this->registry->post()->hydrate($row, $this->proto(Post::class));
        self::assertNull($back->publishedAt);
    }

    public function testPostListItemHydratesFromJoinRow(): void
    {
        $row = [
            'id'            => 5,
            'slug'          => 'hello-world',
            'title'         => 'Hello World',
            'excerpt'       => null,
            'body_preview'  => 'A body preview',
            'status'        => 'published',
            'published_at'  => '2026-02-01 09:00:00',
            'author_name'   => 'Jane Doe',
            'category_name' => 'PHP',
            'tags'          => 'php,8.5,hydrator',
        ];

        $item = $this->registry->postListItem()->hydrate($row, $this->proto(PostListItem::class));

        self::assertInstanceOf(PostListItem::class, $item);
        self::assertSame(['php', '8.5', 'hydrator'], $item->tags);
        self::assertSame(PostStatus::Published, $item->status);
        self::assertSame('/posts/hello-world', $item->href);
        self::assertSame('A body preview', $item->summary);
    }

    public function testPostViewHandlesEmptyTagList(): void
    {
        $row = [
            'id'            => 5,
            'slug'          => 'lonely',
            'title'         => 'Lonely Post',
            'excerpt'       => null,
            'body'          => 'Body',
            'status'        => 'draft',
            'published_at'  => null,
            'updated_at'    => '2026-01-05 00:00:00',
            'author_name'   => 'Jane',
            'author_email'  => 'jane@example.com',
            'category_name' => null,
            'category_slug' => null,
            'tags'          => null,
        ];

        $view = $this->registry->postView()->hydrate($row, $this->proto(PostView::class));

        self::assertInstanceOf(PostView::class, $view);
        self::assertSame([], $view->tags);
        self::assertNull($view->categoryName);
        self::assertFalse($view->isPublished);
    }

    /** @param class-string $class */
    private function proto(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
