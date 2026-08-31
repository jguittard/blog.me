<?php

declare(strict_types=1);

namespace AdminTest\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use Admin\Handler\PostDeleteHandler;
use App\Domain\Entity\User;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Value\Email;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class PostDeleteHandlerTest extends TestCase
{
    public function testDeletesThePostAndRedirects(): void
    {
        $posts = $this->createMock(PostRepositoryInterface::class);
        $posts->expects($this->once())->method('delete')->with('post-123');

        $url = $this->createMock(UrlHelper::class);
        $url->method('generate')->with('admin.posts.index')->willReturn('/admin/posts');

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(
            User::register(Email::fromString('julien@guittard.me'), 'Julien', 'hash'),
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(
            static fn (string $n, mixed $d = null): mixed => $n === 'id' ? 'post-123' : $d,
        );

        $handler = new PostDeleteHandler(
            $this->createMock(TemplateRendererInterface::class),
            $url,
            new CurrentUserProvider($users, 'julien@guittard.me'),
            $posts,
        );

        $response = $handler->handle($request);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/posts', $response->getHeaderLine('Location'));
    }
}
