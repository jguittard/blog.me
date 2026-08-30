<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\TagRepositoryInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function count;

final class DashboardHandler extends AbstractAdminHandler
{
    public function __construct(
        TemplateRendererInterface $template,
        UrlHelper $url,
        CurrentUserProvider $currentUser,
        private readonly PostRepositoryInterface $posts,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TagRepositoryInterface $tags,
    ) {
        parent::__construct($template, $url, $currentUser);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view('admin::dashboard', [
            'postCount'     => count($this->posts->all()),
            'categoryCount' => count($this->categories->all()),
            'tagCount'      => count($this->tags->all()),
        ]);
    }
}
