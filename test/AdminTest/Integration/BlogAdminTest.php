<?php

declare(strict_types=1);

namespace AdminTest\Integration;

use App\Domain\Entity\Category;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\TagRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Value\Email;
use App\Domain\Value\Slug;
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
 * Boots the real app and drives the /admin CRUD end to end against MariaDB.
 *
 * Each test gets a fresh container (so the form services, which are shared,
 * start clean) and a truncated schema.
 */
final class BlogAdminTest extends TestCase
{
    private const string ADMIN_EMAIL = 'julien@guittard.me';

    private Application $app;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        /** @var ContainerInterface $container */
        $container       = require __DIR__ . '/../../../config/container.php';
        $this->container = $container;

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

        // The admin area's hard-coded "current user" must exist.
        $this->service(UserRepositoryInterface::class)
            ->save(User::register(Email::fromString(self::ADMIN_EMAIL), 'Julien', 'hash'));
        $this->service(CategoryRepositoryInterface::class)->save(Category::create('Flight Operations'));

        $app = $container->get(Application::class);
        self::assertInstanceOf(Application::class, $app);
        $factory = $container->get(MiddlewareFactory::class);
        self::assertInstanceOf(MiddlewareFactory::class, $factory);

        $app->pipe(RouteMiddleware::class);
        $app->pipe(DispatchMiddleware::class);
        $app->pipe(NotFoundHandler::class);

        (require __DIR__ . '/../../../config/routes.php')($app, $factory, $container);

        $this->app = $app;
    }

    public function testDashboardShowsTheHardCodedUser(): void
    {
        $response = $this->get('/admin');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Signed in as', (string) $response->getBody());
        self::assertStringContainsString('Julien', (string) $response->getBody());
    }

    public function testPostsIndexRendersDrafts(): void
    {
        $this->seedPost('Quiet Draft', publish: false);

        $response = $this->get('/admin/posts');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Quiet Draft', (string) $response->getBody());
    }

    public function testCreatingAPostPersistsItWithTheCurrentUserAsAuthor(): void
    {
        $response = $this->post('/admin/posts/new', $this->postBody([
            'title'  => 'Written In A Test',
            'body'   => "Para one.\n\nPara two with a few more words.",
            'status' => 'published',
            'tags'   => 'testing, admin',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/posts', $response->getHeaderLine('Location'));

        $post = $this->service(PostRepositoryInterface::class)->findBySlug(Slug::fromString('written-in-a-test'));
        self::assertNotNull($post);

        $author = $this->service(UserRepositoryInterface::class)->findByEmail(Email::fromString(self::ADMIN_EMAIL));
        self::assertNotNull($author);
        self::assertSame($author->id, $post->authorId);
        self::assertCount(2, $this->service(TagRepositoryInterface::class)->forPost($post->id));

        // Published, so it also reaches the public site.
        self::assertStringContainsString('Written In A Test', (string) $this->get('/')->getBody());
    }

    public function testInvalidPostReRendersTheFormWithErrors(): void
    {
        $response = $this->post('/admin/posts/new', ['title' => '', 'body' => '', 'status' => 'draft']);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text-red-600', (string) $response->getBody());

        $posts = $this->service(PostRepositoryInterface::class);
        self::assertSame([], $posts->all());
    }

    public function testEditPrefillsThenPersistsTheChange(): void
    {
        $post = $this->seedPost('Before Edit');

        $form = (string) $this->get("/admin/posts/{$post->id}/edit")->getBody();
        self::assertSame(200, $this->get("/admin/posts/{$post->id}/edit")->getStatusCode());
        self::assertStringContainsString('Before Edit', $form);

        $response = $this->post("/admin/posts/{$post->id}/edit", $this->postBody([
            'title'  => 'After Edit',
            'body'   => 'Reworked body.',
            'status' => 'draft',
        ]));

        self::assertSame(303, $response->getStatusCode());

        $reloaded = $this->service(PostRepositoryInterface::class)->find($post->id);
        self::assertNotNull($reloaded);
        self::assertSame('After Edit', $reloaded->title);
        self::assertSame('after-edit', $reloaded->slug->value);
    }

    public function testDeletingAPostRemovesIt(): void
    {
        $post = $this->seedPost('Doomed Post');

        $response = $this->post("/admin/posts/{$post->id}/delete", []);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/posts', $response->getHeaderLine('Location'));
        self::assertNull($this->service(PostRepositoryInterface::class)->find($post->id));
    }

    public function testUnknownPostEditIs404(): void
    {
        self::assertSame(404, $this->get('/admin/posts/nope/edit')->getStatusCode());
    }

    public function testCreatingACategory(): void
    {
        $response = $this->post('/admin/categories/new', [
            'name'        => 'Human Performance',
            'description' => 'The pilot as a component.',
        ]);

        self::assertSame(303, $response->getStatusCode());
        $category = $this->service(CategoryRepositoryInterface::class)
            ->findBySlug(Slug::fromString('human-performance'));
        self::assertNotNull($category);
        self::assertSame('The pilot as a component.', $category->description);
    }

    public function testCreatingATag(): void
    {
        $response = $this->post('/admin/tags/new', ['name' => 'Night Flying']);

        self::assertSame(303, $response->getStatusCode());
        self::assertNotNull(
            $this->service(TagRepositoryInterface::class)->findBySlug(Slug::fromString('night-flying')),
        );
    }

    private function seedPost(string $title, bool $publish = true): Post
    {
        $author = $this->service(UserRepositoryInterface::class)->findByEmail(Email::fromString(self::ADMIN_EMAIL));
        self::assertNotNull($author);

        $post = Post::draft($author->id, $title, "Body of {$title}.\n\nMore detail.");
        if ($publish) {
            $post = $post->publish(new DateTimeImmutable('2026-01-01 09:00:00'));
        }

        return $this->service(PostRepositoryInterface::class)->save($post);
    }

    /**
     * @param  array<string, string> $overrides
     * @return array<string, string>
     */
    private function postBody(array $overrides): array
    {
        return $overrides + [
            'title'       => 'Untitled',
            'body'        => 'Body.',
            'excerpt'     => '',
            'categoryId'  => '',
            'status'      => 'draft',
            'publishedAt' => '',
            'tags'        => '',
            'imageUrl'    => '',
            'imageAlt'    => '',
        ];
    }

    private function get(string $path): ResponseInterface
    {
        return $this->app->handle(new ServerRequest([], [], $path, 'GET'));
    }

    /** @param array<string, mixed> $body */
    private function post(string $path, array $body): ResponseInterface
    {
        return $this->app->handle(
            (new ServerRequest([], [], $path, 'POST'))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withParsedBody($body),
        );
    }

    /**
     * @template T of object
     * @param  class-string<T> $id
     * @return T
     */
    private function service(string $id): object
    {
        $service = $this->container->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
