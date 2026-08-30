<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Domain\ReadModel\PostView;
use App\Domain\Repository\PostReadRepositoryInterface;
use App\Domain\Value\PostStatus;
use App\Handler\PostViewHandler;
use DateTimeImmutable;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class PostViewHandlerTest extends TestCase
{
    private function request(string $slug): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => $name === 'slug' ? $slug : $default,
        );

        return $request;
    }

    public function testRendersThePostWhenFound(): void
    {
        $view = new PostView(
            id: 'clh1abcd2efgh3ijkl4mnop5',
            slug: 'hello-world',
            title: 'Hello World',
            excerpt: null,
            body: "One.\n\nTwo.",
            status: PostStatus::Published,
            publishedAt: new DateTimeImmutable('2026-01-01'),
            updatedAt: new DateTimeImmutable('2026-01-02'),
            authorName: 'Julien',
            authorEmail: 'julien@guittard.me',
            categoryName: 'Navigation',
            categorySlug: 'navigation',
            tags: ['nav'],
        );

        $repo = $this->createMock(PostReadRepositoryInterface::class);
        $repo->expects($this->once())->method('viewBySlug')->with('hello-world')->willReturn($view);

        $template = $this->createMock(TemplateRendererInterface::class);
        $template->expects($this->once())
            ->method('render')
            ->with('app::post-view', ['post' => $view])
            ->willReturn('<html></html>');

        $response = (new PostViewHandler($repo, $template))->handle($this->request('hello-world'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRenders404WhenMissing(): void
    {
        $repo = $this->createMock(PostReadRepositoryInterface::class);
        $repo->method('viewBySlug')->willReturn(null);

        $template = $this->createMock(TemplateRendererInterface::class);
        $template->expects($this->once())->method('render')->with('error::404')->willReturn('<html>404</html>');

        $response = (new PostViewHandler($repo, $template))->handle($this->request('nope'));

        self::assertSame(404, $response->getStatusCode());
    }
}
