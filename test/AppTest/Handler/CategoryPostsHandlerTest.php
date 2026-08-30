<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Domain\Entity\Category;
use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Repository\PostReadRepositoryInterface;
use App\Handler\CategoryPostsHandler;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class CategoryPostsHandlerTest extends TestCase
{
    private function request(string $slug): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => $name === 'slug' ? $slug : $default,
        );
        $request->method('getQueryParams')->willReturn([]);

        return $request;
    }

    public function testRendersCategoryPostsWhenFound(): void
    {
        $category = Category::create('Meteorology', 'The sky');

        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->method('findBySlug')->willReturn($category);

        $posts = $this->createMock(PostReadRepositoryInterface::class);
        $posts->expects($this->once())->method('listByCategory')->with('meteorology')->willReturn([]);

        $template = $this->createMock(TemplateRendererInterface::class);
        $template->expects($this->once())
            ->method('render')
            ->with('app::category-posts', $this->callback(
                static fn (array $p): bool => $p['category'] === $category && $p['posts'] === [] && $p['page'] === 1,
            ))
            ->willReturn('<html></html>');

        $response = (new CategoryPostsHandler($categories, $posts, $template))->handle($this->request('meteorology'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRenders404WhenCategoryMissing(): void
    {
        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->method('findBySlug')->willReturn(null);

        $posts = $this->createMock(PostReadRepositoryInterface::class);
        $posts->expects($this->never())->method('listByCategory');

        $template = $this->createMock(TemplateRendererInterface::class);
        $template->expects($this->once())->method('render')->with('error::404')->willReturn('<html>404</html>');

        $response = (new CategoryPostsHandler($categories, $posts, $template))->handle($this->request('ghost'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRenders404OnMalformedSlug(): void
    {
        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->expects($this->never())->method('findBySlug');

        $posts = $this->createMock(PostReadRepositoryInterface::class);

        $template = $this->createMock(TemplateRendererInterface::class);
        $template->expects($this->once())->method('render')->with('error::404')->willReturn('<html>404</html>');

        $response = (new CategoryPostsHandler($categories, $posts, $template))->handle($this->request('Not A Slug!'));

        self::assertSame(404, $response->getStatusCode());
    }
}
