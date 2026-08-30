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
final class PostViewHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly PostReadRepositoryInterface $posts,
        private readonly TemplateRendererInterface $template,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $post = $this->posts->viewBySlug((string) $request->getAttribute('slug', ''));

        if ($post === null) {
            return new HtmlResponse($this->template->render('error::404'), 404);
        }

        return new HtmlResponse($this->template->render('app::post-view', ['post' => $post]));
    }
}
