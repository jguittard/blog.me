<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function explode;
use function str_replace;

abstract class AbstractAdminHandler implements RequestHandlerInterface
{
    public function __construct(
        protected readonly TemplateRendererInterface $template,
        protected readonly UrlHelper $url,
        protected readonly CurrentUserProvider $currentUser,
    ) {
    }

    /**
     * Render an admin page inside `admin::layout`. The two renders share the
     * renderer's placeholder helpers (headTitle, ...) but not view variables,
     * so `currentUser` / `activeNav` are injected into both.
     *
     * @param array<string, mixed> $params
     */
    protected function view(string $template, array $params = []): HtmlResponse
    {
        $shared = [
            'currentUser' => $this->currentUser->get(),
            'activeNav'   => explode('/', str_replace('admin::', '', $template))[0],
        ];

        $content = $this->template->render($template, [...$shared, ...$params, 'layout' => false]);

        return new HtmlResponse(
            $this->template->render('admin::layout', [...$shared, 'content' => $content, 'layout' => false]),
        );
    }

    /**
     * @param non-empty-string     $route
     * @param array<string, mixed> $routeParams
     */
    protected function redirectToRoute(string $route, array $routeParams = []): ResponseInterface
    {
        return new RedirectResponse($this->url->generate($route, $routeParams), 303);
    }

    protected function notFound(): HtmlResponse
    {
        return new HtmlResponse($this->template->render('error::404'), 404);
    }
}
