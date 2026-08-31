<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use App\Domain\Repository\CategoryRepositoryInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CategoryDeleteHandler extends AbstractAdminHandler
{
    public function __construct(
        TemplateRendererInterface $template,
        UrlHelper $url,
        CurrentUserProvider $currentUser,
        private readonly CategoryRepositoryInterface $categories,
    ) {
        parent::__construct($template, $url, $currentUser);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->categories->delete((string) $request->getAttribute('id', ''));

        return $this->redirectToRoute('admin.categories.index');
    }
}
