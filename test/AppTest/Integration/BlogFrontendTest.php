<?php

declare(strict_types=1);

namespace AppTest\Integration;

use App\Domain\Entity\Category;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Value\Email;
use App\Infrastructure\Persistence\Migration\MigrationRunner;
use DateTimeImmutable;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Application;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Boots the real Mezzio app (pipeline + routes) and exercises the public
 * blog pages end to end against the `mariadb` container.
 */
final class BlogFrontendTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        /** @var ContainerInterface $container */
        $container = require __DIR__ . '/../../../config/container.php';

        $db = $container->get(AdapterInterface::class);
        self::assertInstanceOf(AdapterInterface::class, $db);

        try {
            $db->getDriver()->getConnection()->connect();
        } catch (Throwable $e) {
            self::markTestSkipped('Database not reachable: ' . $e->getMessage());
        }

        $runner = $container->get(MigrationRunner::class);
        self::assertInstanceOf(MigrationRunner::class, $runner);
        $runner->migrate();

        $db->executeQuery('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['post_tag', 'posts', 'tags', 'categories', 'users'] as $table) {
            $db->executeQuery("TRUNCATE TABLE `{$table}`");
        }
        $db->executeQuery('SET FOREIGN_KEY_CHECKS = 1');

        $this->seed($container);

        $app = $container->get(Application::class);
        self::assertInstanceOf(Application::class, $app);
        $factory = $container->get(MiddlewareFactory::class);
        self::assertInstanceOf(MiddlewareFactory::class, $factory);

        // Minimal pipeline: enough to route, dispatch and 404. The full
        // pipeline's ErrorHandler/Whoops leaves PHP error handlers registered,
        // which PHPUnit flags as risky; the blog handlers return their own 404s.
        $app->pipe(RouteMiddleware::class);
        $app->pipe(DispatchMiddleware::class);
        $app->pipe(NotFoundHandler::class);

        (require __DIR__ . '/../../../config/routes.php')($app, $factory, $container);

        $this->app = $app;
    }

    public function testPostListShowsPublishedPosts(): void
    {
        $response = $this->get('/');
        $html     = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Latest posts', $html);
        self::assertStringContainsString('First Lesson', $html);
        self::assertStringContainsString('Second Lesson', $html);
        self::assertStringContainsString('Flight Operations', $html); // category chip
        self::assertStringContainsString('first-lesson.svg', $html); // imaged card thumbnail
    }

    public function testSinglePostRenders(): void
    {
        $withImage = (string) $this->get('/posts/first-lesson')->getBody();
        self::assertStringContainsString('<h1', $withImage);
        self::assertStringContainsString('First Lesson', $withImage);
        self::assertStringContainsString('min read', $withImage);
        self::assertStringContainsString('first-lesson.svg', $withImage); // hero

        $imageless = (string) $this->get('/posts/second-lesson')->getBody();
        self::assertStringNotContainsString('<img', $imageless);
    }

    public function testUnknownPostIs404(): void
    {
        $response = $this->get('/posts/no-such-post');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('not found', (string) $response->getBody());
    }

    public function testCategoryIndexAndCategoryPage(): void
    {
        $index = $this->get('/categories');
        self::assertSame(200, $index->getStatusCode());
        self::assertStringContainsString('Flight Operations', (string) $index->getBody());

        $page = $this->get('/categories/flight-operations');
        $html = (string) $page->getBody();
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('First Lesson', $html);

        self::assertSame(404, $this->get('/categories/no-such-category')->getStatusCode());
    }

    private function get(string $path): ResponseInterface
    {
        return $this->app->handle(new ServerRequest([], [], $path, 'GET'));
    }

    private function seed(ContainerInterface $container): void
    {
        $users      = $this->service($container, UserRepositoryInterface::class);
        $categories = $this->service($container, CategoryRepositoryInterface::class);
        $posts      = $this->service($container, PostRepositoryInterface::class);

        $author   = $users->save(User::register(Email::fromString('julien@guittard.me'), 'Julien', 'hash'));
        $category = $categories->save(Category::create('Flight Operations', 'Checklists and circuits.'));

        foreach (['First Lesson', 'Second Lesson', 'Third Lesson'] as $i => $title) {
            $post = Post::draft($author->id, $title, 'Body of ' . $title . ".\n\nMore detail here.", $category->id);
            if ($title === 'First Lesson') {
                $post = $post->withImage('https://s3.example/first-lesson.svg', 'First Lesson — cover');
            }

            $posts->save($post->publish(new DateTimeImmutable('2026-0' . ($i + 1) . '-01 09:00:00')));
        }
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
}
