<?php

declare(strict_types=1);

namespace AdminTest\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use Admin\Form\PostForm;
use Admin\Handler\PostCreateHandler;
use Admin\Support\PostFormMapper;
use Admin\Support\PostWriter;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\TagRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Value\Email;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class PostCreateHandlerTest extends TestCase
{
    /** @var TemplateRendererInterface&MockObject */
    private TemplateRendererInterface $template;
    /** @var UrlHelper&MockObject */
    private UrlHelper $url;
    private CurrentUserProvider $currentUser;
    /** @var PostRepositoryInterface&MockObject */
    private PostRepositoryInterface $posts;
    /** @var TagRepositoryInterface&MockObject */
    private TagRepositoryInterface $tags;

    protected function setUp(): void
    {
        $this->template = $this->createMock(TemplateRendererInterface::class);
        $this->url      = $this->createMock(UrlHelper::class);
        $this->posts    = $this->createMock(PostRepositoryInterface::class);
        $this->tags     = $this->createMock(TagRepositoryInterface::class);
        $this->tags->method('findOrCreateByNames')->willReturn([]);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(
            User::register(Email::fromString('julien@guittard.me'), 'Julien', 'hash'),
        );
        $this->currentUser = new CurrentUserProvider($users, 'julien@guittard.me');
    }

    private function handler(): PostCreateHandler
    {
        return new PostCreateHandler(
            $this->template,
            $this->url,
            $this->currentUser,
            new PostForm(),
            new PostWriter($this->posts, $this->tags, new PostFormMapper()),
        );
    }

    public function testGetRendersTheForm(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');

        $this->template->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(static fn (string $name): string => $name === 'admin::posts/form' ? 'FORM' : 'LAYOUT');
        $this->posts->expects($this->never())->method('save');

        self::assertSame(200, $this->handler()->handle($request)->getStatusCode());
    }

    public function testValidPostSavesWithTheCurrentUserAndRedirects(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getParsedBody')->willReturn([
            'title' => 'From Test',
            'body' => 'Body copy.',
            'status' => 'draft',
            'excerpt' => '',
            'categoryId' => '',
            'publishedAt' => '',
            'tags' => '',
            'imageUrl' => '',
            'imageAlt' => '',
        ]);
        $this->url->method('generate')->with('admin.posts.index')->willReturn('/admin/posts');

        $this->posts->expects($this->once())
            ->method('save')
            ->with(self::callback(static fn (Post $p): bool => $p->authorId !== '' && $p->title === 'From Test'), [])
            ->willReturnArgument(0);

        $response = $this->handler()->handle($request);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/posts', $response->getHeaderLine('Location'));
    }

    public function testInvalidPostReRendersTheForm(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getParsedBody')->willReturn(['title' => '', 'body' => '', 'status' => 'draft']);

        $this->template->method('render')->willReturn('HTML');
        $this->posts->expects($this->never())->method('save');

        self::assertSame(200, $this->handler()->handle($request)->getStatusCode());
    }
}
