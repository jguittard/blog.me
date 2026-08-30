<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PostIndexHandler extends AbstractAdminHandler
{
    public function __construct(
        TemplateRendererInterface $template,
        UrlHelper $url,
        CurrentUserProvider $currentUser,
        private readonly PostRepositoryInterface $posts,
        private readonly CategoryRepositoryInterface $categories,
    ) {
        parent::__construct($template, $url, $currentUser);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $categoryNames = [];
        foreach ($this->categories->all() as $category) {
            $categoryNames[$category->id] = $category->name;
        }

        return $this->view('admin::posts/index', [
            'posts'         => $this->posts->all(),
            'categoryNames' => $categoryNames,
        ]);
    }
}
