<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Domain\ReadModel\PostListItem;
use App\Domain\Repository\PostReadRepositoryInterface;
use App\Domain\Value\PostStatus;
use App\Handler\Pagination;
use App\Handler\PostListHandler;
use DateTimeImmutable;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function array_fill;
use function count;
use function is_array;

final class PostListHandlerTest extends TestCase
{
    private static function item(string $slug): PostListItem
    {
        return new PostListItem(
            id: 'clh1abcd2efgh3ijkl4mnop5',
            slug: $slug,
            title: 'A post',
            excerpt: 'Teaser',
            bodyPreview: 'Body preview',
            status: PostStatus::Published,
            publishedAt: new DateTimeImmutable('2026-01-01'),
            authorName: 'Julien',
            categoryName: 'Navigation',
            categorySlug: 'navigation',
            tags: ['nav'],
        );
    }

    public function testRendersFirstPageWithoutPaginationWhenItFits(): void
    {
        $repo = $this->createMock(PostReadRepositoryInterface::class);
        $repo->method('listPublished')
            ->with(Pagination::PER_PAGE + 1, 0)
            ->willReturn([self::item('a'), self::item('b')]);

        $template = $this->createMock(TemplateRendererInterface::class);
        $template->expects($this->once())
            ->method('render')
            ->with('app::post-list', $this->callback(static function (array $p): bool {
                return $p['page'] === 1 && $p['hasPrev'] === false && $p['hasNext'] === false && $p['posts'] !== [];
            }))
            ->willReturn('<html></html>');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn([]);

        $response = (new PostListHandler($repo, $template))->handle($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testSetsHasNextWhenRepositoryReturnsProbeRow(): void
    {
        $repo = $this->createMock(PostReadRepositoryInterface::class);
        $repo->method('listPublished')->willReturn(array_fill(0, Pagination::PER_PAGE + 1, self::item('x')));

        $template = $this->createMock(TemplateRendererInterface::class);
        $template->method('render')
            ->with('app::post-list', $this->callback(static function (array $p): bool {
                return $p['page'] === 2
                    && $p['hasPrev'] === true
                    && $p['hasNext'] === true
                    && is_array($p['posts'])
                    && count($p['posts']) === Pagination::PER_PAGE;
            }))
            ->willReturn('<html></html>');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn(['page' => '2']);

        self::assertSame(200, (new PostListHandler($repo, $template))->handle($request)->getStatusCode());
    }
}
