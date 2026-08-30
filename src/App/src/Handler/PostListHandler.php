<?php

declare(strict_types=1);

namespace App\Handler;

use App\Domain\Repository\PostReadRepositoryInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @psalm-api Instantiated by the DI container.
 */
final class PostListHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly PostReadRepositoryInterface $posts,
        private readonly TemplateRendererInterface $template,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $page    = Pagination::fromQuery($request->getQueryParams());
        $fetched = $this->posts->listPublished($page->fetchLimit(), $page->offset());

        return new HtmlResponse($this->template->render('app::post-list', [
            'posts'   => $page->pageItems($fetched),
            'page'    => $page->page,
            'hasPrev' => $page->hasPrev(),
            'hasNext' => $page->hasNext($fetched),
        ]));
    }
}
