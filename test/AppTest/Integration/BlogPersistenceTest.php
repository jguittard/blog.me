<?php

declare(strict_types=1);

namespace AppTest\Integration;

use App\Domain\Entity\Category;
use App\Domain\Entity\Post;
use App\Domain\Entity\Tag;
use App\Domain\Entity\User;
use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Repository\PostReadRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\TagRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Value\Email;
use App\Domain\Value\PostStatus;
use App\Domain\Value\Slug;
use App\Infrastructure\Persistence\Migration\MigrationRunner;
use DateTimeImmutable;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Throwable;

use function array_map;
use function password_hash;
use function str_repeat;

use const PASSWORD_BCRYPT;

/**
 * Exercises the php-db repositories and JOIN read models against the running
 * `mariadb` container. Run with `composer test-integration` (stack up).
 */
final class BlogPersistenceTest extends TestCase
{
    private AdapterInterface $db;
    private UserRepositoryInterface $users;
    private CategoryRepositoryInterface $categories;
    private TagRepositoryInterface $tags;
    private PostRepositoryInterface $posts;
    private PostReadRepositoryInterface $read;

    protected function setUp(): void
    {
        /** @var ContainerInterface $container */
        $container = require __DIR__ . '/../../../config/container.php';

        $db = $container->get(AdapterInterface::class);
        self::assertInstanceOf(AdapterInterface::class, $db);
        $this->db = $db;

        try {
            $this->db->getDriver()->getConnection()->connect();
        } catch (Throwable $e) {
            self::markTestSkipped('Database not reachable: ' . $e->getMessage());
        }

        $runner = $container->get(MigrationRunner::class);
        self::assertInstanceOf(MigrationRunner::class, $runner);
        $runner->migrate();

        $this->db->executeQuery('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['post_tag', 'posts', 'tags', 'categories', 'users'] as $table) {
            $this->db->executeQuery("TRUNCATE TABLE `{$table}`");
        }
        $this->db->executeQuery('SET FOREIGN_KEY_CHECKS = 1');

        $this->users      = $this->service($container, UserRepositoryInterface::class);
        $this->categories = $this->service($container, CategoryRepositoryInterface::class);
        $this->tags       = $this->service($container, TagRepositoryInterface::class);
        $this->posts      = $this->service($container, PostRepositoryInterface::class);
        $this->read       = $this->service($container, PostReadRepositoryInterface::class);
    }

    /**
     * @template T of object
     * @param  class-string<T> $id
     * @return T
     */
    private function service(ContainerInterface $container, string $id): object
    {
        $service = $container->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }

    public function testWriteThenReadRoundTrip(): void
    {
        $users = $this->users;
        $cats  = $this->categories;
        $tags  = $this->tags;
        $posts = $this->posts;
        $read  = $this->read;

        $author = $users->save(User::register(
            Email::fromString('jane@example.com'),
            'Jane Doe',
            password_hash('secret', PASSWORD_BCRYPT),
        ));
        self::assertMatchesRegularExpression('/^[a-z0-9]{24}$/', $author->id);
        self::assertEquals($author, $users->findByEmail(Email::fromString('jane@example.com')));

        $category = $cats->save(Category::create('PHP Internals', 'Deep dives'));
        $tagList  = $tags->findOrCreateByNames(['php', '8.5', 'hydrator']);
        $tagIds   = array_map(static fn (Tag $t): string => $t->id, $tagList);

        // re-resolving the same names must not create duplicates
        self::assertCount(3, $tags->findOrCreateByNames(['php', '8.5', 'hydrator']));

        $post = Post::draft(
            $author->id,
            'Property Hooks in PHP 8.5',
            str_repeat('word ', 450),
            $category->id,
            'A short look at hooks.',
        )->publish(new DateTimeImmutable('2026-02-01 09:00:00'));

        $post = $posts->save($post, $tagIds);
        self::assertMatchesRegularExpression('/^[a-z0-9]{24}$/', $post->id);

        $reloaded = $posts->findBySlug(Slug::fromString('property-hooks-in-php-8-5'));
        self::assertNotNull($reloaded);
        self::assertSame(PostStatus::Published, $reloaded->status);
        self::assertTrue($reloaded->isPublished());

        // read side: single JOIN query, relations inlined
        $list = $read->listPublished();
        self::assertCount(1, $list);
        self::assertSame('Jane Doe', $list[0]->authorName);
        self::assertSame('PHP Internals', $list[0]->categoryName);
        self::assertSame('php-internals', $list[0]->categorySlug);
        self::assertSame(['8.5', 'hydrator', 'php'], $list[0]->tags);
        self::assertSame('/posts/property-hooks-in-php-8-5', $list[0]->href);

        $view = $read->viewBySlug('property-hooks-in-php-8-5');
        self::assertNotNull($view);
        self::assertSame('jane@example.com', $view->authorEmail);
        self::assertSame('php-internals', $view->categorySlug);
        self::assertSame(3, $view->readingTimeMinutes);
        self::assertTrue($view->isPublished);

        self::assertCount(1, $read->listByCategory('php-internals'));
    }

    public function testUnpublishHidesFromListings(): void
    {
        $users = $this->users;
        $posts = $this->posts;
        $read  = $this->read;

        $author = $users->save(User::register(Email::fromString('a@b.com'), 'A', 'h'));

        $post = $posts->save(
            Post::draft($author->id, 'Temporarily Live', 'body')->publish(new DateTimeImmutable('2026-01-01')),
        );
        self::assertCount(1, $read->listPublished());

        $posts->save($post->unpublish());
        self::assertCount(0, $read->listPublished());
    }

    public function testDeletingPostCascadesTagLinks(): void
    {
        $users = $this->users;
        $tags  = $this->tags;
        $posts = $this->posts;

        $author  = $users->save(User::register(Email::fromString('c@d.com'), 'C', 'h'));
        $tagList = $tags->findOrCreateByNames(['news']);
        $post    = $posts->save(
            Post::draft($author->id, 'With Tags', 'body'),
            [$tagList[0]->id],
        );

        self::assertCount(1, $tags->forPost($post->id));

        $posts->delete($post->id);

        self::assertNull($posts->find($post->id));
        self::assertCount(0, $tags->forPost($post->id));
    }
}
