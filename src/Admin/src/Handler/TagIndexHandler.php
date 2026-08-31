<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use App\Domain\Repository\TagRepositoryInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TagIndexHandler extends AbstractAdminHandler
{
    public function __construct(
        TemplateRendererInterface $template,
        UrlHelper $url,
        CurrentUserProvider $currentUser,
        private readonly TagRepositoryInterface $tags,
    ) {
        parent::__construct($template, $url, $currentUser);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view('admin::tags/index', ['tags' => $this->tags->all()]);
    }
}
