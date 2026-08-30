<?php

declare(strict_types=1);

namespace App\Handler;

use App\Domain\Repository\CategoryRepositoryInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @psalm-api Instantiated by the DI container.
 */
final class CategoryListHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly TemplateRendererInterface $template,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse($this->template->render('app::category-list', [
            'categories' => $this->categories->all(),
        ]));
    }
}
