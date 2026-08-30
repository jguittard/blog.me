<?php

declare(strict_types=1);

namespace App\Handler;

use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Repository\PostReadRepositoryInterface;
use App\Domain\Value\Slug;
use InvalidArgumentException;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @psalm-api Instantiated by the DI container.
 */
final class CategoryPostsHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly PostReadRepositoryInterface $posts,
        private readonly TemplateRendererInterface $template,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $raw = (string) $request->getAttribute('slug', '');

        try {
            $slug = Slug::fromString($raw);
        } catch (InvalidArgumentException) {
            return $this->notFound();
        }

        $category = $this->categories->findBySlug($slug);

        if ($category === null) {
            return $this->notFound();
        }

        $page    = Pagination::fromQuery($request->getQueryParams());
        $fetched = $this->posts->listByCategory($raw, $page->fetchLimit(), $page->offset());

        return new HtmlResponse($this->template->render('app::category-posts', [
            'category' => $category,
            'posts'    => $page->pageItems($fetched),
            'page'     => $page->page,
            'hasPrev'  => $page->hasPrev(),
            'hasNext'  => $page->hasNext($fetched),
        ]));
    }

    private function notFound(): ResponseInterface
    {
        return new HtmlResponse($this->template->render('error::404'), 404);
    }
}
